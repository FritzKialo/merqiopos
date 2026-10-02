@extends('layouts.app')
@section('title', 'New Coupon')
@push('styles')
<style>
@media (max-width: 640px) {
    .coupon-form-grid-2, .coupon-form-grid-3 { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h1 class="page-title">New Coupon</h1>
    <a href="{{ route('coupons.index') }}" class="btn btn-secondary">← Back</a>
</div>
@if($errors->any())<div class="alert alert-danger"><ul style="margin:0;padding-left:20px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ route('coupons.store') }}">
@csrf
<div class="card">
<div class="card-body">
    <div class="coupon-form-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
        <div>
            <label style="display:block;font-weight:600;margin-bottom:4px;">Coupon Code *</label>
            <input type="text" name="code" class="form-control" value="{{ old('code') }}" required placeholder="e.g. SAVE20" style="text-transform:uppercase;" oninput="this.value=this.value.toUpperCase()">
        </div>
        <div>
            <label style="display:block;font-weight:600;margin-bottom:4px;">Name / Description *</label>
            <input type="text" name="name" class="form-control" value="{{ old('name') }}" required placeholder="e.g. 20% Off Promotion">
        </div>
    </div>
    <div class="coupon-form-grid-3" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px;">
        <div>
            <label style="display:block;font-weight:600;margin-bottom:4px;">Discount Type *</label>
            <select name="discount_type" class="form-control">
                <option value="percentage" {{ old('discount_type')=='percentage'?'selected':'' }}>Percentage (%)</option>
                <option value="fixed" {{ old('discount_type')=='fixed'?'selected':'' }}>Fixed Amount (KSh)</option>
            </select>
        </div>
        <div>
            <label style="display:block;font-weight:600;margin-bottom:4px;">Discount Value *</label>
            <input type="number" name="discount_value" class="form-control" value="{{ old('discount_value') }}" required min="0" step="0.01" placeholder="e.g. 20">
        </div>
        <div>
            <label style="display:block;font-weight:600;margin-bottom:4px;">Min. Order Amount (KSh)</label>
            <input type="number" name="min_order_amount" class="form-control" value="{{ old('min_order_amount', 0) }}" min="0" step="0.01">
        </div>
    </div>
    <div class="coupon-form-grid-3" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px;">
        <div>
            <label style="display:block;font-weight:600;margin-bottom:4px;">Max Uses (leave blank = unlimited)</label>
            <input type="number" name="max_uses" class="form-control" value="{{ old('max_uses') }}" min="1" placeholder="Unlimited">
        </div>
        <div>
            <label style="display:block;font-weight:600;margin-bottom:4px;">Valid From</label>
            <input type="datetime-local" name="starts_at" class="form-control" value="{{ old('starts_at') }}">
        </div>
        <div>
            <label style="display:block;font-weight:600;margin-bottom:4px;">Expires At</label>
            <input type="datetime-local" name="expires_at" class="form-control" value="{{ old('expires_at') }}">
        </div>
    </div>
    <div>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
            <span style="font-weight:600;">Active (immediately usable)</span>
        </label>
    </div>
</div>
</div>
<div style="margin-top:16px;display:flex;gap:12px;">
    <button type="submit" class="btn btn-primary">Create Coupon</button>
    <a href="{{ route('coupons.index') }}" class="btn btn-secondary">Cancel</a>
</div>
</form>
@endsection
