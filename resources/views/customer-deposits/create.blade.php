@extends('layouts.app')
@section('title', 'New Customer Deposit')
@section('content')
<div class="page-header"><h1>New Customer Deposit</h1></div>

@if($errors->any())
<div class="alert alert-danger">
    <ul style="margin:0;padding-left:20px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<div style="max-width:600px;">
<div class="card">
<div class="card-body">
<form method="POST" action="{{ route('customer-deposits.store') }}">
@csrf

<div style="margin-bottom:16px;">
<label class="form-label">Customer</label>
<select name="customer_id" class="form-control">
    <option value="">— Walk-in / No Customer —</option>
    @foreach($customers as $c)
    <option value="{{ $c->id }}" {{ old('customer_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
    @endforeach
</select>
</div>

<div style="margin-bottom:16px;">
<label class="form-label">Amount (KES) *</label>
<input type="number" name="amount" class="form-control" step="0.01" min="0.01" value="{{ old('amount') }}" required placeholder="0.00">
</div>

<div style="margin-bottom:16px;">
<label class="form-label">Payment Method *</label>
<select name="payment_method" class="form-control" id="payment-method" onchange="toggleMpesa(this.value)" required>
    <option value="cash" {{ old('payment_method') == 'cash' ? 'selected' : '' }}>Cash</option>
    <option value="mpesa" {{ old('payment_method') == 'mpesa' ? 'selected' : '' }}>M-Pesa</option>
    <option value="bank" {{ old('payment_method') == 'bank' ? 'selected' : '' }}>Bank Transfer</option>
    <option value="card" {{ old('payment_method') == 'card' ? 'selected' : '' }}>Card</option>
</select>
</div>

<div id="mpesa-section" style="margin-bottom:16px;display:{{ old('payment_method') === 'mpesa' ? 'block' : 'none' }};">
<label class="form-label">M-Pesa Transaction Code</label>
<input type="text" name="mpesa_code" class="form-control" value="{{ old('mpesa_code') }}" placeholder="e.g. QGH7XXXXXB" style="text-transform:uppercase;">
</div>

<div style="margin-bottom:16px;">
<label class="form-label">Date Received *</label>
<input type="date" name="received_at" class="form-control" value="{{ old('received_at', date('Y-m-d')) }}" required>
</div>

<div style="margin-bottom:16px;">
<label class="form-label">Notes</label>
<textarea name="notes" class="form-control" rows="3" placeholder="Optional notes...">{{ old('notes') }}</textarea>
</div>

<div style="display:flex;gap:8px;">
<button type="submit" class="btn btn-primary">Save Deposit</button>
<a href="{{ route('customer-deposits.index') }}" class="btn btn-secondary">Cancel</a>
</div>
</form>
</div>
</div>
</div>

<script>
function toggleMpesa(val) {
    document.getElementById('mpesa-section').style.display = val === 'mpesa' ? 'block' : 'none';
}
</script>
@endsection
