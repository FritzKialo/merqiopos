@extends('layouts.app')
@section('title', 'Receive Stock')
@push('styles')
<style>
@media (max-width: 900px) {
    .receive-header-grid { grid-template-columns: 1fr 1fr !important; }
}
@media (max-width: 500px) {
    .receive-header-grid { grid-template-columns: 1fr !important; }
}
@media (min-width: 769px) {
    #itemsTable tfoot td { padding: 0.75rem; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Receive Stock</h1>
            <p class="page-subtitle">Record goods received into inventory</p>
        </div>
        <a href="{{ route('receives.index') }}" class="btn btn--outline">Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert--error">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
    @endif

    <form method="POST" action="{{ route('receives.store') }}" id="receiveForm">
        @csrf

        <div class="table-card" style="margin-bottom:1.5rem;">
            <div class="card-body">
                <div class="receive-header-grid" style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">Received Date *</label>
                        <input type="date" name="received_date" class="form-control"
                               value="{{ old('received_date', date('Y-m-d')) }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Supplier</label>
                        <select name="supplier_id" class="form-control">
                            <option value="">— No Supplier —</option>
                            @foreach($suppliers as $s)
                                <option value="{{ $s->id }}" {{ old('supplier_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Purchase Order (optional)</label>
                        <select name="purchase_order_id" class="form-control" id="poSelect">
                            <option value="">— Ad-hoc receive —</option>
                            @foreach($openPOs as $po)
                                <option value="{{ $po->id }}" {{ (old('purchase_order_id') == $po->id || ($purchaseOrder && $purchaseOrder->id == $po->id)) ? 'selected' : '' }}>
                                    {{ $po->po_number }} — {{ $po->supplier?->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Supplier Invoice Ref</label>
                        <input type="text" name="invoice_ref" class="form-control"
                               value="{{ old('invoice_ref') }}" placeholder="e.g. INV-12345">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>

        <div class="table-card" style="margin-bottom:1.5rem;">
            <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;">
                <h3>Items Received</h3>
                <button type="button" onclick="addRow()" class="btn btn--outline">+ Add Row</button>
            </div>
            <div class="table-wrapper">
            <table class="table" id="itemsTable">
                <thead>
                    <tr>
                        <th>Product *</th>
                        <th>Received In</th>
                        <th>Qty Received *</th>
                        <th>Unit Cost (KSh) *</th>
                        <th>Subtotal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="itemsBody">
                    @if($purchaseOrder)
                        @foreach($purchaseOrder->items as $i => $poItem)
                        @php $remaining = $poItem->quantity_ordered - $poItem->quantity_received; @endphp
                        @if($remaining > 0)
                        <tr>
                            <td data-label="Product">
                                <select name="items[{{ $i }}][product_id]" class="form-control" required>
                                    <option value="{{ $poItem->product_id }}" selected>{{ $poItem->product?->name }}</option>
                                </select>
                                <input type="hidden" name="items[{{ $i }}][purchase_order_item_id]" value="{{ $poItem->id }}">
                                <input type="hidden" name="items[{{ $i }}][unit_type]" value="sell">
                            </td>
                            <td data-label="Received In">{{ $poItem->product?->unit ?? 'piece' }}</td>
                            <td data-label="Qty Received"><input type="number" name="items[{{ $i }}][quantity_received]" class="form-control qty-input" min="1" max="{{ $remaining }}" value="{{ $remaining }}" required></td>
                            <td data-label="Unit Cost"><input type="number" name="items[{{ $i }}][unit_cost]" class="form-control cost-input" min="0" step="0.01" value="{{ $poItem->unit_cost ?? 0 }}" required></td>
                            <td data-label="Subtotal" class="subtotal">KSh 0</td>
                            <td data-label="Actions"><button type="button" onclick="removeRow(this)" class="btn btn--danger btn--sm">Remove</button></td>
                        </tr>
                        @endif
                        @endforeach
                    @else
                        <tr>
                            <td data-label="Product">
                                <select name="items[0][product_id]" class="form-control product-select" required>
                                    <option value="">— Select Product —</option>
                                    @foreach($products as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td data-label="Received In"><select name="items[0][unit_type]" class="form-control unit-select"><option value="sell">piece</option></select></td>
                            <td data-label="Qty Received"><input type="number" name="items[0][quantity_received]" class="form-control qty-input" min="1" value="1" required></td>
                            <td data-label="Unit Cost"><input type="number" name="items[0][unit_cost]" class="form-control cost-input" min="0" step="0.01" value="0" required></td>
                            <td data-label="Subtotal" class="subtotal">KSh 0</td>
                            <td data-label="Actions"><button type="button" onclick="removeRow(this)" class="btn btn--danger btn--sm">Remove</button></td>
                        </tr>
                    @endif
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" data-label="Total Cost" style="text-align:right;"><strong>Total Cost:</strong></td>
                        <td data-label="Amount"><strong id="grandTotal">KSh 0</strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            </div>
        </div>

        <div class="table-card">
            <div class="card-body">
                <button type="submit" class="btn btn--primary">Save Stock Receive</button>
                <a href="{{ route('receives.index') }}" class="btn btn--outline">Cancel</a>
            </div>
        </div>
    </form>
</div>

<script>
// unit/buy_unit/units_per_buy_unit power the "Received In" dropdown — a
// product with no buy_unit configured just shows its sell unit, no choice
// to make. Only ad-hoc rows get this dropdown; PO-linked rows above stay
// fixed to the sell unit since the PO's ordered/received quantities are
// already tracked in it.
@php
    // @json() can't reliably parse a directly-inlined multi-key array
    // literal inside a ->map(fn()=>[...]) closure (its argument-extraction
    // regex stops at the first ')' it sees, silently truncating the array
    // and breaking the whole page with a PHP ParseError) — build the plain
    // array first, then @json() the already-resolved variable instead.
    $productsForReceiveJs = $products->map(fn($p) => [
        'id' => $p->id, 'name' => $p->name, 'unit' => $p->unit,
        'buy_unit' => $p->buy_unit, 'units_per_buy_unit' => $p->units_per_buy_unit,
    ]);
@endphp
const products = @json($productsForReceiveJs);
let rowIndex = {{ $purchaseOrder ? $purchaseOrder->items->count() : 1 }};

function addRow() {
    const tbody = document.getElementById('itemsBody');
    const i = rowIndex++;
    const opts = products.map(p => `<option value="${p.id}">${p.name}</option>`).join('');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td data-label="Product"><select name="items[${i}][product_id]" class="form-control product-select" required><option value="">— Select —</option>${opts}</select></td>
        <td data-label="Received In"><select name="items[${i}][unit_type]" class="form-control unit-select"><option value="sell">piece</option></select></td>
        <td data-label="Qty Received"><input type="number" name="items[${i}][quantity_received]" class="form-control qty-input" min="1" value="1" required></td>
        <td data-label="Unit Cost"><input type="number" name="items[${i}][unit_cost]" class="form-control cost-input" min="0" step="0.01" value="0" required></td>
        <td data-label="Subtotal" class="subtotal">KSh 0</td>
        <td data-label="Actions"><button type="button" onclick="removeRow(this)" class="btn btn--danger btn--sm">Remove</button></td>`;
    tbody.appendChild(tr);
    addListeners(tr);
    updateTotals();
}

function removeRow(btn) {
    btn.closest('tr').remove();
    updateTotals();
}

// Populates the Received In dropdown for whichever product this row just
// picked — "piece" only if there's no buy unit configured, otherwise both
// "piece" and e.g. "Carton (24 pieces)".
function refreshUnitOptions(tr) {
    const productSelect = tr.querySelector('.product-select');
    const unitSelect = tr.querySelector('.unit-select');
    if (!productSelect || !unitSelect) return;
    const product = products.find(p => String(p.id) === productSelect.value);
    const sellLabel = (product && product.unit) || 'piece';
    let html = `<option value="sell">${sellLabel}</option>`;
    if (product && product.buy_unit && product.units_per_buy_unit > 1) {
        html += `<option value="buy">${product.buy_unit} (${product.units_per_buy_unit} ${sellLabel}s)</option>`;
    }
    unitSelect.innerHTML = html;
}

// Subtotal preview needs the same buy-unit conversion the server applies,
// otherwise it'd show the cost-per-carton total instead of the real cost
// for however many pieces that carton actually contains.
function updateTotals() {
    let grand = 0;
    document.querySelectorAll('#itemsBody tr').forEach(tr => {
        const qty  = parseFloat(tr.querySelector('.qty-input')?.value || 0);
        const cost = parseFloat(tr.querySelector('.cost-input')?.value || 0);
        const unitSelect = tr.querySelector('.unit-select');
        const productSelect = tr.querySelector('.product-select');
        let sub = qty * cost;
        if (unitSelect && unitSelect.value === 'buy' && productSelect) {
            const product = products.find(p => String(p.id) === productSelect.value);
            if (product && product.units_per_buy_unit > 1) {
                sub = (qty * product.units_per_buy_unit) * (cost / product.units_per_buy_unit);
            }
        }
        grand += sub;
        tr.querySelector('.subtotal').textContent = 'KSh ' + Math.round(sub).toLocaleString();
    });
    document.getElementById('grandTotal').textContent = 'KSh ' + Math.round(grand).toLocaleString();
}

function addListeners(tr) {
    tr.querySelector('.qty-input')?.addEventListener('input', updateTotals);
    tr.querySelector('.cost-input')?.addEventListener('input', updateTotals);
    tr.querySelector('.unit-select')?.addEventListener('change', updateTotals);
    tr.querySelector('.product-select')?.addEventListener('change', function () {
        refreshUnitOptions(tr);
        updateTotals();
    });
    refreshUnitOptions(tr);
}

document.querySelectorAll('#itemsBody tr').forEach(addListeners);
updateTotals();
</script>
@endsection
