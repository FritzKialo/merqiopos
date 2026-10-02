<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockReceive;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockReceiveController extends Controller
{
    private function business()
    {
        return Auth::user()->currentBusiness();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $business = $this->business();

        $receives = StockReceive::with(['supplier', 'user', 'purchaseOrder'])
            ->forBusiness($business->id)
            ->latest('received_date')
            ->paginate(20)
            ->withQueryString();

        return view('inventory.receives.index', compact('receives'));
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function create(Request $request)
    {
        $business  = $this->business();
        $products  = Product::forBusiness($business->id)->active()->orderBy('name')->get();
        $suppliers = Supplier::where('business_id', $business->id)->orderBy('name')->get();

        $purchaseOrder = null;
        $openPOs = PurchaseOrder::where('business_id', $business->id)
            ->whereIn('status', ['ordered', 'partially_received'])
            ->with('supplier', 'items.product')
            ->get();

        if ($request->filled('po_id')) {
            $purchaseOrder = PurchaseOrder::with(['supplier', 'items.product'])
                ->where('business_id', $business->id)
                ->findOrFail($request->po_id);
        }

        return view('inventory.receives.create', compact('products', 'suppliers', 'purchaseOrder', 'openPOs'));
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $business = $this->business();

        $request->validate([
            'received_date'       => 'required|date',
            // Both scoped to this business — an unscoped exists: check would
            // let a crafted id link this receive to another business's
            // supplier/PO (and the PO one further below gets its status
            // mutated, not just linked).
            'supplier_id'         => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('suppliers', 'id')->where('business_id', $business->id)],
            'purchase_order_id'   => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('purchase_orders', 'id')->where('business_id', $business->id)],
            'invoice_ref'         => 'nullable|string|max:100',
            'notes'               => 'nullable|string|max:1000',
            'items'               => 'required|array|min:1',
            'items.*.product_id'  => 'required|integer|exists:products,id',
            'items.*.quantity_received' => 'required|integer|min:1',
            'items.*.unit_cost'   => 'required|numeric|min:0',
            // Which unit the two fields above were entered in — 'buy' only
            // makes sense for a product that actually has a buy unit
            // configured; store() re-derives the real conversion factor
            // from the product itself rather than trusting anything about
            // the ratio from the client.
            'items.*.unit_type'   => 'nullable|in:sell,buy',
        ]);

        DB::transaction(function () use ($request, $business) {
            $totalCost = 0;
            // Converted (sell-unit) qty/cost per line, keyed by array
            // index — computed once here and reused below instead of
            // repeating the same conversion logic on the second pass.
            $converted = [];

            foreach ($request->items as $i => $line) {
                $product = Product::where('business_id', $business->id)->findOrFail($line['product_id']);
                $enteredQty  = (int) $line['quantity_received'];
                $enteredCost = (float) $line['unit_cost'];

                $isBuyUnit = ($line['unit_type'] ?? 'sell') === 'buy'
                    && $product->buy_unit
                    && $product->units_per_buy_unit > 1;

                if ($isBuyUnit) {
                    // e.g. "3 Cartons of 24" → 72 pieces at cost-per-piece
                    // instead of cost-per-carton, so stock_qty and the
                    // inventory valuation this feeds stay in the same
                    // sell-unit terms as everywhere else in the app.
                    $sellQty  = $enteredQty * $product->units_per_buy_unit;
                    $sellCost = $enteredCost / $product->units_per_buy_unit;
                } else {
                    $sellQty  = $enteredQty;
                    $sellCost = $enteredCost;
                }

                $converted[$i] = [
                    'sell_qty'    => $sellQty,
                    'sell_cost'   => $sellCost,
                    'is_buy_unit' => $isBuyUnit,
                    'buy_unit'    => $product->buy_unit,
                ];
                $totalCost += $sellQty * $sellCost;
            }

            $receive = StockReceive::create([
                'business_id'       => $business->id,
                'user_id'           => Auth::id(),
                'purchase_order_id' => $request->purchase_order_id,
                'supplier_id'       => $request->supplier_id,
                'receive_number'    => 'RCV-TEMP',
                'received_date'     => $request->received_date,
                'total_cost'        => $totalCost,
                'invoice_ref'       => $request->invoice_ref,
                'notes'             => $request->notes,
            ]);

            $receive->update(['receive_number' => 'RCV-' . str_pad($receive->id, 5, '0', STR_PAD_LEFT)]);

            foreach ($request->items as $i => $line) {
                $product = Product::where('business_id', $business->id)->findOrFail($line['product_id']);
                $c       = $converted[$i];
                $qty     = $c['sell_qty'];

                $receive->items()->create([
                    'product_id'          => $product->id,
                    'product_name'        => $product->name,
                    'purchase_order_item_id' => $line['purchase_order_item_id'] ?? null,
                    'quantity_received'   => $qty,
                    'unit_cost'           => $c['sell_cost'],
                    'subtotal'            => $qty * $c['sell_cost'],
                    'received_unit'          => $c['is_buy_unit'] ? $c['buy_unit'] : null,
                    'received_qty_in_unit'   => $c['is_buy_unit'] ? (int) $line['quantity_received'] : null,
                ]);

                // Weighted-average cost: blend the existing on-hand stock
                // (at its current buying_price) with this incoming batch
                // (at its own unit_cost), so a product received at varying
                // prices over time keeps one representative cost instead of
                // silently freezing at whatever buying_price it had when it
                // was first added. Skipped when there's no existing stock
                // (or a negative/zero qty from a prior correction) — in
                // that case the new batch's cost simply becomes the cost.
                $existingQty = max(0, (int) $product->stock_qty);
                $existingCost = (float) $product->buying_price;
                $combinedQty = $existingQty + $qty;

                if ($combinedQty > 0) {
                    $newBuyingPrice = (
                        ($existingQty * $existingCost) + ($qty * $c['sell_cost'])
                    ) / $combinedQty;

                    $product->buying_price = round($newBuyingPrice, 2);
                }

                // Stock stays tracked in the sell unit everywhere — $qty is
                // already converted above, so this is always a plain
                // sell-unit increment regardless of what was typed in.
                $product->increment('stock_qty', $qty);

                // Update PO item if linked. PurchaseOrderItem has no business_id
                // of its own — a posted purchase_order_item_id must be checked
                // against its parent PO's business_id, otherwise a crafted id
                // could increment another business's purchase order item.
                if (! empty($line['purchase_order_item_id'])) {
                    $poItem = PurchaseOrderItem::with('purchaseOrder')->find($line['purchase_order_item_id']);
                    if ($poItem && $poItem->purchaseOrder && $poItem->purchaseOrder->business_id === $business->id) {
                        $poItem->increment('quantity_received', $qty);
                    }
                }
            }

            // Update PO status if linked
            if ($request->purchase_order_id) {
                $po = PurchaseOrder::find($request->purchase_order_id);
                if ($po) {
                    $po->load('items');
                    $allReceived = $po->items->every(function ($item) {
                        return $item->quantity_received >= $item->quantity_ordered;
                    });
                    $wasAlreadyReceived = $po->status === 'received';
                    $po->update(['status' => $allReceived ? 'received' : 'partially_received']);

                    // Mirror PurchaseOrderController::receive() — a PO that
                    // reaches full receipt through this flow must bill the
                    // supplier the same way it would through the PO page's
                    // own "Receive Stock" button, or the supplier's payable
                    // balance silently never reflects goods received here.
                    if ($allReceived && !$wasAlreadyReceived && $po->supplier_id) {
                        $supplier = Supplier::find($po->supplier_id);
                        if ($supplier) {
                            $newBalance = (float) $supplier->payable_balance + (float) $po->total;
                            SupplierPayment::create([
                                'business_id'       => $po->business_id,
                                'supplier_id'       => $supplier->id,
                                'purchase_order_id' => $po->id,
                                'user_id'           => Auth::id(),
                                'type'              => 'bill',
                                'amount'            => $po->total,
                                'balance_after'     => $newBalance,
                                'reference'         => $po->po_number,
                                'notes'             => "Auto-bill from PO #{$po->po_number}",
                                'payment_date'      => now()->toDateString(),
                            ]);
                            $supplier->update(['payable_balance' => $newBalance]);
                        }
                    }

                    if ($allReceived && !$wasAlreadyReceived) {
                        $po->update(['received_date' => $po->received_date ?? now()->toDateString()]);
                    }
                }
            }
        });

        return redirect()->route('receives.index')->with('success', 'Stock received successfully.');
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(StockReceive $stockReceive)
    {
        $business = $this->business();

        if ($stockReceive->business_id !== $business->id) {
            abort(403);
        }

        $stockReceive->load(['items.product', 'supplier', 'purchaseOrder', 'user']);

        return view('inventory.receives.show', compact('stockReceive'));
    }
}
