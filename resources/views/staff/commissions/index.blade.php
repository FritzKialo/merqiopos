@extends('layouts.app')
@section('title', 'Commission Rules')
@push('styles')
<style>
@media (min-width: 769px) {
    .commission-rules-table th, .commission-rules-table td { padding: 12px 16px; }
}
</style>
@endpush
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
    <h1>Staff Commission Rules</h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="{{ route('staff.commissions.earnings') }}" class="btn btn-secondary">View Earnings</a>
        <a href="{{ route('staff.commissions.create') }}" class="btn btn-primary">+ New Rule</a>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card">
<div class="card-body" style="padding:0;">
<div class="table-wrapper">
<table class="commission-rules-table" style="width:100%;border-collapse:collapse;">
<thead><tr style="border-bottom:1px solid var(--color-border);background:var(--color-surface-2);">
    <th style="text-align:left;">Employee</th>
    <th style="text-align:left;">Rule Type</th>
    <th style="text-align:right;">Rate</th>
    <th style="text-align:right;">Min Sales</th>
    <th style="text-align:center;">Active</th>
    <th style="text-align:right;">Actions</th>
</tr></thead>
<tbody>
@forelse($rules as $rule)
<tr style="border-bottom:1px solid var(--color-border);">
    <td data-label="Employee">{{ $rule->staffProfile?->user?->name ?? '—' }}</td>
    <td data-label="Rule Type">{{ $rule->rule_type === 'percentage' ? 'Percentage' : 'Flat per Sale' }}</td>
    <td data-label="Rate" style="text-align:right;">
        @if($rule->rule_type === 'percentage')
            {{ number_format($rule->rate, 2) }}%
        @else
            KSh {{ number_format($rule->rate, 2) }}
        @endif
    </td>
    <td data-label="Min Sales" style="text-align:right;">
        {{ $rule->min_sales_amount ? 'KSh '.number_format($rule->min_sales_amount, 2) : '—' }}
    </td>
    <td data-label="Active" style="text-align:center;">
        <span class="badge {{ $rule->is_active ? 'badge-success' : 'badge-danger' }}">
            {{ $rule->is_active ? 'Active' : 'Inactive' }}
        </span>
    </td>
    <td data-label="Actions" style="text-align:right;">
        <form method="POST" action="{{ route('staff.commissions.update', $rule) }}" style="display:inline;" id="form-toggle-{{ $rule->id }}">
            @csrf @method('PUT')
            <input type="hidden" name="rule_type" value="{{ $rule->rule_type }}">
            <input type="hidden" name="rate" value="{{ $rule->rate }}">
            <input type="hidden" name="min_sales_amount" value="{{ $rule->min_sales_amount }}">
            <input type="hidden" name="is_active" value="{{ $rule->is_active ? 0 : 1 }}">
            <button type="submit" class="btn btn-secondary" style="padding:4px 10px;font-size:0.8rem;">
                {{ $rule->is_active ? 'Deactivate' : 'Activate' }}
            </button>
        </form>
        <form method="POST" action="{{ route('staff.commissions.destroy', $rule) }}" style="display:inline;"
            onsubmit="return confirm('Delete this rule?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger" style="padding:4px 10px;font-size:0.8rem;">Delete</button>
        </form>
    </td>
</tr>
@empty
<tr><td colspan="6" class="text-muted" style="padding:40px;text-align:center;">No commission rules yet. <a href="{{ route('staff.commissions.create') }}">Create one</a>.</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>
</div>
{{ $rules->links() }}
@endsection
