<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\SaleItem;
use App\Models\TableOrder;
use App\Models\TableOrderItem;
use App\Models\TableOrderRequest;
use App\Support\ShopQr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TableController extends Controller
{
    private function business()
    {
        return Auth::user()->currentBusiness();
    }

    private function businessId(): int
    {
        return $this->business()->id;
    }

    // ── Floor Plan ─────────────────────────────────────────────────────────────

    public function floor()
    {
        $tables = RestaurantTable::forBusiness($this->businessId())
            ->with(['currentOrder.items'])
            ->orderBy('sort_order')
            ->orderBy('number')
            ->get();

        return view('tables.floor', compact('tables'));
    }

    // ── Manage Tables ──────────────────────────────────────────────────────────

    public function manage()
    {
        $tables = RestaurantTable::forBusiness($this->businessId())
            ->orderBy('sort_order')->orderBy('number')->get();
        return view('tables.manage', compact('tables'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'number'     => 'required|string|max:20',
            'name'       => 'nullable|string|max:100',
            'capacity'   => 'required|integer|min:1',
            'sort_order' => 'nullable|integer',
        ]);

        RestaurantTable::create([
            'business_id' => $this->businessId(),
            'number'      => $request->number,
            'name'        => $request->name,
            'capacity'    => $request->capacity,
            'sort_order'  => $request->sort_order ?? 0,
        ]);

        return back()->with('success', 'Table added.');
    }

    public function update(Request $request, RestaurantTable $table)
    {
        abort_if($table->business_id !== $this->businessId(), 403);
        $request->validate([
            'number'     => 'required|string|max:20',
            'name'       => 'nullable|string|max:100',
            'capacity'   => 'required|integer|min:1',
            'sort_order' => 'nullable|integer',
        ]);

        $table->update([
            'number'     => $request->number,
            'name'       => $request->name,
            'capacity'   => $request->capacity,
            'sort_order' => $request->sort_order ?? $table->sort_order,
        ]);

        return back()->with('success', 'Table updated.');
    }

    public function destroy(RestaurantTable $table)
    {
        abort_if($table->business_id !== $this->businessId(), 403);
        $table->delete();
        return back()->with('success', 'Table deleted.');
    }

    // ── Orders ──────────────────────────────────────────────────────────────────

    public function openOrder(RestaurantTable $table, Request $request)
    {
        abort_if($table->business_id !== $this->businessId(), 403);

        if ($table->status === 'occupied') {
            return back()->with('error', 'Table already has an open order.');
        }

        DB::transaction(function () use ($table, $request) {
            $order = TableOrder::create([
                'business_id'        => $this->businessId(),
                'restaurant_table_id'=> $table->id,
                'user_id'            => Auth::id(),
                'customer_id'        => $request->customer_id ?? null,
                'status'             => 'open',
                'opened_at'          => now(),
                'service_charge_percent' => (float) ($this->business()->service_charge_percent ?? 0),
            ]);

            $table->update([
                'status'           => 'occupied',
                'current_order_id' => $order->id,
            ]);

            $this->newOrderId = $order->id;
        });

        return redirect()->route('tables.orders.show', $this->newOrderId)
            ->with('success', 'Order opened.');
    }

    public function showOrder(TableOrder $order)
    {
        abort_if($order->business_id !== $this->businessId(), 403);
        $order->load(['table', 'items.product', 'customer', 'user']);
        $products = Product::forBusiness($this->businessId())->active()->orderBy('name')->get();
        // Split bills: which sale settled each already-paid item, and the free
        // tables an order can be moved to.
        $paidSales = Sale::withoutGlobalScopes()->whereIn('id', $order->items->pluck('sale_id')->filter()->unique())
            ->pluck('invoice_number', 'id');
        $freeTables = RestaurantTable::forBusiness($this->businessId())
            ->where('status', 'available')->orderBy('sort_order')->orderBy('number')->get();
        // Merge candidates: other occupied tables with an order still open/billed (not this one).
        $mergeableTables = RestaurantTable::forBusiness($this->businessId())
            ->where('status', 'occupied')->where('id', '!=', $order->restaurant_table_id)
            ->whereHas('currentOrder', fn ($q) => $q->whereIn('status', ['open', 'billed']))
            ->with('currentOrder')->orderBy('sort_order')->orderBy('number')->get();
        $pendingRequests = TableOrderRequest::where('restaurant_table_id', $order->restaurant_table_id)
            ->where('status', 'pending')->orderBy('id')->get();
        return view('tables.order', compact('order', 'products', 'paidSales', 'freeTables', 'mergeableTables', 'pendingRequests'));
    }

    public function addItem(TableOrder $order, Request $request)
    {
        abort_if($order->business_id !== $this->businessId(), 403);
        abort_if(! in_array($order->status, ['open', 'billed'], true), 422, 'This order is already closed.');
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity'   => 'required|numeric|min:0.01',
            'notes'      => 'nullable|string|max:255',
        ]);

        // Was Product::findOrFail() with no business check at all — a
        // crafted product_id from a different business could get added to
        // this table order, using that business's price data and later
        // flowing into a Sale/SaleItem pointing at a product this business
        // doesn't own.
        $product = Product::where('business_id', $this->businessId())->findOrFail($request->product_id);

        DB::transaction(function () use ($order, $request, $product) {
            TableOrderItem::create([
                'table_order_id' => $order->id,
                'product_id'     => $product->id,
                // product_name is a required (NOT NULL, no default) column
                // that was never being set here at all — every single
                // "Add Item" on a table order has been throwing a fatal DB
                // error, confirmed via direct invocation.
                'product_name'   => $product->name,
                'quantity'       => $request->quantity,
                'unit_price'     => $product->selling_price,
                'total'          => $product->selling_price * $request->quantity,
                'notes'          => $request->notes,
            ]);
            $order->recalculate();

            // Adding to a bill that was already printed makes that printout wrong.
            if ($order->status === 'billed') {
                $order->update(['status' => 'open', 'billed_at' => null]);
            }
        });

        return back()->with('success', 'Item added.');
    }

    // A flat KSh amount off one line — a manager comping a dish, a happy-hour
    // price. Never lets the discount exceed the line's own pre-discount value.
    public function setItemDiscount(TableOrder $order, TableOrderItem $item, Request $request)
    {
        abort_if($order->business_id !== $this->businessId(), 403);
        abort_if($item->table_order_id !== $order->id, 403);
        abort_if(! in_array($order->status, ['open', 'billed'], true), 422, 'This order is already closed.');
        abort_if($item->sale_id, 422, 'That item has already been paid for.');

        $gross = round((float) $item->unit_price * (float) $item->quantity, 2);
        $request->validate(['discount' => 'required|numeric|min:0|max:' . $gross]);

        DB::transaction(function () use ($order, $item, $request, $gross) {
            $discount = round((float) $request->discount, 2);
            $item->update(['discount' => $discount, 'total' => round($gross - $discount, 2)]);
            $order->recalculate();
            if ($order->status === 'billed') {
                $order->update(['status' => 'open', 'billed_at' => null]);
            }
        });

        return back()->with('success', 'Discount applied.');
    }

    public function removeItem(TableOrder $order, TableOrderItem $item)
    {
        abort_if($order->business_id !== $this->businessId(), 403);
        abort_if($item->table_order_id !== $order->id, 403);
        abort_if(! in_array($order->status, ['open', 'billed'], true), 422, 'This order is already closed.');
        // An item that a split payment already settled is part of a finished sale.
        abort_if($item->sale_id, 422, 'That item has already been paid for.');

        DB::transaction(function () use ($order, $item) {
            $item->delete();
            $order->recalculate();
            if ($order->status === 'billed') {
                $order->update(['status' => 'open', 'billed_at' => null]);
            }
        });

        return back()->with('success', 'Item removed.');
    }

    // The "Clear Order" button on tables/order.blade.php has never had a
    // route or controller method behind it at all — added alongside fixing
    // that view's broken route names (tables.order.* never existed; the
    // real routes are tables.orders.*).
    public function clearOrder(TableOrder $order)
    {
        abort_if($order->business_id !== $this->businessId(), 403);
        abort_if(! in_array($order->status, ['open', 'billed'], true), 422, 'This order is already closed.');

        DB::transaction(function () use ($order) {
            $order->items()->delete();
            $order->recalculate();
            if ($order->status === 'billed') {
                $order->update(['status' => 'open', 'billed_at' => null]);
            }
        });

        return back()->with('success', 'Order cleared.');
    }

    // ── Bill (printed BEFORE the customer pays) ─────────────────────────────────
    // The route existed but nothing ever called it, and it printed nothing: a
    // customer could not be shown what they owed. Now it marks the order as
    // billed and opens an 80 mm bill; adding or removing an item afterwards
    // re-opens the order so a stale bill is never the one that gets paid.
    public function bill(TableOrder $order)
    {
        abort_if($order->business_id !== $this->businessId(), 403);
        abort_if(! in_array($order->status, ['open', 'billed'], true), 422, 'This order is already closed.');

        if (! $order->outstandingItems()->exists()) {
            return back()->with('error', 'Add items before printing a bill.');
        }

        $order->update(['status' => 'billed', 'billed_at' => $order->billed_at ?? now()]);

        return redirect()->route('tables.orders.bill.print', $order);
    }

    public function printBill(TableOrder $order)
    {
        abort_if($order->business_id !== $this->businessId(), 403);
        $order->load(['table', 'user', 'business']);
        $order->setRelation('items', $order->outstandingItems()->orderBy('id')->get());

        return view('tables.bill', compact('order'));
    }

    // ── Kitchen order slip ───────────────────────────────────────────────────────
    // Prints only the items the kitchen has not been sent yet, then remembers
    // that they were sent, so a second press never cooks the same dish twice.
    public function sendToKitchen(TableOrder $order)
    {
        abort_if($order->business_id !== $this->businessId(), 403);
        abort_if(! in_array($order->status, ['open', 'billed'], true), 422, 'This order is already closed.');

        // The slip lists items by id, not by time: two sends in the same second
        // (a busy rush) must never pull each other's items onto the wrong slip.
        $ids = $order->items()
            ->where('status', '!=', 'cancelled')
            ->whereNull('sent_to_kitchen_at')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return back()->with('error', 'Nothing new to send — every item has already gone to the kitchen.');
        }

        $order->items()->whereIn('id', $ids)->update(['sent_to_kitchen_at' => now()]);

        return redirect()->route('tables.orders.kitchen.print', [$order, 'items' => $ids->implode(',')]);
    }

    public function printKitchen(TableOrder $order, Request $request)
    {
        abort_if($order->business_id !== $this->businessId(), 403);
        $order->load(['table', 'user', 'business']);

        $items = $order->items()->where('status', '!=', 'cancelled');
        if ($request->filled('items')) {
            $ids = array_filter(array_map('intval', explode(',', (string) $request->items)));
            $items->whereIn('id', $ids);
        }
        $items = $items->orderBy('id')->get();

        return view('tables.kitchen', ['order' => $order, 'items' => $items, 'reprint' => ! $request->filled('items')]);
    }

    // ── Service charge ───────────────────────────────────────────────────────────
    public function setServiceCharge(TableOrder $order, Request $request)
    {
        abort_if($order->business_id !== $this->businessId(), 403);
        abort_if(! in_array($order->status, ['open', 'billed'], true), 422, 'This order is already closed.');
        $request->validate(['service_charge_percent' => 'required|numeric|min:0|max:30']);

        DB::transaction(function () use ($order, $request) {
            $order->update(['service_charge_percent' => round((float) $request->service_charge_percent, 2)]);
            $order->recalculate();
            // A bill that was already printed no longer shows the right total.
            if ($order->status === 'billed') {
                $order->update(['status' => 'open', 'billed_at' => null]);
            }
        });

        return back()->with('success', 'Service charge updated.');
    }

    // The business-wide default, applied to every NEW order.
    public function setDefaultServiceCharge(Request $request)
    {
        $request->validate(['service_charge_percent' => 'required|numeric|min:0|max:30']);
        $this->business()->update(['service_charge_percent' => round((float) $request->service_charge_percent, 2)]);

        return back()->with('success', 'Default service charge saved. It applies to new orders.');
    }

    // ── Move an order to another table ──────────────────────────────────────────
    public function moveTable(TableOrder $order, Request $request)
    {
        abort_if($order->business_id !== $this->businessId(), 403);
        abort_if(! in_array($order->status, ['open', 'billed'], true), 422, 'This order is already closed.');
        $request->validate(['to_table_id' => 'required|integer']);

        $to = RestaurantTable::where('business_id', $this->businessId())->findOrFail($request->to_table_id);
        if ($to->id === $order->restaurant_table_id) {
            return back()->with('error', 'The order is already on that table.');
        }
        if ($to->status !== 'available') {
            return back()->with('error', 'That table is not free.');
        }

        try {
            DB::transaction(function () use ($order, $to) {
                // Lock both sides: two people moving orders onto the same free table.
                $to = RestaurantTable::whereKey($to->id)->lockForUpdate()->first();
                if ($to->status !== 'available') {
                    throw new \RuntimeException('That table is not free.');
                }
                $from = RestaurantTable::whereKey($order->restaurant_table_id)->lockForUpdate()->first();

                $order->update(['restaurant_table_id' => $to->id]);
                $to->update(['status' => 'occupied', 'current_order_id' => $order->id]);
                $from?->update(['status' => 'available', 'current_order_id' => null]);
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('tables.orders.show', $order)
            ->with('success', 'Order moved to table ' . ($to->name ?: $to->number) . '.');
    }

    // ── Merge tables ─────────────────────────────────────────────────────────────
    // A party that outgrew its table: fold another occupied table's order into
    // this one. The source table's own order is kept (not deleted) for the
    // history/receipt trail, marked 'merged' and pointing at where its items went.
    public function mergeTable(TableOrder $order, Request $request)
    {
        abort_if($order->business_id !== $this->businessId(), 403);
        abort_if(! in_array($order->status, ['open', 'billed'], true), 422, 'This order is already closed.');
        $request->validate(['from_table_id' => 'required|integer']);

        $fromTable = RestaurantTable::where('business_id', $this->businessId())->findOrFail($request->from_table_id);
        if ($fromTable->id === $order->restaurant_table_id) {
            return back()->with('error', 'That is already this table.');
        }

        try {
            DB::transaction(function () use ($order, $fromTable) {
                $order = TableOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
                $fromTable = RestaurantTable::whereKey($fromTable->id)->lockForUpdate()->firstOrFail();

                $fromOrder = $fromTable->current_order_id
                    ? TableOrder::whereKey($fromTable->current_order_id)->lockForUpdate()->first()
                    : null;
                if (! $fromOrder || ! in_array($fromOrder->status, ['open', 'billed'], true)) {
                    throw new \RuntimeException('That table has no open order to merge.');
                }
                if (! $fromOrder->outstandingItems()->exists()) {
                    throw new \RuntimeException('That table\'s order has no items to bring over.');
                }

                // Move every still-outstanding item across; anything already paid on the
                // source order (a split payment) stays there as part of its own finished sale.
                $fromOrder->outstandingItems()->update(['table_order_id' => $order->id]);

                $fromOrder->update([
                    'status'         => 'merged',
                    'merged_into_id' => $order->id,
                    'closed_at'      => now(),
                ]);
                $fromOrder->recalculate();

                $fromTable->update(['status' => 'available', 'current_order_id' => null]);

                $order->update(['notes' => trim(($order->notes ? $order->notes . "\n" : '') . 'Merged in table ' . ($fromTable->name ?: $fromTable->number) . '.')]);
                $order->recalculate();
                if ($order->status === 'billed') {
                    $order->update(['status' => 'open', 'billed_at' => null]);
                }
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('tables.orders.show', $order)
            ->with('success', 'Table ' . ($fromTable->name ?: $fromTable->number) . ' merged into this order.');
    }

    // ── QR self-ordering: staff side (approve/reject a customer's request) ──────

    public function qr(RestaurantTable $table)
    {
        abort_if($table->business_id !== $this->businessId(), 403);
        $url = route('table-order.public', $table->qrToken());

        return view('tables.qr', ['table' => $table, 'url' => $url, 'svg' => ShopQr::svg($url, 320)]);
    }

    public function approveRequest(RestaurantTable $table, TableOrderRequest $tableOrderRequest)
    {
        abort_if($table->business_id !== $this->businessId(), 403);
        abort_if($tableOrderRequest->restaurant_table_id !== $table->id, 403);
        abort_if($tableOrderRequest->status !== 'pending', 422, 'That request was already handled.');

        // table_order_items.product_id is required — a product deleted between the
        // customer's request and staff approving it (rare, but possible) can't be
        // turned into a line item; reject the request outright rather than crash.
        $product = $tableOrderRequest->product_id
            ? Product::where('business_id', $this->businessId())->find($tableOrderRequest->product_id)
            : null;
        if (! $product) {
            $tableOrderRequest->update(['status' => 'rejected', 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);

            return back()->with('error', 'That item is no longer available — the request was dismissed.');
        }

        $orderId = null;

        DB::transaction(function () use ($table, $tableOrderRequest, $product, &$orderId) {
            $table = RestaurantTable::whereKey($table->id)->lockForUpdate()->first();

            // No order open yet at this table — a customer can scan and ask before
            // staff has seated/opened it. Approving the first request opens one.
            if (! $table->current_order_id || $table->status !== 'occupied') {
                $order = TableOrder::create([
                    'business_id'            => $this->businessId(),
                    'restaurant_table_id'    => $table->id,
                    'user_id'                => Auth::id(),
                    'status'                 => 'open',
                    'opened_at'              => now(),
                    'service_charge_percent' => (float) ($this->business()->service_charge_percent ?? 0),
                ]);
                $table->update(['status' => 'occupied', 'current_order_id' => $order->id]);
            } else {
                $order = TableOrder::findOrFail($table->current_order_id);
            }

            $unitPrice = $product->selling_price;

            TableOrderItem::create([
                'table_order_id' => $order->id,
                'product_id'     => $product->id,
                'product_name'   => $tableOrderRequest->product_name,
                'quantity'       => $tableOrderRequest->quantity,
                'unit_price'     => $unitPrice,
                'total'          => round($unitPrice * (float) $tableOrderRequest->quantity, 2),
                'notes'          => $tableOrderRequest->notes,
            ]);
            $order->recalculate();
            if ($order->status === 'billed') {
                $order->update(['status' => 'open', 'billed_at' => null]);
            }

            $tableOrderRequest->update(['status' => 'approved', 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);
            $orderId = $order->id;
        });

        return redirect()->route('tables.orders.show', $orderId)->with('success', 'Added to the order.');
    }

    public function rejectRequest(RestaurantTable $table, TableOrderRequest $tableOrderRequest)
    {
        abort_if($table->business_id !== $this->businessId(), 403);
        abort_if($tableOrderRequest->restaurant_table_id !== $table->id, 403);
        abort_if($tableOrderRequest->status !== 'pending', 422, 'That request was already handled.');

        $tableOrderRequest->update(['status' => 'rejected', 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);

        return back()->with('success', 'Request dismissed.');
    }

    // ── Pay ──────────────────────────────────────────────────────────────────────
    // One payment settles the items ticked for it (all outstanding items by
    // default), so a table can pay in several goes — each guest for their own
    // dishes, by cash, M-Pesa or card as they prefer. Each payment is its own
    // sale and receipt. The service charge is worked out on the items being
    // paid; a tip is kept beside the sale, not inside it.
    public function pay(TableOrder $order, Request $request)
    {
        abort_if($order->business_id !== $this->businessId(), 403);
        $request->validate([
            'payment_method'  => 'required|in:cash,mpesa,card,bank_transfer',
            'customer_name'   => 'nullable|string|max:100',
            'amount_tendered' => 'nullable|numeric|min:0|max:100000000',
            'tip_amount'      => 'nullable|numeric|min:0|max:100000000',
            'mpesa_reference' => 'nullable|string|max:50',
            'item_ids'        => 'nullable|array',
            'item_ids.*'      => 'integer',
        ]);

        $sale = null;
        $orderClosed = false;

        try {
            DB::transaction(function () use ($order, $request, &$sale, &$orderClosed) {
                // Lock and re-check: a double tap, or two tills on the same table,
                // used to create two sales for one bill.
                $order = TableOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
                if (! in_array($order->status, ['open', 'billed'], true)) {
                    throw new \RuntimeException('This order has already been paid or closed.');
                }

                $outstanding = $order->outstandingItems()->lockForUpdate()->get();
                if ($outstanding->isEmpty()) {
                    throw new \RuntimeException('There is nothing to pay on this order.');
                }

                // Which items this payment covers.
                $selected = $request->filled('item_ids')
                    ? $outstanding->whereIn('id', array_map('intval', $request->item_ids))->values()
                    : $outstanding;
                if ($selected->isEmpty()) {
                    throw new \RuntimeException('Tick at least one item to pay for.');
                }

                $subtotal = round((float) $selected->sum('total'), 2);
                $service  = $order->serviceChargeOn($subtotal);
                $total    = round($subtotal + $service, 2);
                $tip      = round((float) ($request->tip_amount ?? 0), 2);

                $method    = $request->payment_method;
                $tendered  = null;
                $reference = null;

                if ($method === 'cash' && $request->filled('amount_tendered')) {
                    $tendered = round((float) $request->amount_tendered, 2);
                    if ($tendered + 0.001 < $total + $tip) {
                        throw new \RuntimeException('The cash received (KSh ' . number_format($tendered, 2) . ') is less than the bill'
                            . ($tip > 0 ? ' plus tip' : '') . ' (KSh ' . number_format($total + $tip, 2) . ').');
                    }
                }

                if ($method === 'mpesa' && $request->filled('mpesa_reference')) {
                    $reference = strtoupper(trim($request->mpesa_reference));
                    $used = Sale::withoutGlobalScopes()->where('business_id', $order->business_id)
                        ->where('mpesa_reference', $reference)->exists();
                    if ($used) {
                        throw new \RuntimeException('That M-Pesa code is already recorded on another sale.');
                    }
                }

                $business = $this->business();

                // Same tax-inclusive VAT as every other sale path (only when the
                // business is VAT-registered); the service charge is part of the price.
                $vatRate   = $business->isVatRegistered() ? (float) ($business->vat_rate ?? 16) : 0;
                $taxAmount = $vatRate > 0 ? round($total * $vatRate / (100 + $vatRate), 2) : 0;

                // TBL-00012, then TBL-00012-2, -3… for the later payments of a split bill.
                $earlier = Sale::withoutGlobalScopes()->where('table_order_id', $order->id)->count();
                $invoice = 'TBL-' . str_pad($order->id, 5, '0', STR_PAD_LEFT) . ($earlier > 0 ? '-' . ($earlier + 1) : '');
                $guest   = $request->filled('customer_name') ? trim($request->customer_name) : $order->customer_name;

                $sale = Sale::create([
                    'business_id'           => $business->id,
                    'shift_id'              => Shift::currentOpenId($business->id),
                    'user_id'               => Auth::id(),
                    'customer_id'           => $order->customer_id,
                    'invoice_number'        => $invoice,
                    'subtotal'              => $subtotal,
                    'tax_amount'            => $taxAmount,
                    // invoice.blade.php reads vat_amount, not tax_amount.
                    'vat_amount'            => $taxAmount,
                    'total_amount'          => $total,
                    'paid_amount'           => $total,
                    'balance_due'           => 0,
                    'payment_method'        => $method,
                    'mpesa_reference'       => $reference,
                    'payment_status'        => 'paid',
                    'sale_status'           => 'completed',
                    'table_order_id'        => $order->id,
                    'service_charge_amount' => $service,
                    'tip_amount'            => $tip,
                    'amount_tendered'       => $tendered,
                    'table_guest_name'      => $guest,
                ]);

                foreach ($selected as $item) {
                    // Table sales are built here rather than through SaleService,
                    // the only place stock normally drops. Clamped at zero (not an
                    // exception) so a venue that doesn't track stock tightly can
                    // still take payment.
                    if ($item->product_id && ($stockProduct = Product::where('business_id', $business->id)->find($item->product_id))) {
                        $stockProduct->update(['stock_qty' => max(0, (int) $stockProduct->stock_qty - (int) ceil($item->quantity))]);
                        \App\Models\ProductBatch::consume((int) $stockProduct->id, null, (float) $item->quantity);
                    }

                    // sale_items.discount is a PERCENTAGE (see SaleService), but a table
                    // order's per-item discount is stored as a flat KSh amount — convert
                    // so the sale's own line still displays correctly everywhere else that
                    // reads sale_items.discount as a percent.
                    $lineGross = round((float) $item->unit_price * (float) $item->quantity, 2);
                    $discountPercent = $lineGross > 0 ? round((float) $item->discount / $lineGross * 100, 2) : 0;

                    SaleItem::create([
                        'sale_id'      => $sale->id,
                        'product_id'   => $item->product_id,
                        'product_name' => $item->product_name ?: ($item->product?->name ?? ''),
                        'quantity'     => (int) ceil($item->quantity),
                        'unit_price'   => $item->unit_price,
                        'buying_price' => $item->product?->buying_price ?? 0,
                        'subtotal'     => $item->total,
                        'discount'     => $discountPercent,
                    ]);

                    $item->update(['sale_id' => $sale->id]);
                }

                $orderClosed = ! $order->outstandingItems()->exists();

                if ($orderClosed) {
                    $order->update([
                        'status'          => 'paid',
                        'closed_at'       => now(),
                        'sale_id'         => $sale->id,
                        'payment_method'  => $method,
                        'amount_tendered' => $tendered,
                        'customer_name'   => $guest,
                    ]);
                    $order->recalculate();
                    $order->table->update(['status' => 'available', 'current_order_id' => null]);
                } else {
                    // Still items to pay: the table stays open, and any earlier bill is out of date.
                    $order->update(['status' => 'open', 'billed_at' => null, 'customer_name' => $guest]);
                    $order->recalculate();
                }
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        // KRA eTIMS, exactly as for a normal sale (table sales were skipped).
        $business = $this->business();
        if ($business->isEtimsConfigured()) {
            $sale->update(['etims_status' => 'pending']);
            try {
                \App\Jobs\SubmitEtimsDocument::dispatchSync('sale', $sale->id);
                $sale->refresh();
                if ($sale->etims_status !== 'submitted') {
                    \App\Jobs\SubmitEtimsDocument::dispatch('sale', $sale->id)->delay(now()->addMinutes(2));
                }
            } catch (\Throwable $e) {
                \App\Jobs\SubmitEtimsDocument::dispatch('sale', $sale->id)->delay(now()->addMinutes(2));
            }
        }

        try {
            \App\Services\WebhookService::dispatch('sale.created', $business->id, [
                'sale_id'        => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'total_amount'   => $sale->total_amount,
                'payment_method' => $sale->payment_method,
                'customer_id'    => $sale->customer_id,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Table sale webhook failed: ' . $e->getMessage());
        }

        // Straight to the receipt (it prints itself, then goes back): to the floor
        // plan when the table is finished, or to the order when others still have
        // to pay.
        return redirect()->route('sales.receipt', ['sale' => $sale->id, 'return' => $orderClosed ? 'floor' : 'order']);
    }
}
