@extends('layouts.app')
@section('title', 'New Supplier Credit Note')
@push('styles')
<style>
@media (max-width: 900px) {
    .scn-form-layout { grid-template-columns: 1fr !important; }
}
@media (min-width: 769px) {
    .scn-items-table th { padding: 8px; }
    .scn-items-table td { padding: 4px; }
}
</style>
@endpush
@section('content')
<div class="page-header"><h1>New Supplier Credit Note</h1></div>

@if($errors->any())
<div class="alert alert-danger">
    <ul style="margin:0;padding-left:20px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<form method="POST" action="{{ route('supplier-credit-notes.store') }}">
@csrf
<div class="scn-form-layout" style="display:grid;grid-template-columns:2fr 1fr;gap:24px;max-width:1100px;">
<div>
<div class="card">
<div class="card-body">
<h3 style="margin:0 0 16px;">Line Items</h3>
<div class="table-wrapper">
<table class="scn-items-table" style="width:100%;border-collapse:collapse;">
<thead><tr style="border-bottom:1px solid #eee;">
    <th style="text-align:left;">Description</th>
    <th style="text-align:right;width:80px;">Qty</th>
    <th style="text-align:right;width:120px;">Unit Price</th>
    <th style="text-align:right;width:120px;">Total</th>
    <th style="width:40px;"></th>
</tr></thead>
<tbody id="items-body">
<tr class="item-row">
    <td data-label="Description"><input type="text" name="items[0][description]" class="form-control" required placeholder="Item description"></td>
    <td data-label="Qty"><input type="number" name="items[0][quantity]" class="form-control item-qty" step="0.01" min="0.01" value="1" required></td>
    <td data-label="Unit Price"><input type="number" name="items[0][unit_price]" class="form-control item-price" step="0.01" min="0" value="0" required></td>
    <td data-label="Total" style="text-align:right;" class="item-total">0.00</td>
    <td data-label="Remove"><button type="button" onclick="removeRow(this)" style="background:none;border:none;color:#dc3545;cursor:pointer;font-size:1.2rem;">×</button></td>
</tr>
</tbody>
</table>
</div>
<button type="button" id="add-item" class="btn btn-secondary" style="margin-top:12px;">+ Add Line</button>
</div>
</div>

<div class="card" style="margin-top:16px;">
<div class="card-body">
<label class="form-label">Notes</label>
<textarea name="notes" class="form-control" rows="3" placeholder="Additional notes...">{{ old('notes') }}</textarea>
</div>
</div>
</div>

<div>
<div class="card">
<div class="card-body">
<div style="margin-bottom:16px;">
<label class="form-label">Supplier</label>
<select name="supplier_id" class="form-control">
    <option value="">— No Supplier —</option>
    @foreach($suppliers as $s)
    <option value="{{ $s->id }}" {{ old('supplier_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
    @endforeach
</select>
</div>
<div style="margin-bottom:16px;">
<label class="form-label">Issue Date *</label>
<input type="date" name="issue_date" class="form-control" value="{{ old('issue_date', date('Y-m-d')) }}" required>
</div>
<div style="margin-bottom:16px;">
<label class="form-label">Reason *</label>
<select name="reason" class="form-control" required>
    <option value="return" {{ old('reason') == 'return' ? 'selected' : '' }}>Return</option>
    <option value="overcharge" {{ old('reason') == 'overcharge' ? 'selected' : '' }}>Overcharge</option>
    <option value="damaged" {{ old('reason') == 'damaged' ? 'selected' : '' }}>Damaged Goods</option>
    <option value="other" {{ old('reason') == 'other' ? 'selected' : '' }}>Other</option>
</select>
</div>
<hr>
<div style="display:flex;justify-content:space-between;font-weight:700;font-size:1.1rem;margin-bottom:16px;"><span>Total</span><span id="show-total">KSh 0.00</span></div>
<button type="submit" class="btn btn-primary" style="width:100%;">Save Credit Note</button>
<a href="{{ route('supplier-credit-notes.index') }}" class="btn btn-secondary" style="width:100%;margin-top:8px;text-align:center;display:block;">Cancel</a>
</div>
</div>
</div>
</div>
</form>

<script>
let rowIndex = 1;

function removeRow(btn) {
    const rows = document.querySelectorAll('.item-row');
    if (rows.length > 1) { btn.closest('tr').remove(); calcTotal(); }
}

function calcTotal() {
    let total = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const q = parseFloat(row.querySelector('.item-qty').value) || 0;
        const p = parseFloat(row.querySelector('.item-price').value) || 0;
        const line = q * p;
        total += line;
        row.querySelector('.item-total').textContent = line.toFixed(2);
    });
    document.getElementById('show-total').textContent = 'KSh ' + total.toFixed(2);
}

document.getElementById('add-item').addEventListener('click', function () {
    const body = document.getElementById('items-body');
    const tpl  = body.querySelector('.item-row').cloneNode(true);
    tpl.querySelectorAll('input').forEach(inp => {
        inp.name = inp.name.replace(/\[\d+\]/, '[' + rowIndex + ']');
        if (inp.classList.contains('item-qty'))   inp.value = '1';
        if (inp.classList.contains('item-price')) inp.value = '0';
        if (inp.type === 'text') inp.value = '';
    });
    tpl.querySelector('.item-total').textContent = '0.00';
    body.appendChild(tpl);
    rowIndex++;
});

document.addEventListener('input', function (e) {
    if (e.target.classList.contains('item-qty') || e.target.classList.contains('item-price')) {
        calcTotal();
    }
});
</script>
@endsection
