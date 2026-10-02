@extends('layouts.app')
@section('title', 'New Commission Rule')
@section('content')
<div class="page-header">
    <h1>New Commission Rule</h1>
</div>

<div class="card" style="max-width:600px;">
<div class="card-body">
<form method="POST" action="{{ route('staff.commissions.store') }}">
@csrf

<div class="form-group" style="margin-bottom:16px;">
    <label style="display:block;font-weight:500;margin-bottom:4px;">Employee <span style="color:red;">*</span></label>
    <select name="staff_profile_id" class="form-control" required>
        <option value="">-- Select Employee --</option>
        @foreach($staffProfiles as $sp)
        <option value="{{ $sp->id }}" @selected(old('staff_profile_id') == $sp->id)>
            {{ $sp->user->name }} ({{ $sp->job_title ?? $sp->pay_type }})
        </option>
        @endforeach
    </select>
    @error('staff_profile_id')<span style="color:red;font-size:0.85rem;">{{ $message }}</span>@enderror
</div>

<div class="form-group" style="margin-bottom:16px;">
    <label style="display:block;font-weight:500;margin-bottom:4px;">Rule Type <span style="color:red;">*</span></label>
    <select name="rule_type" class="form-control" required>
        <option value="percentage" @selected(old('rule_type','percentage')=='percentage')>Percentage of Sales</option>
        <option value="flat_per_sale" @selected(old('rule_type')=='flat_per_sale')>Flat Amount per Period</option>
    </select>
</div>

<div class="form-group" style="margin-bottom:16px;">
    <label style="display:block;font-weight:500;margin-bottom:4px;">Rate <span style="color:red;">*</span></label>
    <input type="number" name="rate" class="form-control" step="0.0001" min="0"
        value="{{ old('rate') }}" required placeholder="e.g. 5 for 5% or 500 for flat KSh">
    <small style="color:#888;">Enter percentage (e.g. 5.00) or flat KES amount per period.</small>
    @error('rate')<span style="color:red;font-size:0.85rem;">{{ $message }}</span>@enderror
</div>

<div class="form-group" style="margin-bottom:16px;">
    <label style="display:block;font-weight:500;margin-bottom:4px;">Minimum Sales Amount (optional)</label>
    <input type="number" name="min_sales_amount" class="form-control" step="0.01" min="0"
        value="{{ old('min_sales_amount') }}" placeholder="Leave blank for no minimum">
    <small style="color:#888;">Commission only applies if staff reaches this monthly sales threshold.</small>
</div>

<div class="form-group" style="margin-bottom:24px;">
    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
        <span style="font-weight:500;">Active</span>
    </label>
</div>

<div style="display:flex;gap:8px;">
    <button type="submit" class="btn btn-primary">Create Rule</button>
    <a href="{{ route('staff.commissions.index') }}" class="btn btn-secondary">Cancel</a>
</div>
</form>
</div>
</div>
@endsection
