@extends('layouts.app')
@section('title', 'Proforma Invoices')
@push('styles')
<style>
@media (min-width: 769px) {
    .pf-index-table th, .pf-index-table td { padding: 12px 16px; }
}
</style>
@endpush
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h1>Proforma Invoices</h1>
    <a href="{{ route('proforma-invoices.create') }}" class="btn btn-primary">+ New Proforma</a>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<div class="card">
<div class="card-body" style="padding:0;">
<table class="pf-index-table" style="width:100%;border-collapse:collapse;">
<thead><tr style="border-bottom:1px solid var(--color-border);background:var(--color-surface-2);">
    <th style="text-align:left;">Number</th>
    <th style="text-align:left;">Customer</th>
    <th style="text-align:left;">Date</th>
    <th style="text-align:left;">Valid Until</th>
    <th style="text-align:right;">Amount</th>
    <th style="text-align:left;">Status</th>
    <th style="text-align:right;">Actions</th>
</tr></thead>
<tbody>
@forelse($proformas as $p)
<tr style="border-bottom:1px solid var(--color-border);">
    <td data-label="Number" style="font-family:monospace;">{{ $p->proforma_number }}</td>
    <td data-label="Customer">{{ $p->customer?->name ?? '—' }}</td>
    <td data-label="Date">{{ $p->issue_date?->format('d M Y') }}</td>
    <td data-label="Valid Until">{{ $p->valid_until?->format('d M Y') ?? '—' }}</td>
    <td data-label="Amount" style="text-align:right;">KSh {{ number_format($p->total_amount, 2) }}</td>
    <td data-label="Status">
        @php $badgeClasses=['draft'=>'badge-secondary','sent'=>'badge-blue','accepted'=>'badge-success','rejected'=>'badge-danger','converted'=>'badge-purple']; @endphp
        <span class="badge {{ $badgeClasses[$p->status] ?? 'badge-secondary' }}">{{ ucfirst($p->status) }}</span>
    </td>
    <td data-label="Actions" style="text-align:right;">
        <a href="{{ route('proforma-invoices.show', $p) }}" class="btn btn-secondary" style="padding:4px 10px;font-size:0.8rem;">View</a>
    </td>
</tr>
@empty
<tr><td colspan="7" class="text-muted" style="padding:40px;text-align:center;">No proforma invoices yet.</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>
{{ $proformas->links() }}
@endsection
