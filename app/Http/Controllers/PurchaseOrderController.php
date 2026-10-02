<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\Product;
use App\Models\StockAdjustment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller {

    private function businessId(): int {
        return Auth::user()->currentBusiness()->id;
    }

    // â”€â”€ List all purchase orders â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function index(Request $request) {
        $businessId = $this->businessId();
        $query = PurchaseOrder::forBusiness($businessId)
                    ->with('supplier');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by supplier
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('order_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('order_date', '<=', $request->date_to);
        }

        $purchaseOrders = $query->latest('order_date')
                            ->paginate(15)
                            ->withQueryString();

        // Stats
        $stats = [
            'total'     => PurchaseOrder::forBusiness($businessId)->count(),
            'pending'   => PurchaseOrder::forBusiness($businessId)->pending()->count(),
            // Cash actually paid to suppliers this month, not the value of POs
            // placed — summing 'total' here made this identical to Unpaid
            // Balance whenever nothing had been paid yet, which is misleading.
            'this_month'=> SupplierPayment::forBusiness($businessId)
                            ->where('type', 'payment')
                            ->whereMonth('payment_date', now()->month)
                            ->whereYear('payment_date', now()->year)
                            ->sum('amount'),
            'unpaid_balance' => PurchaseOrder::forBusiness($businessId)
                            ->whereIn('payment_status', ['unpaid', 'partial'])
                            ->selectRaw('SUM(total - amount_paid) as balance')
                            ->value('balance') ?? 0,
        ];

        // Suppliers for filter dropdown
        $suppliers = Supplier::forBusiness($businessId)
                        ->orderBy('name')
                        ->get();

        return view('purchases.index', compact('purchaseOrders', 'stats', 'suppliers'));
    }

    // â”€â”€ Show create form â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function create(Request $request) {
        $businessId = $this->businessId();

        $suppliers = Supplier::forBusiness($businessId)
                        ->active()
                        ->orderBy('name')
                        ->get();

        $products = Product::forBusiness($businessId)
                        ->active()
                        ->orderBy('name')
                        ->get();

        $defaultVatRate = Auth::user()->currentBusiness()->vatRate();

        // Pre-fill from a requisition (PurchaseRequisitionController::convert()
        // sends the user here with ?requisition=ID) — previously this page had
        // no idea a requisition existed at all, so every item on it was lost.
        $requisition = null;
        if ($request->filled('requisition')) {
            $requisition = \App\Models\PurchaseRequisition::where('id', $request->requisition)
                ->where('business_id', $businessId)
                ->where('status', '!=', 'converted')
                ->with('items')
                ->first();
        }

        return view('purchases.create', compact('suppliers', 'products', 'defaultVatRate', 'requisition'));
    }

    // â”€â”€ Store new purchase order â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function store(Request $request) {
        $businessId = $this->businessId();

        $request->validate([
            'supplier_id'              => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('suppliers', 'id')->where('business_id', $businessId)],
            'requisition_id'           => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('purchase_requisitions', 'id')->where('business_id', $businessId)],
            'order_date'               => 'required|date',
            'expected_date'            => 'nullable|date',
            'notes'                    => 'nullable|string',
            'items'                    => 'required|array|min:1',
            'items.*.product_id'       => 'nullable|integer',
            'items.*.product_name'     => 'required|string',
            'items.*.quantity_ordered' => 'required|integer|min:1',
            'items.*.unit_cost'        => 'required|numeric|min:0',
            'items.*.vat_rate'         => 'nullable|numeric|min:0|max:100',
        ]);

        // Verify supplier belongs to this business if provided
        if ($request->filled('supplier_id')) {
            $supplier = Supplier::where('id', $request->supplier_id)
                            ->where('business_id', $businessId)
                            ->firstOrFail();
        }

        $poNumber = PurchaseOrder::generatePoNumber($businessId);

        DB::transaction(function () use ($request, $businessId, $poNumber) {
            // Calculate totals. vat_rate is opt-in per line (defaults to 0,
            // same as every PO before this feature existed) — unit_cost is
            // treated as VAT-exclusive, same convention InvoiceController
            // already uses for its own items, so VAT is added on top rather
            // than assumed to be baked into the entered cost.
            $subtotal  = 0;
            $taxAmount = 0;
            foreach ($request->items as $item) {
                $lineSubtotal = $item['quantity_ordered'] * $item['unit_cost'];
                $lineVat      = round($lineSubtotal * (($item['vat_rate'] ?? 0) / 100), 2);
                $subtotal    += $lineSubtotal;
                $taxAmount   += $lineVat;
            }

            // Create the PO
            $po = PurchaseOrder::create([
                'business_id'   => $businessId,
                'supplier_id'   => $request->supplier_id ?: null,
                'user_id'       => Auth::id(),
                'po_number'     => $poNumber,
                'order_date'    => $request->order_date,
                'expected_date' => $request->expected_date,
                'subtotal'      => $subtotal,
                'tax_amount'    => $taxAmount,
                'total'         => $subtotal + $taxAmount,
                'amount_paid'   => 0,
                'status'        => 'ordered',
                'payment_status'=> 'unpaid',
                'notes'         => $request->notes,
            ]);

            // Create PO items
            foreach ($request->items as $item) {
                $lineSubtotal = $item['quantity_ordered'] * $item['unit_cost'];
                $lineVatRate  = (float) ($item['vat_rate'] ?? 0);
                $lineVat      = round($lineSubtotal * $lineVatRate / 100, 2);
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id'        => $item['product_id'] ?: null,
                    'product_name'      => $item['product_name'],
                    'quantity_ordered'  => $item['quantity_ordered'],
                    'quantity_received' => 0,
                    'unit_cost'         => $item['unit_cost'],
                    'subtotal'          => $lineSubtotal,
                    'vat_rate'          => $lineVatRate,
                    'vat_amount'        => $lineVat,
                ]);
            }

            // Link back to the requisition this PO was created from, if any
            // (PurchaseRequisitionController::convert() sends the customer
            // here but previously never actually created anything — the
            // requisition was marked 'converted' with its line items just
            // discarded). Only now, once the PO genuinely exists, do we mark
            // the requisition converted and point it at the real PO.
            if ($request->filled('requisition_id')) {
                \App\Models\PurchaseRequisition::where('id', $request->requisition_id)
                    ->where('business_id', $businessId)
                    ->update(['status' => 'converted', 'purchase_order_id' => $po->id]);
            }

            $this->poId = $po->id;
        });

        return redirect()
            ->route('purchases.show', $this->poId)
            ->with('success', "Purchase Order {$poNumber} created successfully.");
    }

    // â”€â”€ Show PO detail â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function show(PurchaseOrder $purchaseOrder) {
        $this->authorizePO($purchaseOrder);

        $purchaseOrder->load(['supplier', 'items.product', 'user']);

        return view('purchases.show', compact('purchaseOrder'));
    }

    // â”€â”€ Receive stock â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function receive(Request $request, PurchaseOrder $purchaseOrder) {
        $this->authorizePO($purchaseOrder);

        $request->validate([
            'items'                    => 'required|array',
            'items.*.id'               => 'required|integer',
            'items.*.quantity_received'=> 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($request, $purchaseOrder) {
            foreach ($request->items as $itemData) {
                $item = PurchaseOrderItem::find($itemData['id']);

                if (!$item || $item->purchase_order_id !== $purchaseOrder->id) {
                    continue;
                }

                $newQty = (int) $itemData['quantity_received'];
                if ($newQty <= 0) continue;

                $oldReceived = $item->quantity_received;
                $item->increment('quantity_received', $newQty);

                // Update product stock if linked to a product
                if ($item->product_id) {
                    $product = Product::find($item->product_id);
                    if ($product) {
                        $qtyBefore = $product->stock_qty;
                        $product->increment('stock_qty', $newQty);

                        StockAdjustment::create([
                            'business_id'     => $purchaseOrder->business_id,
                            'product_id'      => $item->product_id,
                            'user_id'         => Auth::id(),
                            'type'            => 'addition',
                            'quantity_before' => $qtyBefore,
                            'quantity_change' => $newQty,
                            'quantity_after'  => $qtyBefore + $newQty,
                            'reason'          => 'purchase',
                            'reference'       => $purchaseOrder->po_number,
                            'notes'           => "Received via PO #{$purchaseOrder->po_number}",
                        ]);
                    }
                }
            }

            // Refresh items to check receive status
            $purchaseOrder->load('items');
            $allReceived = $purchaseOrder->items->every(
                fn ($i) => $i->quantity_received >= $i->quantity_ordered
            );
            $anyReceived = $purchaseOrder->items->some(
                fn ($i) => $i->quantity_received > 0
            );

            if ($allReceived) {
                $purchaseOrder->update([
                    'status'        => 'received',
                    'received_date' => now()->toDateString(),
                ]);

                // Auto-create a supplier bill when PO is fully received
                if ($purchaseOrder->supplier_id) {
                    $supplier = Supplier::find($purchaseOrder->supplier_id);
                    if ($supplier) {
                        $newBalance = (float) $supplier->payable_balance + (float) $purchaseOrder->total;
                        SupplierPayment::create([
                            'business_id'       => $purchaseOrder->business_id,
                            'supplier_id'       => $supplier->id,
                            'purchase_order_id' => $purchaseOrder->id,
                            'user_id'           => Auth::id(),
                            'type'              => 'bill',
                            'amount'            => $purchaseOrder->total,
                            'balance_after'     => $newBalance,
                            'reference'         => $purchaseOrder->po_number,
                            'notes'             => "Auto-bill from PO #{$purchaseOrder->po_number}",
                            'payment_date'      => now()->toDateString(),
                        ]);
                        $supplier->update(['payable_balance' => $newBalance]);
                    }
                }
            } elseif ($anyReceived) {
                $purchaseOrder->update(['status' => 'partially_received']);
            }
        });

        return redirect()
            ->route('purchases.show', $purchaseOrder)
            ->with('success', 'Stock received and inventory updated.');
    }

    // â”€â”€ Record payment â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function recordPayment(Request $request, PurchaseOrder $purchaseOrder) {
        $this->authorizePO($purchaseOrder);

        $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $amount  = (float) $request->amount;
        $newPaid = $purchaseOrder->amount_paid + $amount;

        $paymentStatus = 'unpaid';
        if ($newPaid >= $purchaseOrder->total) {
            $paymentStatus = 'paid';
            $newPaid       = $purchaseOrder->total; // cap at total
        } elseif ($newPaid > 0) {
            $paymentStatus = 'partial';
        }

        DB::transaction(function () use ($purchaseOrder, $newPaid, $paymentStatus, $amount) {
            $purchaseOrder->update([
                'amount_paid'    => $newPaid,
                'payment_status' => $paymentStatus,
            ]);

            // Mirror SupplierPaymentController::store() — a payment against a
            // specific PO must reduce the supplier's payable_balance and leave
            // a ledger entry the same way a standalone supplier payment does,
            // or the supplier permanently shows as owed the full PO amount
            // even after it's been paid off through this button.
            if ($purchaseOrder->supplier_id) {
                $supplier = Supplier::find($purchaseOrder->supplier_id);
                if ($supplier) {
                    $newBalance = max(0, (float) $supplier->payable_balance - $amount);

                    SupplierPayment::create([
                        'business_id'       => $purchaseOrder->business_id,
                        'supplier_id'       => $supplier->id,
                        'purchase_order_id' => $purchaseOrder->id,
                        'user_id'           => Auth::id(),
                        'type'              => 'payment',
                        'amount'            => $amount,
                        'balance_after'     => $newBalance,
                        'reference'         => $purchaseOrder->po_number,
                        'notes'             => "Payment against PO #{$purchaseOrder->po_number}",
                        'payment_date'      => now()->toDateString(),
                    ]);

                    $supplier->update(['payable_balance' => $newBalance]);
                }
            }
        });

        return redirect()
            ->route('purchases.show', $purchaseOrder)
            ->with('success', 'Payment of KSh ' . number_format($amount, 2) . ' recorded.');
    }

    // â”€â”€ Cancel PO â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function cancel(PurchaseOrder $purchaseOrder) {
        $this->authorizePO($purchaseOrder);

        if (!in_array($purchaseOrder->status, ['draft', 'ordered'])) {
            return back()->with('error', 'Only draft or ordered purchase orders can be cancelled.');
        }

        $purchaseOrder->update(['status' => 'cancelled']);

        return redirect()
            ->route('purchases.show', $purchaseOrder)
            ->with('success', 'Purchase order cancelled.');
    }

    // â”€â”€ Authorization helper â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    private function authorizePO(PurchaseOrder $purchaseOrder): void {
        if ($purchaseOrder->business_id !== $this->businessId()) {
            abort(403, 'Unauthorized action.');
        }
    }
}

