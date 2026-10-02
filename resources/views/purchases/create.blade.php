@extends('layouts.app')
@section('title', 'New Purchase Order')

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title"> New Purchase Order</h1>
        <p class="page-subtitle">Create a new purchase order for stock replenishment</p>
    </div>
    <a href="{{ route('purchases.index') }}" class="btn btn-outline">
        &#8592; Back to Purchase Orders
    </a>
</div>

@if($errors->any())
    <div class="alert alert-danger" style="margin-bottom: var(--space-md);">
        <strong>Please fix the following errors:</strong>
        <ul style="margin: 8px 0 0 20px;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('purchases.store') }}" id="poForm">
    @csrf
    @if($requisition)
    <input type="hidden" name="requisition_id" value="{{ $requisition->id }}">
    <div class="alert alert-info" style="margin-bottom:var(--space-md);">
        Creating this PO from requisition <strong>{{ $requisition->reference }}</strong> — its {{ $requisition->items->count() }} item(s) have been pre-filled below.
    </div>
    @endif

    <div class="sale-layout">

        {{-- LEFT: Items + Details --}}
        <div>

            {{-- Supplier & Dates --}}
            <div class="form-card" style="margin-bottom: var(--space-md);">
                <div class="form-section-title">
                     Supplier & Dates
                </div>

                <div class="form-group">
                    <label class="form-label" for="supplier_id">
                        Supplier
                        <a href="{{ route('suppliers.create') }}" target="_blank"
                           style="font-size: 0.8rem; margin-left: 8px; color: var(--color-primary);">
                            + Add New Supplier
                        </a>
                    </label>
                    <select id="supplier_id" name="supplier_id" class="form-control">
                        <option value="">— No Supplier / Direct Purchase —</option>
                        @foreach($suppliers as $s)
                            <option value="{{ $s->id }}" {{ old('supplier_id') == $s->id ? 'selected' : '' }}>
                                {{ $s->name }}
                                {{ $s->contact_person ? '(' . $s->contact_person . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label" for="order_date">
                            Order Date *
                        </label>
                        <input
                            type="date"
                            id="order_date"
                            name="order_date"
                            class="form-control {{ $errors->has('order_date') ? 'is-invalid' : '' }}"
                            value="{{ old('order_date', now()->format('Y-m-d')) }}"
                            required>
                        @error('order_date')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="expected_date">
                            Expected Delivery Date
                        </label>
                        <input
                            type="date"
                            id="expected_date"
                            name="expected_date"
                            class="form-control {{ $errors->has('expected_date') ? 'is-invalid' : '' }}"
                            value="{{ old('expected_date') }}">
                        @error('expected_date')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Line Items --}}
            <div class="form-card">
                <div class="form-section-title">
                     Order Items
                </div>

                @error('items')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror

                <div class="items-table-wrapper">
                    <table class="items-table" id="poItemsTable">
                        <thead>
                            <tr>
                                <th style="width: 26%;">Product</th>
                                <th style="width: 21%;">Item Name</th>
                                <th style="width: 10%;">Qty</th>
                                <th style="width: 13%;">Unit Cost (KSh)</th>
                                <th style="width: 10%;">VAT %</th>
                                <th style="width: 15%;">Subtotal</th>
                                <th style="width: 5%;"></th>
                            </tr>
                        </thead>
                        <tbody id="poItemsBody">
                            {{-- Rows injected by JS --}}
                        </tbody>
                    </table>
                </div>

                <button type="button" class="add-item-btn" onclick="addPoRow()">
                     Add Item
                </button>
            </div>
        </div>

        {{-- RIGHT: Summary Panel --}}
        <div class="summary-panel">
            <h3> Summary</h3>

            <div class="summary-row">
                <span>Subtotal</span>
                <span id="poDisplaySubtotal">KSh 0.00</span>
            </div>

            <div class="summary-row" id="poVatRow" style="display:none;">
                <span>VAT</span>
                <span id="poDisplayVat">KSh 0.00</span>
            </div>

            <div class="summary-row total">
                <span>Total</span>
                <span id="poDisplayTotal">KSh 0.00</span>
            </div>

            <div class="form-group" style="margin-top: var(--space-md);">
                <label class="form-label" for="notes">
                    Notes
                </label>
                <textarea
                    id="notes"
                    name="notes"
                    class="form-control"
                    rows="2"
                    placeholder="e.g. Delivery instructions, special requirements...">{{ old('notes') }}</textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                     Create Purchase Order
                </button>
            </div>

            <a href="{{ route('purchases.index') }}"
               style="display: block; text-align: center; margin-top: var(--space-sm); font-size: 0.875rem; color: var(--color-text-muted); text-decoration: none;">
                Cancel
            </a>
        </div>
    </div>
</form>

@endsection

@push('scripts')
@php
    $poProducts = $products->map(fn($p) => [
        'id'   => $p->id,
        'name' => $p->name,
        'cost' => (float) $p->buying_price,
    ]);
