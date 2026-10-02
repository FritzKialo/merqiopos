<?php

namespace App\Http\Controllers;

use App\Models\CustomerCredit;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\StockAdjustment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaleReturnController extends Controller
{
    private function business()
    {
        return Auth::user()->currentBusiness();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $business = $this->business();

        $returns = SaleReturn::with(['sale', 'user', 'customer'])
            ->forBusiness($business->id)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('sales.returns.index', compact('returns'));
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function create(Request $request)
    {
        $business = $this->business();

        $request->validate(['sale_id' => 'required|integer|exists:sales,id']);

        $sale = Sale::with('items.product', 'customer')
            ->where('business_id', $business->id)
            ->where('sale_status', '!=', 'cancelled')
            ->findOrFail($request->sale_id);

        // Determine already-returned quantities per sale item
        $returnedQtys = [];
        foreach ($sale->items as $item) {
            $returned = \App\Models\SaleReturnItem::where('sale_item_id', $item->id)->sum('quantity_returned');
            $returnedQtys[$item->id] = (int) $returned;
        }

        return view('sales.returns.create', compact('sale', 'returnedQtys'));
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $business = $this->business();

        $request->validate([
            'sale_id'       => 'required|integer|exists:sales,id',
            'items'         => 'required|array|min:1',
            'items.*.sale_item_id' => 'required|integer|exists:sale_items,id',
            'items.*.quantity_returned' => 'required|integer|min:1',
            'stock_action'  => 'required|in:restock,writeoff',
            'refund_method' => 'required|in:cash,mpesa,store_credit',
            'reason'        => 'required|string|max:500',
            'notes'         => 'nullable|string|max:1000',
        ]);

        $sale = Sale::with('items')
            ->where('business_id', $business->id)
            ->findOrFail($request->sale_id);

        // Build item map by sale_item_id
        $saleItemMap = $sale->items->keyBy('id');

        $lineItems  = [];
        $totalRefund = 0;

        foreach ($request->items as $line) {
            $saleItemId = (int) $line['sale_item_id'];
            $qtyReturned = (int) $line['quantity_returned'];

            if (! isset($saleItemMap[$saleItemId])) {
                return back()->withErrors(['items' => 'Invalid sale item.'])->withInput();
            }

            $saleItem = $saleItemMap[$saleItemId];

            // A bundle summary line (bundle price, tagged with its first
            // component's product_id purely for receipt display) isn't a
            // real stock item — its own components each have their own
            // separate, real sale_item rows. Returning it would restock
            // that first component by the BUNDLE quantity instead of its
            // actual per-bundle quantity (the same shape of bug just fixed
            // in SaleService), and there's no correct single-line meaning
            // for "return part of a bundle" anyway. Block it outright
            // rather than move stock incorrectly; a bundle return should
            // be done by returning its individual component lines instead.
            if ($saleItem->is_bundle_summary) {
                return back()->withErrors(['items' => "\"{$saleItem->product_name}\" is a bundle — return its individual items instead."])->withInput();
            }

            // Validate qty not exceeding original minus already returned
            $alreadyReturned = \App\Models\SaleReturnItem::where('sale_item_id', $saleItemId)->sum('quantity_returned');
            $maxReturnable = $saleItem->quantity - $alreadyReturned;

            if ($qtyReturned > $maxReturnable) {
                return back()->withErrors(['items' => "Cannot return more than {$maxReturnable} units of {$saleItem->product_name}."])->withInput();
            }

            $subtotal = $qtyReturned * $saleItem->unit_price;
            $totalRefund += $subtotal;

            $lineItems[] = [
                'sale_item_id'      => $saleItemId,
                'product_id'        => $saleItem->product_id,
                'variant_id'        => $saleItem->variant_id,
                'product_name'      => $saleItem->product_name,
                'quantity_returned' => $qtyReturned,
                'unit_price'        => $saleItem->unit_price,
                'subtotal'          => $subtotal,
            ];
        }

        $createdReturn = null;
        DB::transaction(function () use ($request, $sale, $business, $lineItems, $totalRefund, &$createdReturn) {
            $saleReturn = SaleReturn::create([
                'business_id'   => $business->id,
                'sale_id'       => $sale->id,
                'user_id'       => Auth::id(),
                'customer_id'   => $sale->customer_id,
                'return_number' => 'RTN-TEMP',
                'total_refund'  => $totalRefund,
                'stock_action'  => $request->stock_action,
                'refund_method' => $request->refund_method,
                'reason'        => $request->reason,
                'notes'         => $request->notes,
            ]);

            // Assign return number using ID
            $saleReturn->update(['return_number' => 'RTN-' . str_pad($saleReturn->id, 5, '0', STR_PAD_LEFT)]);
            $createdReturn = $saleReturn;

            // Create return items + handle stock
            foreach ($lineItems as $line) {
                $saleReturn->items()->create($line);

                if ($request->stock_action === 'restock' && $line['product_id']) {
                    $product = Product::find($line['product_id']);
                    if ($product) {
                        // Restock the specific variant that was actually sold, when
                        // there is one — falls back to the parent product otherwise.
                        if (!empty($line['variant_id'])) {
                            $variant = ProductVariant::find($line['variant_id']);
                            if ($variant) {
                                $variant->increment('stock_qty', $line['quantity_returned']);
                            }
                        }

                        $before = $product->stock_qty;
                        // The parent product's own stock_qty is only decremented at
                        // sale time when the sold item had no variant, so only bump
                        // it back up in that same case — otherwise it was never touched.
                        if (empty($line['variant_id'])) {
                            $product->increment('stock_qty', $line['quantity_returned']);
                            $product->refresh();
                        }

                        StockAdjustment::create([
                            'business_id'     => $business->id,
                            'product_id'      => $product->id,
                            'user_id'         => Auth::id(),
                            'type'            => 'return_in',
                            'quantity_before' => $before,
                            'quantity_change' => $line['quantity_returned'],
                            'quantity_after'  => empty($line['variant_id']) ? $product->stock_qty : $before,
                            'reason'          => 'Sale return: ' . $saleReturn->return_number,
                            'reference'       => $saleReturn->return_number,
                        ]);
                    }
                }
            }

            // Store credit
            if ($request->refund_method === 'store_credit' && $sale->customer_id) {
                $customer = \App\Models\Customer::find($sale->customer_id);
                if ($customer) {
                    $customer->increment('credit_balance', $totalRefund);
                    $customer->refresh();

                    CustomerCredit::create([
                        'business_id'    => $business->id,
                        'customer_id'    => $customer->id,
                        'user_id'        => Auth::id(),
                        'sale_return_id' => $saleReturn->id,
                        'type'           => 'store_credit',
                        'amount'         => $totalRefund,
                        'balance_after'  => $customer->credit_balance,
                        'reference'      => $saleReturn->return_number,
                        'notes'          => 'Store credit from return',
                    ]);
                }
            }
        });

        if ($createdReturn) {
            \App\Models\AuditLog::record('sale.return', $sale, ['return' => $createdReturn->return_number, 'refund' => (float) $totalRefund, 'method' => $request->refund_method]);
        }

        // Report the refund to KRA against the original receipt.
        if ($createdReturn) {
            try {
                \App\Models\EtimsRefund::queue('sale_return', $createdReturn->id, $business, $sale, null, (float) $totalRefund);
            } catch (\Throwable $e) {
                \Log::warning('eTIMS refund queue failed for return ' . $createdReturn->id . ': ' . $e->getMessage());
            }
        }

        return redirect()->route('returns.index')->with('success', 'Return processed successfully.');
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(SaleReturn $saleReturn)
    {
        $business = $this->business();

        if ($saleReturn->business_id !== $business->id) {
            abort(403);
        }

        $saleReturn->load(['sale', 'items.product', 'user', 'customer']);

        return view('sales.returns.show', compact('saleReturn'));
    }
}
