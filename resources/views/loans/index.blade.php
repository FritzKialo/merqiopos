@extends('layouts.app')
@section('title', 'Business Loans')
@push('styles')
<style>
@media (max-width: 640px) {
    .loans-stats-grid { grid-template-columns: 1fr !important; }
}
@media (min-width: 769px) {
    .loans-table th, .loans-table td { padding: 10px 12px; }
}
</style>
@endpush
@section('content')
<div class="page-header">
    <h1>Business Loans</h1>
    <a href="{{ route('loans.create') }}" class="btn btn-primary">+ Add Loan</a>
</div>

<div class="loans-stats-grid" style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-label">Total Borrowed</div>
        <div class="stat-value">KSh {{ number_format($loans->sum('principal'), 0) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Outstanding Balance</div>
        <div class="stat-value">KSh {{ number_format($loans->sum('balance'), 0) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Active Loans</div>
        <div class="stat-value">{{ $loans->where('status','active')->count() }}</div>
    </div>
</div>

@if($loans->isEmpty())
<div class="empty-state"><p>No loans recorded.</p></div>
@else
<div class="card">
    <div class="card-body">
        <table class="loans-table" style="width:100%; border-collapse:collapse;">
            <thead><tr style="border-bottom:2px solid var(--color-text);">
                <th style="text-align:left;">Lender</th>
                <th style="text-align:right;">Principal</th>
                <th style="text-align:right;">Balance</th>
                <th style="text-align:right;">Rate</th>
                <th style="text-align:left;">Due Date</th>
                <th style="text-align:left;">Status</th>
                <th></th>
            </tr></thead>
            <tbody>
                @foreach($loans as $loan)
                <tr style="border-bottom:1px solid var(--color-border);">
                    <td data-label="Lender">{{ $loan->lender_name }}</td>
                    <td data-label="Principal" style="text-align:right;">KSh {{ number_format($loan->principal, 0) }}</td>
                    <td data-label="Balance" style="text-align:right;">KSh {{ number_format($loan->balance, 0) }}</td>
                    <td data-label="Rate" style="text-align:right;">{{ $loan->interest_rate }}%</td>
                    <td data-label="Due Date" style="font-size:0.9rem;">{{ $loan->due_date ? $loan->due_date->format('d M Y') : '—' }}</td>
                    <td data-label="Status">
                        @if($loan->isOverdue())<span class="badge badge-danger">Overdue</span>
                        @elseif($loan->status === 'active')<span class="badge badge-success">Active</span>
                        @else<span class="badge badge-secondary">{{ ucfirst(str_replace('_',' ',$loan->status)) }}</span>@endif
                    </td>
                    <td data-label="Actions"><a href="{{ route('loans.show', $loan) }}" class="btn btn-secondary btn-sm">View</a></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
