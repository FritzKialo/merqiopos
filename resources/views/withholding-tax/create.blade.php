@extends('layouts.app')
@section('title', 'Record Withholding Tax')
@push('styles')
<style>
@media (max-width: 640px) {
    .wht-form-grid-2, .wht-form-grid-3 { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page-header"><h1>Record Withholding Tax</h1></div>
<div class="card" style="max-width:700px;">
<div class="card-body">
<p style="color:#555;margin-bottom:16px;font-size:0.9rem;">WHT is deducted from payments made to suppliers/consultants and remitted to KRA. Enter the gross payment amount — the WHT amount will be calculated automatically.</p>
<form method="POST" action="{{ route('withholding-tax.store') }}">
@csrf
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="wht-form-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
<div>
<label class="form-label">Supplier (optional)</label>
<select name="supplier_id" class="form-control" id="supplier-select">
    <option value="">— Not a supplier —</option>
    @foreach($suppliers as $s)<option value="{{ $s->id }}" data-name="{{ $s->name }}" data-pin="{{ $s->kra_pin ?? '' }}">{{ $s->name }}</option>@endforeach
</select>
</div>
<div>
<label class="form-label">Payee Name *</label>
<input type="text" name="payee_name" id="payee-name" class="form-control" value="{{ old('payee_name') }}" required>
</div>
</div>
<div class="wht-form-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
<div>
<label class="form-label">Payee KRA PIN</label>
<input type="text" name="payee_kra_pin" id="payee-pin" class="form-control" value="{{ old('payee_kra_pin') }}" placeholder="A123456789B">
</div>
<div>
<label class="form-label">Payment Date *</label>
<input type="date" name="payment_date" class="form-control" value="{{ old('payment_date', date('Y-m-d')) }}" required>
</div>
</div>
<div style="margin-bottom:16px;">
<label class="form-label">Payment Type *</label>
<select name="wht_type" id="wht-type" class="form-control" required>
    @foreach($rates as $key => $info)
    <option value="{{ $key }}" data-rate="{{ $info['rate'] }}" @selected(old('wht_type')===$key)>{{ $info['label'] }} ({{ $info['rate'] }}%)</option>
    @endforeach
</select>
</div>
<div class="wht-form-grid-3" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px;">
<div>
<label class="form-label">Gross Amount (KSh) *</label>
<input type="number" name="gross_amount" id="gross" class="form-control" step="0.01" min="0" value="{{ old('gross_amount') }}" required>
</div>
<div>
<label class="form-label">WHT Rate %</label>
<input type="number" name="wht_rate" id="wht-rate" class="form-control" step="0.01" min="0" max="100" value="{{ old('wht_rate', 5) }}" required>
</div>
<div>
<label class="form-label">WHT Amount</label>
<input type="text" id="wht-preview" class="form-control" disabled style="background:#f5f5f5;" value="0.00">
</div>
</div>
<div class="wht-form-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
<div>
<label class="form-label">Certificate Number (optional)</label>
<input type="text" name="certificate_number" class="form-control" value="{{ old('certificate_number') }}" placeholder="WHT certificate ref">
</div>
<div>
<label class="form-label">Notes</label>
<input type="text" name="notes" class="form-control" value="{{ old('notes') }}">
</div>
</div>
<div style="display:flex;gap:12px;">
<button type="submit" class="btn btn-primary">Save WHT Record</button>
<a href="{{ route('withholding-tax.index') }}" class="btn btn-secondary">Cancel</a>
</div>
</form>
</div>
</div>
<script>
function calcWht() {
    const gross = parseFloat(document.getElementById('gross').value)||0;
    const rate  = parseFloat(document.getElementById('wht-rate').value)||0;
    document.getElementById('wht-preview').value = (gross * rate / 100).toFixed(2);
}
document.getElementById('gross').addEventListener('input', calcWht);
document.getElementById('wht-rate').addEventListener('input', calcWht);
document.getElementById('wht-type').addEventListener('change', function() {
    const rate = this.selectedOptions[0].dataset.rate;
    document.getElementById('wht-rate').value = rate;
    calcWht();
});
document.getElementById('supplier-select').addEventListener('change', function() {
    const opt = this.selectedOptions[0];
    if (opt.value) {
        document.getElementById('payee-name').value = opt.dataset.name;
        document.getElementById('payee-pin').value  = opt.dataset.pin;
    }
});
</script>
@endsection
