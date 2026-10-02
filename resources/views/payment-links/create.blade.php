@extends('layouts.app')
@section('title', 'New Payment Link')
@push('styles')
<style>
@media (max-width: 640px) {
    .pl-form-grid { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h1>New Payment Link</h1>
    <a href="{{ route('payment-links.index') }}" class="btn btn-secondary">← Back</a>
</div>
@if($errors->any())<div class="alert alert-danger"><ul style="margin:0;padding-left:20px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ route('payment-links.store') }}">
@csrf
<div class="card">
<div class="card-body">
    <div class="pl-form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
        <div>
            <label style="display:block;font-weight:600;margin-bottom:4px;">Title *</label>
            <input type="text" name="title" class="form-control" value="{{ old('title') }}" required placeholder="e.g. Invoice #101 Payment">
        </div>
        <div>
            <label style="display:block;font-weight:600;margin-bottom:4px;">Amount (KSh) *</label>
            <input type="number" name="amount" class="form-control" value="{{ old('amount') }}" required min="1" step="0.01">
        </div>
    </div>
    <div style="margin-bottom:16px;">
        <label style="display:block;font-weight:600;margin-bottom:4px;">Customer (optional)</label>
        <select name="customer_id" class="form-control">
            <option value="">— None —</option>
            @foreach($customers as $customer)
            <option value="{{ $customer->id }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>
                {{ $customer->name }} {{ $customer->phone ? '('.$customer->phone.')' : '' }}
            </option>
            @endforeach
        </select>
    </div>
    <div style="margin-bottom:16px;">
        <label style="display:block;font-weight:600;margin-bottom:4px;">Description (optional)</label>
        <textarea name="description" class="form-control" rows="3" placeholder="What is this payment for?">{{ old('description') }}</textarea>
    </div>
    <div style="margin-bottom:16px;">
        <label style="display:block;font-weight:600;margin-bottom:4px;">Expires in (hours, optional)</label>
        <input type="number" name="expiry_hours" class="form-control" value="{{ old('expiry_hours') }}" min="1" max="8760" placeholder="Leave blank for no expiry" style="max-width:240px;">
    </div>
</div>
</div>
<div style="margin-top:16px;display:flex;gap:12px;">
    <button type="submit" class="btn btn-primary">Create Payment Link</button>
    <a href="{{ route('payment-links.index') }}" class="btn btn-secondary">Cancel</a>
</div>
</form>
@endsection
