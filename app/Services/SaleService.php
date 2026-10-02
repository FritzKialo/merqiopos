<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Customer;
use App\Models\Discount;
use App\Models\LoyaltyTransaction;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\SerialNumber;
use App\Models\Shift;
use App\Models\TaxRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaleService
{
    /**
     * Create a new sale with items, stock deduction, and customer balance update.
     *
     * @param  array  $data  Validated data from SaleRequest
     * @param  int    $businessId
     * @param  int    $userId
     * @return Sale
     * @throws \Exception on stock or ownership errors
     */
    public function createSale(array $data, int $businessId, int $userId): Sale
    {
        // customer_id/discount_id were only ever validated with a bare
        // exists: rule (SaleRequest) — exists ANYWHERE on the platform, not
        // scoped to this business — and nothing below rechecked ownership
        // before writing to them directly: Customer::increment('balance_owed'),
        // Customer::update(['loyalty_points' => ...]), and
        // Discount::increment('uses_count') would all have silently written
        // to another business's real customer/discount record with zero
        // error, in the single highest-traffic write path in the app. Same
        // real severity as the bug #42 cluster found earlier this session,
        // just undiscovered until now because this is a service method, not
        // a controller-level validate() call the earlier grep sweep covered.
        if (!empty($data['customer_id']) && !Customer::where('id', $data['customer_id'])->where('business_id', $businessId)->exists()) {
            throw new \Exception('Invalid customer selected.');
        }
        if (!empty($data['discount_id']) && !Discount::where('id', $data['discount_id'])->where('business_id', $businessId)->exists()) {
            throw new \Exception('Invalid discount selected.');
        }

        // Loyalty redemption: the points and KSh discount both arrive from the
        // browser and were trusted as-is — a tampered request could take any
        // discount off a sale for 1 point (or for points the customer never
        // had, since the deduction below only clamps at zero). Validate the
        // points against the customer's real balance and the program's
        // minimum, and derive the discount from them server-side.
        if ((float) ($data['loyalty_points_redeemed'] ?? 0) > 0 || (float) ($data['loyalty_discount_amount'] ?? 0) > 0) {
            $pts      = (float) ($data['loyalty_points_redeemed'] ?? 0);
            $lCust    = !empty($data['customer_id'])
                ? Customer::where('id', $data['customer_id'])->where('business_id', $businessId)->first() : null;
            $lProgram = \App\Models\LoyaltyProgram::where('business_id', $businessId)->where('is_active', true)->first();
            if (!$lCust || !$lProgram || $pts <= 0) {
                throw new \Exception('Loyalty points can only be redeemed by a registered customer on an active loyalty program.');
            }
            if ($pts > (float) $lCust->loyalty_points) {
                throw new \Exception('Customer only has ' . (float) $lCust->loyalty_points . ' loyalty points.');
            }
            if ($pts < (int) ($lProgram->min_redemption_points ?? 0)) {
                throw new \Exception('Minimum redemption is ' . (int) $lProgram->min_redemption_points . ' points.');
            }
            $data['loyalty_discount_amount'] = $lProgram->pointsToKsh($pts);
        }

        // Digital Float enforcement — checked before any stock/customer
        // writes below so a blocked sale fails cleanly with no partial
        // side effects. Only cash sales draw on float (M-Pesa/bank transfer
        // are confirmed synchronously at checkout and never sit physically
        // with the cashier), only the 'cashier' role is gated (owner/manager
        // are never blocked, same exemption as the void-approval workflow),
        // and only while a shift is actually open — with none open there's
        // no [shift, user] key for float to even be tracked against.
        if (($data['payment_method'] ?? null) === 'cash') {
            $business = Business::find($businessId);
            $shiftId  = Shift::currentOpenId($businessId);
            if ($business?->enable_digital_float && $shiftId) {
                $actingUser = \App\Models\User::find($userId);
                if ($actingUser && $actingUser->hasRole('cashier')) {
                    $available = (new \App\Services\FloatService())->availableFloat($actingUser, $business, $shiftId);
                    $floatAvailable = $available;
                    if ($available <= 0) {
                        throw new \Exception('Your cash float is used up — ask a manager to record a deposit or issue a refloat before taking more cash sales.');
                    }
                }
            }
        }

        $subtotal  = 0;
        $itemsData = [];

        // Expand bundles into component items
        $expandedItems = [];
        foreach ($data['items'] as $item) {
            if (!empty($item['bundle_id'])) {
                $bundle = ProductBundle::with('items.product')
                    ->where('business_id', $businessId)
                    ->find($item['bundle_id']);
                if (!$bundle) {
                    // Was previously falling through to treat this as a
                    // plain item with no product_id, surfacing a raw
                    // "Undefined array key" PHP error to the cashier instead
                    // of a clean message.
                    throw new \Exception('Invalid bundle selected.');
                }
                if ($bundle) {
                    foreach ($bundle->items as $bItem) {
                        $expandedItems[] = [
                            'product_id' => $bItem->product_id,
                            'variant_id' => $bItem->variant_id,
                            'quantity'   => $bItem->quantity * ($item['quantity'] ?? 1),
                            'unit_price' => 0,
                            'discount'   => 0,
                            'bundle_id'  => $bundle->id,
                        ];
                    }
                    // Add a summary item at bundle price
                    $itemsData[] = [
                        'product_id'   => $bundle->items->first()->product_id,
                        'product_name' => $bundle->name . ' (Bundle)',
                        'unit_price'   => $bundle->price,
                        'buying_price' => 0,
                        'quantity'     => $item['quantity'] ?? 1,
                        'discount'     => 0,
                        'subtotal'     => $bundle->price * ($item['quantity'] ?? 1),
                        '_bundle_components' => $bundle->items->toArray(),
                        '_bundle_qty'        => $item['quantity'] ?? 1,
                        '_tax_category'      => $bundle->items->first()->product?->effectiveTaxCategory() ?? 'standard',
                    ];
                    $subtotal += $bundle->price * ($item['quantity'] ?? 1);
                    continue;
                }
            }
            $expandedItems[] = $item;
        }

        // Merge duplicate product+variant rows into one (sum quantities, keep first unit_price/discount)
        $merged = [];
        foreach ($expandedItems as $item) {
            $key = ($item['product_id'] ?? '') . '_' . ($item['variant_id'] ?? '0');
            if (isset($merged[$key])) {
                $merged[$key]['quantity'] += $item['quantity'];
                $merged[$key]['subtotal'] = ($merged[$key]['unit_price'] * $merged[$key]['quantity'])
                    * (1 - ($merged[$key]['discount'] ?? 0) / 100);
            } else {
                $merged[$key] = $item;
            }
        }
        $expandedItems = array_values($merged);

        foreach ($expandedItems as $item) {
            $product = Product::findOrFail($item['product_id']);

            if ($product->business_id !== $businessId) {
                throw new \Exception('Invalid product selected.');
            }

            // Check variant stock if applicable. variant_id was only ever
            // checked for existing at all (SaleRequest's bare exists: rule)
            // — not that it actually belongs to $product, so a crafted
            // variant_id from a completely different product (any
            // business's) would have its stock silently decremented below
            // instead of the real product/variant that was actually sold.
            $variant = null;
            if (!empty($item['variant_id'])) {
                $variant = ProductVariant::where('id', $item['variant_id'])
                    ->where('product_id', $product->id)
                    ->first();
                if (!$variant) {
                    throw new \Exception('Invalid variant selected.');
                }
                if ($variant->stock_qty < $item['quantity']) {
                    throw new \Exception("Insufficient stock for variant: {$variant->name}.");
                }
            } else {
                if ($product->stock_qty <= 0) {
                    throw new \Exception(
                        "{$product->name} is out of stock and cannot be sold."
                    );
                }

                if ($product->stock_qty < $item['quantity']) {
                    throw new \Exception(
                        "Insufficient stock for: {$product->name}. "
                        . "Available: {$product->stock_qty} {$product->unit}."
                    );
                }
            }

            $discountPct = $item['discount'] ?? 0;
            $lineTotal   = $item['unit_price'] * $item['quantity'];
            $discounted  = $lineTotal - ($lineTotal * $discountPct / 100);

            $subtotal += $discounted;

            // serial_id was only ever checked for existing at all — not
            // that it belongs to $product — so a crafted id would mark a
            // completely unrelated serial-tracked unit (any business's) as
            // "sold" and linked to this sale below.
            $serialId = null;
            if (!empty($item['serial_id'])) {
                $serialId = SerialNumber::where('id', $item['serial_id'])
                    ->where('product_id', $product->id)
                    ->where('status', 'in_stock')
                    ->value('id');
                if (!$serialId) {
                    throw new \Exception('Invalid or already-sold serial number selected.');
                }
            }

            $itemsData[] = [
                'product_id'    => $product->id,
                'product_name'  => $product->name,
                'unit_price'    => $item['unit_price'],
                'buying_price'  => $product->buying_price,
                'quantity'      => $item['quantity'],
                'discount'      => $discountPct,
                'subtotal'      => $discounted,
                '_variant_id'   => $variant?->id,
                '_serial_id'    => $serialId,
                '_tax_category' => $product->effectiveTaxCategory(),
            ];
        }

        $discountAmount      = $data['discount_amount'] ?? 0;
        $couponDiscountAmt   = $data['coupon_discount_amount'] ?? 0;
        $loyaltyPointsUsed   = (float) ($data['loyalty_points_redeemed'] ?? 0);
        $loyaltyDiscountAmt  = (float) ($data['loyalty_discount_amount'] ?? 0);
        $totalAmount         = max(0, $subtotal - $discountAmount - $couponDiscountAmt - $loyaltyDiscountAmt);

        // The early check above only blocks a cashier whose float is already
        // used up, so one big cash sale could push them far past their limit.
        // Refuse a cash sale larger than the float they have left.
        if (isset($floatAvailable) && $totalAmount > $floatAvailable) {
            throw new \Exception('This cash sale (KSh ' . number_format($totalAmount, 2) . ') is more than your remaining cash float (KSh ' . number_format($floatAvailable, 2) . ') — ask a manager to record a deposit or issue a refloat first.');
        }

        // Compute VAT (tax-inclusive: VAT = total × rate / (100 + rate)).
        // If the business has set up Tax Rules (Settings > Tax Rules), those
        // take over — multiple simultaneous rates keyed by each item's tax
        // category (standard/reduced/zero_rated/exempt), summed per item.
        // Falls back to the flat business-wide rate exactly as before for
        // every business that hasn't configured any rules — zero change in
        // behavior unless a business opts in.
        $business    = Business::find($businessId);
        $itemsForTax = array_map(fn ($i) => [
            'subtotal'     => $i['subtotal'],
            'tax_category' => $i['_tax_category'] ?? 'standard',
        ], $itemsData);
        $multiRateTax = $this->calculateTax($itemsForTax, $businessId);

        if ($multiRateTax !== null) {
            $taxAmount = $multiRateTax;
        } else {
            $vatRate   = $business?->isVatRegistered() ? (float) ($business->vat_rate ?? 16) : 0;
            $taxAmount = $vatRate > 0 ? round($totalAmount * $vatRate / (100 + $vatRate), 2) : 0;
        }

        $paidAmount = $data['paid_amount'];
        $balanceDue = $totalAmount - $paidAmount;

        $paymentStatus = match (true) {
            $paidAmount <= 0              => 'unpaid',
            $paidAmount < $totalAmount    => 'partial',
            default                       => 'paid',
        };

        $sale = Sale::create([
            'offline_id'             => $data['offline_id'] ?? null,
            'business_id'            => $businessId,
            'shift_id'               => Shift::currentOpenId($businessId),
            'customer_id'            => $data['customer_id'] ?? null,
            'user_id'                => $userId,
            'invoice_number'         => Sale::generateInvoiceNumber($businessId),
            'subtotal'               => $subtotal,
            'discount_amount'        => $discountAmount,
            'coupon_discount_amount' => $couponDiscountAmt,
            'discount_id'            => $data['discount_id'] ?? null,
            'tax_amount'             => $taxAmount,
            // invoice.blade.php's "of which VAT" line, EtimsService (KRA
            // eTIMS), and cancelSale()'s credit-note creation all read
            // vat_amount specifically, not tax_amount — this method never
            // wrote to vat_amount at all (it defaults to 0 in the DB, not
            // null, so invoice.blade.php's `?? fallback` never triggered),
            // meaning every POS invoice has likely displayed "of which VAT:
            // KSh 0.00" for the entire life of this feature, on every
            // VAT-registered business, despite tax_amount correctly holding
            // the real value (and VAT-return reports, which read tax_amount
            // directly, being unaffected). Setting both keeps every existing
            // reader correct without touching any other logic here.
            'vat_amount'             => $taxAmount,
            'total_amount'           => $totalAmount,
            'paid_amount'            => $paidAmount,
            'balance_due'            => max(0, $balanceDue),
            'payment_method'         => $data['payment_method'],
            'mpesa_reference'        => $data['mpesa_reference'] ?? null,
            'payment_status'         => $paymentStatus,
            'sale_status'            => 'completed',
            'notes'                  => $data['notes'] ?? null,
        ]);

        foreach ($itemsData as $itemData) {
            $variantId = $itemData['_variant_id'] ?? null;
            $serialId  = $itemData['_serial_id'] ?? null;
            // A bundle produces this ONE summary row (bundle price, tagged
            // with its first component's product_id purely so the receipt
            // shows "Bundle Name x1" instead of a confusing $0 component
            // list) PLUS the real per-component rows, each with their own
            // actual product_id/quantity, elsewhere in $itemsData. This
            // summary row is display-only — it was also being decremented
            // below, using the BUNDLE quantity rather than that
            // component's own per-bundle quantity, silently over-
            // decrementing the bundle's first component on every sale.
            $isBundleSummary = isset($itemData['_bundle_components']);
            unset($itemData['_variant_id'], $itemData['_serial_id'],
                  $itemData['_bundle_components'], $itemData['_bundle_qty'],
                  $itemData['_tax_category']);

            SaleItem::create(array_merge($itemData, [
                'sale_id'           => $sale->id,
                'variant_id'        => $variantId,
                'is_bundle_summary' => $isBundleSummary,
            ]));

            // Decrement stock (skip the bundle summary line — see above)
            if (!$isBundleSummary) {
                if ($variantId) {
                    ProductVariant::where('id', $variantId)
                        ->decrement('stock_qty', $itemData['quantity']);
                } else {
                    Product::where('id', $itemData['product_id'])
                        ->decrement('stock_qty', $itemData['quantity']);
                }
                \App\Models\ProductBatch::consume((int) $itemData['product_id'], $variantId ?: null, (float) $itemData['quantity']);
            }

            // Mark serial as sold
            if ($serialId) {
                SerialNumber::where('id', $serialId)->update([
                    'status'   => 'sold',
                    'sale_id'  => $sale->id,
                    'sold_date'=> now()->toDateString(),
                ]);
            }
        }

        // Increment discount usage
        if (!empty($data['discount_id'])) {
            Discount::where('id', $data['discount_id'])->increment('uses_count');
        }

        if (($data['customer_id'] ?? null) && $balanceDue > 0) {
            Customer::where('id', $data['customer_id'])
                ->increment('balance_owed', $balanceDue);
        }

        // Deduct redeemed loyalty points from customer balance
        if ($loyaltyPointsUsed > 0 && ($data['customer_id'] ?? null)) {
            $customer   = Customer::find($data['customer_id']);
            $newBalance = max(0, (float) $customer->loyalty_points - $loyaltyPointsUsed);
            $customer->update(['loyalty_points' => $newBalance]);
            LoyaltyTransaction::create([
                'business_id'   => $businessId,
                'customer_id'   => $customer->id,
                'user_id'       => $userId,
                'sale_id'       => $sale->id,
                'type'          => 'redeem',
                'points'        => -$loyaltyPointsUsed,
                'balance_after' => $newBalance,
                'description'   => "Redeemed on sale #{$sale->invoice_number} (KSh " . number_format($loyaltyDiscountAmt, 2) . " off)",
            ]);
        }

        AuditLog::record('sale.created', $sale, [
            'invoice' => $sale->invoice_number,
            'total'   => $sale->total_amount,
            'method'  => $sale->payment_method,
            'status'  => $sale->payment_status,
        ]);

        return $sale;
    }

    /**
     * New multi-rate tax engine — purely additive. Returns null (meaning
     * "use the old flat business-VAT-rate calculation instead") whenever
     * the business has no enabled Tax Rules configured, so every business
     * that hasn't opted into this feature keeps computing tax exactly as
     * it always has.
     *
     * Only priority-1, inclusive rules are applied — compounding
     * (priority 2+) and exclusive add-on charges (e.g. a Service Charge
     * that increases the total rather than being extracted from it) are
     * stored on the rule for forward compatibility but not yet computed
     * here; see TaxRule/tax_rules migration comments.
     *
     * @param array $itemsForTax [['subtotal' => float, 'tax_category' => string], ...]
     */
    private function calculateTax(array $itemsForTax, int $businessId): ?float
    {
        // Tax rules are VAT rates (standard / reduced / zero-rated / exempt). A
        // business that is not VAT-registered may not charge VAT, but its
        // saved rules were still applied — its sales and invoices showed
        // "of which VAT 16%" and the VAT return counted tax it never owed.
        // Returning null falls back to the flat rate, which is 0 for a
        // non-registered business.
        if (! Business::find($businessId)?->isVatRegistered()) {
            return null;
        }

        $rules = TaxRule::forBusiness($businessId)
            ->enabled()
            ->where('priority', 1)
            ->where('inclusive', true)
            ->get();

        if ($rules->isEmpty()) {
            return null;
        }

        $rulesByCategory = $rules->groupBy('tax_category');
        $tax = 0.0;

        foreach ($itemsForTax as $item) {
            $category      = $item['tax_category'] ?? 'standard';
            $categoryRules = $rulesByCategory->get($category, collect());
            foreach ($categoryRules as $rule) {
                $rate = (float) $rule->rate;
                if ($rate <= 0) continue;
                $tax += round($item['subtotal'] * $rate / (100 + $rate), 2);
            }
        }

        return round($tax, 2);
    }

    /**
     * Create a matching Sale (+ SaleItems) for an OnlineOrder once it has
     * been marked paid. Before this, a checkout through the public online
     * shop only ever produced an OnlineOrder — the Sales list, dashboard
     * revenue, and every report (P&L, gross margin, staff performance,
     * etc.) all read exclusively from the `sales` table, so a paid online
     * order was completely invisible everywhere except the dedicated
     * /online-orders admin page.
     *
     * Stock is deliberately NOT touched here — both callers (the M-Pesa
     * webhook and the manual "mark paid" action) already decrement it
     * themselves, using the same variant-vs-product rule this class's own
     * createSale() uses for POS sales. Doing it again here would double-
     * decrement.
     *
     * Idempotent via $order->sale_id: a duplicate M-Pesa webhook delivery
     * (Safaricom does not guarantee exactly-once delivery) calling this a
     * second time for the same order returns the existing Sale instead of
     * creating a second one.
     */
    public function createSaleFromOnlineOrder(OnlineOrder $order): ?Sale
    {
        if ($order->sale_id) {
            return $order->sale;
        }

        // Online orders have no acting staff member (a public customer
        // placed it) — attribute it to the business owner, same fallback
        // pattern already used for other business-wide system actions
        // (SendLowStockAlerts, SendPaymentReminders, MpesaB2CController).
        $userId = $order->business->users()->wherePivot('role', 'owner')->first()?->id
            ?? $order->business->users()->first()?->id;

        if (!$userId) {
            // sales.user_id is NOT NULL — bail out cleanly rather than
            // crash the payment-confirmation flow for the (extremely
            // unlikely) case of a business with zero user accounts.
            // Bumped from warning() — invisible in production, whose
            // LOG_LEVEL only records error and above (see ReceiptService.php).
            // This means a customer paid for an online order that never got
            // a Sale record at all — a real financial-record gap.
            \Illuminate\Support\Facades\Log::error('Could not create Sale for online order — business has no user', ['order' => $order->reference]);
            return null;
        }

        $paymentMethod = in_array($order->payment_method, ['cash', 'mpesa', 'bank_transfer', 'credit'], true)
            ? $order->payment_method
            : 'mpesa';

        // VAT was previously never computed for online-shop sales at all —
        // POS sales correctly extract it from the (tax-inclusive) total via
        // this exact formula in createSale() above, but this method left
        // tax_amount at 0 regardless of the business's VAT registration,
        // meaning every online-shop sale silently undercounted VAT-return
        // and P&L revenue. The delivery fee is treated as part of the same
        // taxable total (a bundled charge), not a separate untaxed line.
        $business    = $order->business;
        $itemsForTax = $order->items->map(fn ($item) => [
            'subtotal'     => (float) $item->total,
            'tax_category' => Product::find($item->product_id)?->effectiveTaxCategory() ?? 'standard',
        ])->all();
        $multiRateTax = $this->calculateTax($itemsForTax, $order->business_id);

        if ($multiRateTax !== null) {
            $taxAmount = $multiRateTax;
        } else {
            $vatRate   = $business?->isVatRegistered() ? (float) ($business->vat_rate ?? 16) : 0;
            $taxAmount = $vatRate > 0 ? round($order->total * $vatRate / (100 + $vatRate), 2) : 0;
        }

        $sale = Sale::create([
            'business_id'    => $order->business_id,
            'shift_id'       => Shift::currentOpenId($order->business_id),
            'user_id'        => $userId,
            'invoice_number' => Sale::generateInvoiceNumber($order->business_id),
            'subtotal'       => $order->subtotal,
            'delivery_fee'   => $order->delivery_fee ?? 0,
            'tax_amount'     => $taxAmount,
            // invoice.blade.php actually displays VAT from vat_amount, a
            // separate column tax_amount doesn't automatically populate
            // (a pre-existing gap in SaleService::createSale() too, flagged
            // separately — see task_2dc0a70e). Set both here so THIS path's
            // invoices are correct regardless of that wider issue.
            'vat_amount'     => $taxAmount,
            'total_amount'   => $order->total,
            'paid_amount'    => $order->total,
            'balance_due'    => 0,
            'payment_method' => $paymentMethod,
            'payment_status' => 'paid',
            'sale_status'    => 'completed',
            'notes'          => "Online order {$order->reference} — {$order->customer_name}"
                . ($order->customer_phone ? " ({$order->customer_phone})" : ''),
        ]);

        foreach ($order->items as $item) {
            $product = Product::find($item->product_id);
            SaleItem::create([
                'sale_id'           => $sale->id,
                'product_id'        => $item->product_id,
                'variant_id'        => $item->variant_id,
                'product_name'      => $item->product_name,
                'unit_price'        => $item->unit_price,
                'buying_price'      => $product->buying_price ?? 0,
                'quantity'          => $item->quantity,
                'discount'          => 0,
                'subtotal'          => $item->total,
                // Without this, the Sale generated for a paid online order
                // would lose the bundle-summary marker its OnlineOrderItem
                // already carries — and cancelSale()'s restock guard (see
                // above) checks THIS column, not the original order's. Left
                // unset, cancelling that Sale later would reintroduce the
                // exact double-restore bug this whole fix was for.
                'is_bundle_summary' => $item->is_bundle_summary,
            ]);
        }

        $order->update(['sale_id' => $sale->id]);

        AuditLog::record('sale.created', $sale, [
            'invoice'      => $sale->invoice_number,
            'total'        => $sale->total_amount,
            'method'       => $sale->payment_method,
            'status'       => $sale->payment_status,
            'online_order' => $order->reference,
        ]);

        return $sale;
    }

    /**
     * Cancel a sale — restores stock and adjusts customer balance.
     *
     * @throws \Exception
     */
    public function cancelSale(Sale $sale): void
    {
        if ($sale->sale_status === 'cancelled') {
            throw new \Exception('Sale is already cancelled.');
        }

        // This whole method previously ran as a sequence of independent
        // writes with no transaction — the credit-note-item bug above meant
        // a real crash partway through routinely happened here, leaving
        // stock already restored and the customer's balance already
        // adjusted while the sale itself was NEVER marked cancelled and no
        // credit note or refund record existed. A retry after that kind of
        // failure would double-restore stock and double-adjust the balance.
        // Wrapping in a transaction makes any future failure at any step
        // roll back cleanly instead of leaving that kind of partial state.
        DB::transaction(function () use ($sale) {
            $this->doCancelSale($sale);
        });

        // Tell KRA the sale it was already given has been reversed.
        try {
            $sale->refresh();
            \App\Models\EtimsRefund::queue('sale_cancel', $sale->id, $sale->business, $sale, null, (float) $sale->total_amount);
        } catch (\Throwable $e) {
            \Log::warning('eTIMS refund queue failed for cancelled sale ' . $sale->id . ': ' . $e->getMessage());
        }
    }

    private function doCancelSale(Sale $sale): void
    {
        // 1. Restore stock for every line item — to the variant that was actually
        //    decremented at sale time, falling back to the parent product when
        //    no variant was involved. Skip the bundle summary line (display-only,
        //    tagged with its first component's product_id) — its stock was never
        //    actually decremented at sale time (see createSale()), so restoring
        //    it here would over-credit that same component a second time.
        foreach ($sale->items as $item) {
            if ($item->is_bundle_summary) {
                continue;
            }
            if ($item->variant_id) {
                ProductVariant::where('id', $item->variant_id)
                    ->increment('stock_qty', $item->quantity);
            } else {
                Product::where('id', $item->product_id)
                    ->increment('stock_qty', $item->quantity);
            }
            \App\Models\ProductBatch::restore((int) $item->product_id, $item->variant_id ?: null, (float) $item->quantity);
        }

        // 1b. Release any serial-numbered units sold on this sale back to stock.
        SerialNumber::where('sale_id', $sale->id)
            ->update([
                'status'    => 'in_stock',
                'sale_id'   => null,
                'sold_date' => null,
            ]);

        // 2. Clear customer credit balance
        if ($sale->customer_id && $sale->balance_due > 0) {
            Customer::where('id', $sale->customer_id)
                ->decrement('balance_owed', $sale->balance_due);
        }

        // 3. Reverse loyalty points earned on this sale
        $loyaltyTx = LoyaltyTransaction::where('sale_id', $sale->id)
            ->where('type', 'earn')
            ->first();
        if ($loyaltyTx && $sale->customer_id) {
            $customer    = Customer::find($sale->customer_id);
            $newBalance  = max(0, (float) $customer->loyalty_points - (float) $loyaltyTx->points);
            $customer->update(['loyalty_points' => $newBalance]);
            LoyaltyTransaction::create([
                'business_id'   => $sale->business_id,
                'customer_id'   => $customer->id,
                'user_id'       => Auth::id() ?? $sale->user_id,
                'sale_id'       => $sale->id,
                'type'          => 'adjust',
                'points'        => -$loyaltyTx->points,
                'balance_after' => $newBalance,
                'description'   => "Points reversed — sale #{$sale->invoice_number} cancelled",
            ]);
        }

        // 3b. Give back any points the customer spent on this sale — only the
        //     earned points were reversed above, so a cancelled sale used to
        //     cost the customer their redeemed points for nothing.
        $redeemedPts = abs((float) LoyaltyTransaction::where('sale_id', $sale->id)
            ->where('type', 'redeem')->sum('points'));
        if ($redeemedPts > 0 && $sale->customer_id) {
            $customer   = Customer::find($sale->customer_id);
            $newBalance = (float) $customer->loyalty_points + $redeemedPts;
            $customer->update(['loyalty_points' => $newBalance]);
            LoyaltyTransaction::create([
                'business_id'   => $sale->business_id,
                'customer_id'   => $customer->id,
                'user_id'       => Auth::id() ?? $sale->user_id,
                'sale_id'       => $sale->id,
                'type'          => 'adjust',
                'points'        => $redeemedPts,
                'balance_after' => $newBalance,
                'description'   => "Redeemed points returned — sale #{$sale->invoice_number} cancelled",
            ]);
        }

        // 3c. Give back store credit the customer spent on this sale. It was
        //     deducted (and logged) when applied, but cancelling never returned
        //     it, so a cancelled sale silently cost the customer their credit.
        //     The refund record below then only counts the rest as money back,
        //     otherwise that part would be refunded twice.
        $creditReturned = 0.0;
        if ($sale->customer_id) {
            $applied = abs((float) \App\Models\CustomerCredit::where('business_id', $sale->business_id)
                ->where('customer_id', $sale->customer_id)
                ->where('reference', $sale->invoice_number)
                ->where('notes', 'like', 'Store credit applied to sale%')
                ->where('amount', '<', 0)
                ->sum('amount'));
            $alreadyBack = (float) \App\Models\CustomerCredit::where('business_id', $sale->business_id)
                ->where('customer_id', $sale->customer_id)
                ->where('reference', $sale->invoice_number)
                ->where('notes', 'like', 'Store credit returned%')
                ->sum('amount');
            $creditReturned = round(max(0, min($applied - $alreadyBack, (float) $sale->paid_amount)), 2);
            if ($creditReturned > 0) {
                $cust = Customer::where('id', $sale->customer_id)->lockForUpdate()->first();
                $cust->increment('credit_balance', $creditReturned);
                \App\Models\CustomerCredit::create([
                    'business_id'   => $sale->business_id,
                    'customer_id'   => $sale->customer_id,
                    'user_id'       => Auth::id() ?? $sale->user_id,
                    'type'          => 'adjustment',
                    'amount'        => $creditReturned,
                    'balance_after' => (float) $cust->fresh()->credit_balance,
                    'reference'     => $sale->invoice_number,
                    'notes'         => 'Store credit returned — sale ' . $sale->invoice_number . ' cancelled',
                ]);
            }
        }

        // 4. Auto-generate a credit note for the paid amount (if any was collected)
        $paidAmount = (float) $sale->paid_amount;
        if ($sale->customer_id && $paidAmount > 0) {
            $cn = CreditNote::create([
                'business_id' => $sale->business_id,
                'customer_id' => $sale->customer_id,
                'user_id'     => Auth::id() ?? $sale->user_id,
                'number'      => CreditNote::nextNumber($sale->business_id),
                'reason'      => "Sale #{$sale->invoice_number} cancelled",
                'status'      => 'issued',
                'subtotal'    => $sale->subtotal,
                'vat_amount'  => $sale->vat_amount ?? 0,
                'total'       => $paidAmount,
                'issued_at'   => now(),
            ]);
            foreach ($sale->items as $item) {
                CreditNoteItem::create([
                    'credit_note_id' => $cn->id,
                    'product_id'     => $item->product_id,
                    'description'    => $item->product_name ?? $item->product->name ?? 'Item',
                    'quantity'       => $item->quantity,
                    'unit_price'     => $item->unit_price,
                    // Was: 'subtotal' — credit_note_items has no such column
                    // (it's 'total', NOT NULL with no default, unlike the
                    // sibling SaleReturnItem::create() a few lines below
                    // which really does use 'subtotal'). Every cancellation
                    // of a paid sale with a customer attached threw a fatal
                    // "Field 'total' doesn't have a default value" here —
                    // confirmed via direct invocation, not just reading the
                    // code — meaning sale cancellation has been completely
                    // broken for the single most common case.
                    'total'          => $item->subtotal,
                ]);
            }
        }

        // 5. Record a refund entry (only when money was actually collected)
        if ($paidAmount > 0) {
            $refundMethod = in_array($sale->payment_method, ['cash', 'mpesa', 'store_credit'])
                ? $sale->payment_method
                : 'cash';
            // Paid entirely with store credit: it went back as store credit.
            if ($creditReturned > 0 && $creditReturned + 0.005 >= $paidAmount) {
                $refundMethod = 'store_credit';
            }
            $saleReturn = SaleReturn::create([
                'business_id'   => $sale->business_id,
                'sale_id'       => $sale->id,
                'user_id'       => Auth::id() ?? $sale->user_id,
                'customer_id'   => $sale->customer_id,
                'return_number' => 'RTN-TEMP',
                'total_refund'  => $paidAmount,
                // Must match the sale_returns.stock_action enum ('restock'|'writeoff') —
                // 'restocked' silently violated it under MySQL strict mode, which threw
                // a raw SQL exception and blocked cancelling ANY sale with money collected.
                'stock_action'  => 'restock',
                'refund_method' => $refundMethod,
                'reason'        => "Sale #{$sale->invoice_number} cancelled — full refund"
                    . ($creditReturned > 0 ? ' (KSh ' . number_format($creditReturned, 2) . ' returned as store credit, the rest as money)' : ''),
            ]);
            $saleReturn->update([
                'return_number' => 'RTN-' . str_pad($saleReturn->id, 5, '0', STR_PAD_LEFT),
            ]);
            foreach ($sale->items as $item) {
                SaleReturnItem::create([
                    'sale_return_id'    => $saleReturn->id,
                    'sale_item_id'      => $item->id,
                    'product_id'        => $item->product_id,
                    'variant_id'        => $item->variant_id,
                    'product_name'      => $item->product_name ?? '',
                    'quantity_returned' => $item->quantity,
                    'unit_price'        => $item->unit_price,
                    'subtotal'          => $item->subtotal,
                ]);
            }
        }

        $sale->update([
            'sale_status'    => 'cancelled',
            'payment_status' => 'unpaid',
        ]);

        // 6. Keep a linked online order in sync — this Sale can be cancelled
        // either from here directly (Sales list) or via
        // OnlineOrderController::cancel() (which cancels the Sale THROUGH
        // this same method). Without this, cancelling the Sale directly left
        // its OnlineOrder stuck showing 'paid' forever (misleading — staff
        // could think it still needs fulfilling), and a limited-use coupon
        // on that order stayed permanently burned. It also closes a
        // double-restock hole: OnlineOrderController::cancel() only restocks
        // items itself when the linked Sale is NOT already cancelled — if
        // the order's status weren't updated here too, a later attempt to
        // cancel it from the online-orders screen would restock the same
        // items a second time.
        $onlineOrder = OnlineOrder::where('sale_id', $sale->id)->first();
        if ($onlineOrder && $onlineOrder->status !== 'cancelled') {
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
            $onlineOrder->update(['status' => 'cancelled']);
        }

        AuditLog::record('sale.cancelled', $sale, [
            'invoice'       => $sale->invoice_number,
            'refund_amount' => $paidAmount,
            'credit_note'   => isset($cn) ? $cn->number : null,
            'refund_record' => isset($saleReturn) ? $saleReturn->return_number : null,
        ]);
    }
}