@endphp
<script>
    var PO_PRODUCTS = @json($poProducts);
    var PO_DEFAULT_VAT_RATE = {{ (float) $defaultVatRate }};

    var poRowIndex = 0;

    function addPoRow(productId, productName, qty, cost) {
        var tbody = document.getElementById('poItemsBody');
        var i     = poRowIndex++;
        var row   = document.createElement('tr');

        // Build product options
        var opts = '<option value="">— Select Product (optional) —</option>';
        PO_PRODUCTS.forEach(function(p) {
            var sel = (productId && p.id == productId) ? ' selected' : '';
            opts += '<option value="' + p.id + '" data-name="' + escHtml(p.name) + '" data-cost="' + p.cost + '"' + sel + '>' + escHtml(p.name) + '</option>';
        });

        row.innerHTML =
            '<td data-label="Product">' +
                '<select name="items[' + i + '][product_id]" class="form-control" style="font-size:0.85rem;" onchange="onPoProductChange(this,' + i + ')">' +
                    opts +
                '</select>' +
            '</td>' +
            '<td data-label="Item Name">' +
                '<input type="text" name="items[' + i + '][product_name]" class="form-control" style="font-size:0.85rem;" value="' + escHtml(productName || '') + '" placeholder="Item name" required id="po_name_' + i + '">' +
            '</td>' +
            '<td data-label="Qty">' +
                '<input type="number" name="items[' + i + '][quantity_ordered]" class="form-control" style="font-size:0.85rem;" value="' + (qty || 1) + '" min="1" required id="po_qty_' + i + '" oninput="updatePoRow(' + i + ')">' +
            '</td>' +
            '<td data-label="Unit Cost">' +
                '<input type="number" name="items[' + i + '][unit_cost]" class="form-control" style="font-size:0.85rem;" value="' + (cost || '') + '" min="0" step="0.01" required id="po_cost_' + i + '" oninput="updatePoRow(' + i + ')">' +
            '</td>' +
            '<td data-label="VAT %">' +
                '<input type="number" name="items[' + i + '][vat_rate]" class="form-control" style="font-size:0.85rem;" value="' + PO_DEFAULT_VAT_RATE + '" min="0" max="100" step="0.01" id="po_vat_' + i + '" oninput="updatePoRow(' + i + ')">' +
            '</td>' +
            '<td data-label="Subtotal">' +
                '<span id="po_sub_' + i + '" style="font-weight:600; color:var(--color-text);">KSh 0.00</span>' +
            '</td>' +
            '<td data-label="Remove">' +
                '<button type="button" onclick="removePoRow(this)" style="background:none;border:none;color:var(--color-danger);cursor:pointer;font-size:1.1rem;" title="Remove">' +
                    '&times;' +
                '</button>' +
            '</td>';

        tbody.appendChild(row);
        updatePoRow(i);
        updatePoSummary();
    }

    function onPoProductChange(sel, i) {
        var opt  = sel.options[sel.selectedIndex];
        var name = opt.getAttribute('data-name') || '';
        var cost = opt.getAttribute('data-cost') || '';
        var nameField = document.getElementById('po_name_' + i);
        var costField = document.getElementById('po_cost_' + i);
        if (nameField && name) nameField.value = name;
        if (costField && cost) costField.value  = cost;
        updatePoRow(i);
    }

    function updatePoRow(i) {
        var qty  = parseFloat(document.getElementById('po_qty_'  + i)?.value) || 0;
        var cost = parseFloat(document.getElementById('po_cost_' + i)?.value) || 0;
        var sub  = qty * cost;
        var el   = document.getElementById('po_sub_' + i);
        if (el) el.textContent = 'KSh ' + sub.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        el?.setAttribute('data-raw', sub);
        updatePoSummary();
    }

    function updatePoSummary() {
        var subtotal = 0, vat = 0;
        document.querySelectorAll('#poItemsBody tr').forEach(function(row) {
            var subEl = row.querySelector('[id^="po_sub_"]');
            var vatEl = row.querySelector('[id^="po_vat_"]');
            var lineSub = subEl ? (parseFloat(subEl.getAttribute('data-raw')) || 0) : 0;
            var lineRate = vatEl ? (parseFloat(vatEl.value) || 0) : 0;
            subtotal += lineSub;
            vat += lineSub * lineRate / 100;
        });
        var fmt = function(n) { return 'KSh ' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); };
        document.getElementById('poDisplaySubtotal').textContent = fmt(subtotal);
        document.getElementById('poDisplayVat').textContent      = fmt(vat);
        document.getElementById('poVatRow').style.display        = vat > 0 ? '' : 'none';
        document.getElementById('poDisplayTotal').textContent    = fmt(subtotal + vat);
    }

    function removePoRow(btn) {
        btn.closest('tr').remove();
        updatePoSummary();
    }

    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // Seed from a requisition's items when converting one (see the
    // requisition_id hidden field above), otherwise start with one blank row.
    // Computed in a PHP block first — the JSON-output Blade directive's own
    // argument parser can't handle a multi-line expression with this much
    // nested bracket/paren mixing (arrow function + array literal + ?-> +
    // ??), and threw "Unclosed '['" on every single load of this page once
    // this was added inline.
    @php
        // PurchaseRequisitionItem.quantity is decimal(10,2) (e.g. "1.00"),
        // but purchases.store() requires items.*.quantity_ordered to be a
        // strict integer — submitting the raw decimal value failed
        // validation with "must be an integer" on every conversion.
        $poRequisitionItems = $requisition?->items->map(fn($i) => [
            'product_id' => $i->product_id,
            'description' => $i->description,
            'quantity' => max(1, (int) round($i->quantity)),
            'unit_price' => $i->estimated_unit_price,
        ]) ?? [];
    @endphp
    var PO_REQUISITION_ITEMS = @json($poRequisitionItems);

    if (PO_REQUISITION_ITEMS.length > 0) {
        PO_REQUISITION_ITEMS.forEach(function(it) {
            addPoRow(it.product_id, it.description, it.quantity, it.unit_price);
        });
    } else {
        addPoRow();
    }
</script>
@endpush
