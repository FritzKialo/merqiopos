<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Admin-side management for orders placed through the public online shop
 * (Shop\StoreController). Before this controller existed there was no
 * authenticated view anywhere in the app for a business to see, fulfil, or
 * manually confirm payment on an online order — the only path to "paid" was
 * the M-Pesa webhook firing, so a business without M-Pesa configured (or a
 * customer paying by cash-on-delivery / bank transfer) had orders that went
 * into a black hole with zero way to act on them.
 */
class OnlineOrderController extends Controller
{
    private function businessId(): int
    {
        return Auth::user()->currentBusiness()->id;
    }

    private function authorizeOrder(OnlineOrder $order): void
    {
        abort_if($order->business_id !== $this->businessId(), 403);
    }

    public function index(Request $request)
    {
        $businessId = $this->businessId();

        $query = OnlineOrder::where('business_id', $businessId)
            ->withCount('items')
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('reference', 'like', "%{$s}%")
                  ->orWhere('customer_name', 'like', "%{$s}%")
                  ->orWhere('customer_phone', 'like', "%{$s}%");
            });
        }

        $orders = $query->paginate(20)->withQueryString();

        $counts = [
            'all'        => OnlineOrder::where('business_id', $businessId)->count(),
            'pending'    => OnlineOrder::where('business_id', $businessId)->where('status', 'pending')->count(),
            'paid'       => OnlineOrder::where('business_id', $businessId)->where('status', 'paid')->count(),
            'processing' => OnlineOrder::where('business_id', $businessId)->where('status', 'processing')->count(),
            'shipped'    => OnlineOrder::where('business_id', $businessId)->where('status', 'shipped')->count(),
            'delivered'  => OnlineOrder::where('business_id', $businessId)->where('status', 'delivered')->count(),
            'cancelled'  => OnlineOrder::where('business_id', $businessId)->where('status', 'cancelled')->count(),
        ];

        $business = Auth::user()->currentBusiness();

        return view('online-orders.index', compact('orders', 'counts', 'business'));
    }

    public function show(OnlineOrder $onlineOrder)
    {
        $this->authorizeOrder($onlineOrder);
        $onlineOrder->load(['items.product', 'items.variant', 'sale']);
        return view('online-orders.show', ['order' => $onlineOrder]);
    }

    /**
     * Manually confirm payment for an order that didn't come through the
     * M-Pesa webhook (cash on delivery, bank transfer, or M-Pesa paid but
     * the callback never arrived). Mirrors StoreController::mpesaCallback()
     * exactly — same variant-vs-product stock decrement rule the POS and
     * every other stock-moving path in this app already follows.
     */
    public function markPaid(Request $request, OnlineOrder $onlineOrder)
    {
        $this->authorizeOrder($onlineOrder);

        if ($onlineOrder->status !== 'pending') {
            return back()->with('error', 'Only pending orders can be marked as paid.');
        }

        $request->validate([
            'payment_method' => 'nullable|string|max:50',
        ]);

        DB::transaction(function () use ($onlineOrder, $request) {
            $onlineOrder->update([
                'status'                => 'paid',
                'payment_confirmed_at'  => now(),
                'payment_method'        => $request->payment_method ?: $onlineOrder->payment_method,
            ]);

            foreach ($onlineOrder->items as $item) {
                // Skip the bundle summary line — see Shop\StoreController::
                // markOnlineOrderPaid() for why.
                if ($item->is_bundle_summary) {
                    continue;
                }
                \App\Models\ProductBatch::consume((int) $item->product_id, $item->variant_id ?: null, (float) $item->quantity);
                if ($item->variant_id) {
                    ProductVariant::where('id', $item->variant_id)->decrement('stock_qty', $item->quantity);
                } else {
                    Product::where('id', $item->product_id)->decrement('stock_qty', $item->quantity);
                }
            }

            // Same timing as the stock decrement above — only recorded
            // once actually paid. Mirrors
            // Shop\StoreController::recordCouponUsage() exactly.
            if ($onlineOrder->coupon_code) {
                $coupon = Coupon::where('business_id', $onlineOrder->business_id)
                    ->where('code', $onlineOrder->coupon_code)
                    ->first();
                if ($coupon) {
                    $coupon->increment('used_count');
                    CouponUsage::create([
                        'coupon_id'        => $coupon->id,
                        'business_id'      => $onlineOrder->business_id,
                        'online_order_id'  => $onlineOrder->id,
                        'discount_applied' => $onlineOrder->coupon_discount_amount,
                        'used_at'          => now(),
                    ]);
                }
            }

            // Record it as a real Sale so it shows up in the Sales list,
            // dashboard revenue, and every report — see SaleService for why.
            (new SaleService())->createSaleFromOnlineOrder($onlineOrder->fresh());
        });

        return back()->with('success', 'Order marked as paid and stock updated.');
    }

    /**
     * Advance fulfilment status (processing/shipped/delivered). Kept
     * separate from markPaid — payment confirmation and fulfilment are
     * different real-world events and a business may confirm payment
     * (cash on delivery) well before actually shipping.
     */
    public function updateStatus(Request $request, OnlineOrder $onlineOrder)
    {
        $this->authorizeOrder($onlineOrder);

        $request->validate([
            'status' => 'required|in:processing,shipped,delivered',
        ]);

        if (!in_array($onlineOrder->status, ['paid', 'processing', 'shipped'], true)) {
            return back()->with('error', 'Order must be paid before it can be processed, shipped, or delivered.');
        }

        $onlineOrder->update(['status' => $request->status]);

        return back()->with('success', 'Order status updated to ' . ucfirst($request->status) . '.');
    }

    /**
     * Cancel an order. If it was already paid (stock already decremented),
     * restock the items — same restock-on-cancel principle SaleService and
     * SaleReturnController already apply elsewhere in the app. Once an
     * order has shipped it's too late to just "cancel" from here.
     */
    public function cancel(OnlineOrder $onlineOrder)
    {
        $this->authorizeOrder($onlineOrder);

        if (!in_array($onlineOrder->status, ['pending', 'paid'], true)) {
            return back()->with('error', 'Only pending or paid (not yet shipped) orders can be cancelled here.');
        }

        DB::transaction(function () use ($onlineOrder) {
            if ($onlineOrder->status === 'paid') {
                $onlineOrder->load(['items', 'sale']);
                if ($onlineOrder->sale && $onlineOrder->sale->sale_status !== 'cancelled') {
                    // Cancelling the linked Sale already restores stock
                    // (SaleService applies the same variant-vs-product
                    // rule) — restocking again below would double-restore
                    // it. This also keeps revenue reports honest: without
                    // this, a cancelled order's Sale would sit forever as
                    // "completed/paid", still counted in every report.
                    (new SaleService())->cancelSale($onlineOrder->sale);
                } else {
                    foreach ($onlineOrder->items as $item) {
                        // Skip the bundle summary line — see
                        // Shop\StoreController::markOnlineOrderPaid().
                        if ($item->is_bundle_summary) {
                            continue;
                        }
                        if ($item->variant_id) {
                            ProductVariant::where('id', $item->variant_id)->increment('stock_qty', $item->quantity);
                        } else {
                            Product::where('id', $item->product_id)->increment('stock_qty', $item->quantity);
                        }
                    }
                }

                // A coupon's usage is only ever recorded once the order is
                // actually paid (see markPaid() above and
                // Shop\StoreController::recordCouponUsage()) — cancelling a
                // paid order after the fact must reverse that exact same
                // side effect, or a limited-use coupon permanently loses one
                // use for an order that never actually went through.
                if ($onlineOrder->coupon_code) {
                    $usage = CouponUsage::where('online_order_id', $onlineOrder->id)->first();
                    if ($usage) {
                        $coupon = Coupon::find($usage->coupon_id);
                        if ($coupon && $coupon->used_count > 0) {
                            $coupon->decrement('used_count');
                        }
                        $usage->delete();
                    }
                }
            }

            $onlineOrder->update(['status' => 'cancelled']);
        });

        return back()->with('success', 'Order cancelled.');
    }
}
