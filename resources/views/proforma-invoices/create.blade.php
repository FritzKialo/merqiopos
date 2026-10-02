@extends('layouts.app')
@section('title', 'New Proforma Invoice')
@push('styles')
<style>
@media (min-width: 769px) {
    .pf-create-table th, .pf-create-table td { padding: 8px; }
    .pf-create-table tbody td { padding: 4px; }
}
@media (max-width: 900px) {
    .pf-create-layout { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page-header"><h1>New Proforma Invoice</h1></div>

@if($errors->any())
<div class="alert alert-danger">
    <ul style="margin:0;padding-left:20px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<form method="POST" action="{{ route('proforma-invoices.store') }}">
@csrf
<div class="pf-create-layout" style="display:grid;grid-template-columns:2fr 1fr;gap:24px;max-width:1100px;">
<div>
<div class="card">
<div class="card-body">
<h3 style="margin:0 0 16px;">Line Items</h3>
<table class="pf-create-table" style="width:100%;border-collapse:collapse;" id="items-table">
<thead><tr style="border-bottom:1px solid #eee;">
    <th style="text-align:left;">Description</th>
    <th style="text-align:right;width:80px;">Qty</th>
    <th style="text-align:right;width:120px;">Unit Price</th>
    <th style="text-align:right;width:80px;">Tax %</th>
    <th style="text-align:right;width:120px;">Total</th>
    <th style="width:40px;"></th>
</tr></thead>
<tbody id="items-body">
<tr class="item-row">
    <td data-label="Description"><input type="text" name="items[0][description]" class="form-control" required placeholder="Item description"></td>
    <td data-label="Qty"><input type="number" name="items[0][quantity]" class="form-control item-qty" step="0.01" min="0.01" value="1" required></td>
    <td data-label="Unit Price"><input type="number" name="items[0][unit_price]" class="form-control item-price" step="0.01" min="0" value="0" required></td>
    <td data-label="Tax %"><input type="number" name="items[0][tax_rate]" class="form-control item-tax" step="0.01" min="0" max="100" value="0"></td>
    <td data-label="Total" style="text-align:right;" class="item-total">0.00</td>
    <td data-label=""><button type="button" onclick="removeRow(this)" style="background:none;border:none;color:#dc3545;cursor:pointer;font-size:1.2rem;">×</button></td>
</tr>
</tbody>
</table>
<button type="button" id="add-item" class="btn btn-secondary" style="margin-top:12px;">+ Add Line</button>
</div>
</div>

<div class="card" style="margin-top:16px;">
<div class="card-body">
<label class="form-label">Notes</label>
<textarea name="notes" class="form-control" rows="3" placeholder="Internal notes or terms...">{{ old('notes') }}</textarea>
</div>
</div>
</div>

<div>
<div class="card">
<div class="card-body">
<div style="margin-bottom:16px;">
<label class="form-label">Customer</label>
<select name="customer_id" class="form-control">
    <option value="">— No customer —</option>
    @foreach($customers as $c)
    <option value="{{ $c->id }}" {{ old('customer_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
    @endforeach
</select>
</div>
<div style="margin-bottom:16px;">
<label class="form-label">Issue Date *</label>
<input type="date" name="issue_date" class="form-control" value="{{ old('issue_date', date('Y-m-d')) }}" required>
</div>
<div style="margin-bottom:16px;">
<label class="form-label">Valid Until</label>
<input type="date" name="valid_until" class="form-control" value="{{ old('valid_until') }}">
</div>
<hr>
<div style="display:flex;justify-content:space-between;margin-bottom:8px;"><span>Subtotal</span><span id="show-subtotal">KSh 0.00</span></div>
<div style="display:flex;justify-content:space-between;margin-bottom:8px;"><span>Tax</span><span id="show-tax">KSh 0.00</span></div>
<div style="display:flex;justify-content:space-between;font-weight:700;font-size:1.1rem;"><span>Total</span><span id="show-total">KSh 0.00</span></div>
<hr>
<button type="submit" class="btn btn-primary" style="width:100%;">Save Proforma</button>
<a href="{{ route('proforma-invoices.index') }}" class="btn btn-secondary" style="width:100%;margin-top:8px;text-align:center;display:block;">Cancel</a>
</div>
</div>
</div>
</div>
</form>

<script>
let rowIndex = 1;

function removeRow(btn) {
    const rows = document.querySelectorAll('.item-row');
    if (rows.length > 1) { btn.closest('tr').remove(); calcTotals(); }
}

function calcTotals() {
    let sub = 0, tax = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const q = parseFloat(row.querySelector('.item-qty').value) || 0;
        const p = parseFloat(row.querySelector('.item-price').value) || 0;
        const t = parseFloat(row.querySelector('.item-tax').value) || 0;
        const line = q * p;
        const lineTax = line * t / 100;
        sub += line; tax += lineTax;
        row.querySelector('.item-total').textContent = (line + lineTax).toFixed(2);
    });
    document.getElementById('show-subtotal').textContent = 'KSh ' + sub.toFixed(2);
    document.getElementById('show-tax').textContent = 'KSh ' + tax.toFixed(2);
    document.getElementById('show-total').textContent = 'KSh ' + (sub + tax).toFixed(2);
}

document.getElementById('add-item').addEventListener('click', function () {
    const body = document.getElementById('items-body');
    const tpl  = body.querySelector('.item-row').cloneNode(true);
    tpl.querySelectorAll('input').forEach(inp => {
        inp.name = inp.name.replace(/\[\d+\]/, '[' + rowIndex + ']');
        if (inp.classList.contains('item-qty'))   inp.value = '1';
        if (inp.classList.contains('item-price')) inp.value = '0';
        if (inp.classList.contains('item-tax'))   inp.value = '0';
        if (inp.type === 'text') inp.value = '';
    });
    tpl.querySelector('.item-total').textContent = '0.00';
    body.appendChild(tpl);
    rowIndex++;
});

document.addEventListener('input', function (e) {
    if (e.target.classList.contains('item-qty') ||
        e.target.classList.contains('item-price') ||
        e.target.classList.contains('item-tax')) {
        calcTotals();
    }
});
</script>
@endsection
