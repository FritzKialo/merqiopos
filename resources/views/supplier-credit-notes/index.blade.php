@extends('layouts.app')
@section('title', 'Supplier Credit Notes')
@push('styles')
<style>
@media (min-width: 769px) {
    .scn-index-table th, .scn-index-table td { padding: 12px 16px; }
}
</style>
@endpush
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h1>Supplier Credit Notes</h1>
    <a href="{{ route('supplier-credit-notes.create') }}" class="btn btn-primary">+ New Credit Note</a>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="card">
<div class="card-body" style="padding:0;">
<div class="table-wrapper">
<table class="scn-index-table" style="width:100%;border-collapse:collapse;">
<thead><tr style="border-bottom:1px solid var(--color-border);background:var(--color-surface-2);">
    <th style="text-align:left;">Number</th>
    <th style="text-align:left;">Supplier</th>
    <th style="text-align:left;">Date</th>
    <th style="text-align:left;">Reason</th>
    <th style="text-align:right;">Amount</th>
    <th style="text-align:left;">Status</th>
    <th style="text-align:right;">Actions</th>
</tr></thead>
<tbody>
@forelse($scns as $scn)
<tr style="border-bottom:1px solid var(--color-border);">
    <td data-label="Number" style="font-family:monospace;">{{ $scn->credit_number }}</td>
    <td data-label="Supplier">{{ $scn->supplier?->name ?? '—' }}</td>
    <td data-label="Date">{{ $scn->issue_date?->format('d M Y') }}</td>
    <td data-label="Reason">{{ ucfirst($scn->reason) }}</td>
    <td data-label="Amount" style="text-align:right;">KSh {{ number_format($scn->amount, 2) }}</td>
    <td data-label="Status">
        @php $badgeClasses=['pending'=>'badge-warning','applied'=>'badge-success','cancelled'=>'badge-danger']; @endphp
        <span class="badge {{ $badgeClasses[$scn->status] ?? 'badge-secondary' }}">{{ ucfirst($scn->status) }}</span>
    </td>
    <td data-label="Actions" style="text-align:right;">
        <a href="{{ route('supplier-credit-notes.show', $scn) }}" class="btn btn-secondary" style="padding:4px 10px;font-size:0.8rem;">View</a>
    </td>
</tr>
@empty
<tr><td colspan="7" class="text-muted" style="padding:40px;text-align:center;">No supplier credit notes yet.</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>
</div>
{{ $scns->links() }}
@endsection
