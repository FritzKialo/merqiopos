@extends('layouts.app')
@section('title', 'New Credit Note')
@push('styles')
<style>
@media (min-width: 769px) {
    .cn-items-table th, .cn-items-table td { padding: 0.4rem; }
}
/* Two inline grids here had no mobile collapse at all — squeezed the
   items/summary layout and the invoice/customer selects into unusably
   narrow columns on a phone. */
@media (max-width: 900px) {
    .cn-create-layout { grid-template-columns: 1fr !important; }
}
@media (max-width: 560px) {
    .cn-create-header-grid { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <div><h1 class="page-title">New Credit Note</h1></div>
        <a href="{{ route('credit-notes.index') }}" class="btn btn-secondary">Cancel</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul style="margin:0;padding-left:1.25rem;">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('credit-notes.store') }}" id="cn-form">
        @csrf

        <div class="cn-create-layout" style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;">
            <div>
                {{-- Header --}}
                <div class="table-card" style="padding:1.25rem;margin-bottom:1rem;">
                    <h3 style="font-weight:700;margin-bottom:1rem;">Details</h3>

                    <div class="cn-create-header-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                        <div class="form-group">
                            <label class="form-label">Invoice (optional)</label>
                            <select name="invoice_id" class="form-control" id="invoice-select">
                                <option value="">— No linked invoice —</option>
                                @foreach($invoices as $inv)
                                    <option value="{{ $inv->id }}"
                                        data-customer="{{ $inv->customer_id }}"
                                        {{ old('invoice_id', $invoice?->id) == $inv->id ? 'selected' : '' }}>
                                        {{ $inv->invoice_number }} — {{ $inv->customer?->name }} (KSh {{ number_format($inv->balance_due, 2) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Customer</label>
                            <select name="customer_id" class="form-control" id="customer-select">
                                <option value="">— No customer —</option>
                                @foreach($customers as $c)
                                    <option value="{{ $c->id }}"
                                        {{ old('customer_id', $invoice?->customer_id) == $c->id ? 'selected' : '' }}>
                                        {{ $c->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top:0.75rem;">
                        <label class="form-label">Reason / Description *</label>
                        <textarea name="reason" class="form-control" rows="3" required
                                  placeholder="Reason for this credit note (e.g. Returned goods, billing error)">{{ old('reason') }}</textarea>
                    </div>
                </div>

                {{-- Items --}}
                <div class="table-card" style="padding:1.25rem;">
                    <h3 style="font-weight:700;margin-bottom:1rem;">Items</h3>

                    <table class="cn-items-table" style="width:100%;border-collapse:collapse;" id="items-table">
                        <thead>
                            <tr style="border-bottom:2px solid var(--color-border);">
                                <th style="text-align:left;font-size:0.8rem;">Description</th>
                                <th style="text-align:right;font-size:0.8rem;width:80px;">Qty</th>
                                <th style="text-align:right;font-size:0.8rem;width:110px;">Unit Price</th>
                                <th style="text-align:right;font-size:0.8rem;width:80px;">VAT%</th>
                                <th style="text-align:right;font-size:0.8rem;width:110px;">Total</th>
                                <th style="width:40px;"></th>
                            </tr>
                        </thead>
                        <tbody id="items-body">
                            <tr class="item-row">
                                <td data-label="Description">
                                    <input type="hidden" name="items[0][product_id]" value="">
                                    <input type="text" name="items[0][description]" class="form-control" required placeholder="Description">
                                </td>
                                <td data-label="Qty">
                                    <input type="number" name="items[0][quantity]" class="form-control item-qty" value="1" min="0.01" step="0.01" required>
                                </td>
                                <td data-label="Unit Price">
                                    <input type="number" name="items[0][unit_price]" class="form-control item-price" value="0" min="0" step="0.01" required>
                                </td>
                                <td data-label="VAT%">
                                    <input type="number" name="items[0][vat_rate]" class="form-control item-vat" value="{{ $business->vatRate() }}" min="0" max="100" step="0.01">
                                </td>
                                <td data-label="Total" style="text-align:right;font-weight:600;" class="item-total">KSh 0.00</td>
                                <td data-label="">
                                    <button type="button" class="btn btn-danger remove-row" style="font-size:0.75rem;padding:0.2rem 0.5rem;">×</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <button type="button" id="add-row" class="btn btn-secondary" style="margin-top:0.75rem;font-size:0.85rem;">+ Add Item</button>
                </div>
            </div>

            {{-- Summary --}}
            <div>
                <div class="table-card" style="padding:1.25rem;">
                    <h3 style="font-weight:700;margin-bottom:1rem;">Summary</h3>
                    <div style="display:flex;flex-direction:column;gap:0.5rem;font-size:0.9rem;">
                        <div style="display:flex;justify-content:space-between;">
                            <span class="text-muted">Subtotal</span>
                            <span id="summary-subtotal">KSh 0.00</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;">
                            <span class="text-muted">VAT</span>
                            <span id="summary-vat">KSh 0.00</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-weight:700;font-size:1.05rem;border-top:1px solid var(--color-border);padding-top:0.5rem;margin-top:0.25rem;">
                            <span>Total</span>
                            <span id="summary-total">KSh 0.00</span>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;margin-top:1.25rem;">Create Credit Note</button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
let rowIndex = 1;

function recalc() {
    let subtotal = 0, vat = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const qty   = parseFloat(row.querySelector('.item-qty')?.value) || 0;
        const price = parseFloat(row.querySelector('.item-price')?.value) || 0;
        const vatR  = parseFloat(row.querySelector('.item-vat')?.value) || 0;
        const sub   = qty * price;
        const lineVat = sub * vatR / 100;
        subtotal += sub;
        vat += lineVat;
        const totalCell = row.querySelector('.item-total');
        if (totalCell) totalCell.textContent = 'KSh ' + (sub + lineVat).toFixed(2);
    });
    document.getElementById('summary-subtotal').textContent = 'KSh ' + subtotal.toFixed(2);
    document.getElementById('summary-vat').textContent = 'KSh ' + vat.toFixed(2);
    document.getElementById('summary-total').textContent = 'KSh ' + (subtotal + vat).toFixed(2);
}

document.getElementById('items-body').addEventListener('input', recalc);

document.getElementById('add-row').addEventListener('click', function () {
    const body = document.getElementById('items-body');
    const row = document.createElement('tr');
    row.className = 'item-row';
    row.innerHTML = `
        <td data-label="Description">
            <input type="hidden" name="items[${rowIndex}][product_id]" value="">
            <input type="text" name="items[${rowIndex}][description]" class="form-control" required placeholder="Description">
        </td>
        <td data-label="Qty">
            <input type="number" name="items[${rowIndex}][quantity]" class="form-control item-qty" value="1" min="0.01" step="0.01" required>
        </td>
        <td data-label="Unit Price">
            <input type="number" name="items[${rowIndex}][unit_price]" class="form-control item-price" value="0" min="0" step="0.01" required>
        </td>
        <td data-label="VAT%">
            <input type="number" name="items[${rowIndex}][vat_rate]" class="form-control item-vat" value="{{ $business->vatRate() }}" min="0" max="100" step="0.01">
        </td>
        <td data-label="Total" style="text-align:right;font-weight:600;" class="item-total">KSh 0.00</td>
        <td data-label="">
            <button type="button" class="btn btn-danger remove-row" style="font-size:0.75rem;padding:0.2rem 0.5rem;">×</button>
        </td>`;
    body.appendChild(row);
    rowIndex++;
});

document.addEventListener('click', function (e) {
    if (e.target.classList.contains('remove-row')) {
        if (document.querySelectorAll('.item-row').length > 1) {
            e.target.closest('tr').remove();
            recalc();
        }
    }
});

// Auto-select customer when invoice is chosen
document.getElementById('invoice-select').addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    const custId = opt.getAttribute('data-customer');
    if (custId) {
        const custSelect = document.getElementById('customer-select');
        for (let o of custSelect.options) {
            if (o.value == custId) { o.selected = true; break; }
        }
    }
});

recalc();
</script>
@endsection
