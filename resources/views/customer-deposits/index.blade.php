@extends('layouts.app')
@section('title', 'Customer Deposits')
@push('styles')
<style>
@media (min-width: 769px) {
    .cd-table th, .cd-table td { padding: 12px 16px; }
}
</style>
@endpush
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h1>Customer Deposits</h1>
    <a href="{{ route('customer-deposits.create') }}" class="btn btn-primary">+ New Deposit</a>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<div class="card" style="margin-bottom:16px;">
<div class="card-body">
<form method="GET" style="display:flex;gap:12px;align-items:flex-end;">
    <div>
        <label class="form-label">Filter by Customer</label>
        <select name="customer_id" class="form-control" onchange="this.form.submit()">
            <option value="">— All Customers —</option>
            @foreach($customers as $c)
            <option value="{{ $c->id }}" {{ request('customer_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
            @endforeach
        </select>
    </div>
    @if(request('customer_id'))
    <a href="{{ route('customer-deposits.index') }}" class="btn btn-secondary">Clear</a>
    @endif
</form>
</div>
</div>

<div class="card">
<div class="card-body" style="padding:0;">
<table class="cd-table" style="width:100%;border-collapse:collapse;">
<thead><tr style="border-bottom:1px solid var(--color-border);background:var(--color-surface-2);">
    <th style="text-align:left;">Reference</th>
    <th style="text-align:left;">Customer</th>
    <th style="text-align:right;">Amount</th>
    <th style="text-align:right;">Available</th>
    <th style="text-align:left;">Method</th>
    <th style="text-align:left;">Date</th>
    <th style="text-align:left;">Status</th>
    <th style="text-align:right;">Actions</th>
</tr></thead>
<tbody>
@forelse($deposits as $d)
@php $available = $d->amount - $d->used_amount; @endphp
<tr style="border-bottom:1px solid var(--color-border);">
    <td data-label="Reference" style="font-family:monospace;font-size:0.9rem;">{{ $d->reference }}</td>
    <td data-label="Customer">{{ $d->customer?->name ?? '—' }}</td>
    <td data-label="Amount" style="text-align:right;">KSh {{ number_format($d->amount, 2) }}</td>
    <td data-label="Available" style="text-align:right;{{ $available > 0 ? 'color:var(--color-success);font-weight:600;' : 'color:var(--color-text-muted);' }}">KSh {{ number_format($available, 2) }}</td>
    <td data-label="Method">{{ ucfirst($d->payment_method) }}</td>
    <td data-label="Date">{{ $d->received_at?->format('d M Y') }}</td>
    <td data-label="Status">
        @php $badgeClasses=['available'=>'badge-success','partially_used'=>'badge-warning','fully_used'=>'badge-secondary']; @endphp
        <span class="badge {{ $badgeClasses[$d->status] ?? 'badge-secondary' }}">{{ str_replace('_',' ',ucfirst($d->status)) }}</span>
    </td>
    <td data-label="Actions" style="text-align:right;">
        <a href="{{ route('customer-deposits.show', $d) }}" class="btn btn-secondary" style="padding:4px 10px;font-size:0.8rem;">View</a>
    </td>
</tr>
@empty
<tr><td colspan="8" class="text-muted" style="padding:40px;text-align:center;">No deposits recorded yet.</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>
{{ $deposits->links() }}
@endsection
