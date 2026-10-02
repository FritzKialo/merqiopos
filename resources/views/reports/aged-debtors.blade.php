@extends('layouts.app')
@section('title', 'Aged Debtors Report')
@push('styles')
<style>
@media (min-width: 769px) {
    .ad-table th, .ad-table td { padding: 0.75rem 1rem; }
}
@media (max-width: 700px) {
    .ad-buckets-grid { grid-template-columns: repeat(2, 1fr) !important; }
}
@media (max-width: 420px) {
    .ad-buckets-grid { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Aged Debtors Report</h1>
            <p class="page-subtitle">Outstanding customer balances by age</p>
        </div>
        <div>
            <a href="{{ route('reports.aged-debtors', ['export' => 'csv']) }}" class="btn btn-secondary">Export CSV</a>
        </div>
    </div>

    {{-- Summary Buckets --}}
    <div class="ad-buckets-grid" style="display:grid;grid-template-columns:repeat(5,1fr);gap:1rem;margin-bottom:1.5rem;">
        <div class="card" style="padding:1.25rem;text-align:center;">
            <div style="font-size:0.75rem;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.4rem;">0–30 Days</div>
            <div style="font-size:1.4rem;font-weight:700;color:var(--color-text);">KES {{ number_format($totals['b0_30'], 2) }}</div>
        </div>
        <div class="card" style="padding:1.25rem;text-align:center;border-top:3px solid #f59e0b;">
            <div style="font-size:0.75rem;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.4rem;">31–60 Days</div>
            <div style="font-size:1.4rem;font-weight:700;color:#f59e0b;">KES {{ number_format($totals['b31_60'], 2) }}</div>
        </div>
        <div class="card" style="padding:1.25rem;text-align:center;border-top:3px solid #f97316;">
            <div style="font-size:0.75rem;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.4rem;">61–90 Days</div>
            <div style="font-size:1.4rem;font-weight:700;color:#f97316;">KES {{ number_format($totals['b61_90'], 2) }}</div>
        </div>
        <div class="card" style="padding:1.25rem;text-align:center;border-top:3px solid var(--color-danger);">
            <div style="font-size:0.75rem;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.4rem;">90+ Days</div>
            <div style="font-size:1.4rem;font-weight:700;color:var(--color-danger);">KES {{ number_format($totals['b90plus'], 2) }}</div>
        </div>
        <div class="card" style="padding:1.25rem;text-align:center;border-top:3px solid var(--color-primary);">
            <div style="font-size:0.75rem;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.4rem;">Grand Total</div>
            <div style="font-size:1.4rem;font-weight:700;">KES {{ number_format($totals['total'], 2) }}</div>
        </div>
    </div>

    {{-- Debtors Table --}}
    <div class="card">
        @if(empty($debtors))
            <div style="padding:3rem;text-align:center;color:var(--color-text-muted);">No outstanding debts found.</div>
        @else
        <table class="ad-table" style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:var(--color-surface);border-bottom:2px solid var(--color-border);">
                    <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Customer</th>
                    <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Phone</th>
                    <th style="text-align:right;font-size:0.8rem;text-transform:uppercase;">0–30 Days</th>
                    <th style="text-align:right;font-size:0.8rem;text-transform:uppercase;color:#f59e0b;">31–60 Days</th>
                    <th style="text-align:right;font-size:0.8rem;text-transform:uppercase;color:#f97316;">61–90 Days</th>
                    <th style="text-align:right;font-size:0.8rem;text-transform:uppercase;color:var(--color-danger);">90+ Days</th>
                    <th style="text-align:right;font-size:0.8rem;text-transform:uppercase;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($debtors as $d)
                @php
                    $rowColor = '';
                    if ($d['b90plus'] > 0) $rowColor = 'background:rgba(239,68,68,0.04);';
                    elseif ($d['b61_90'] > 0) $rowColor = 'background:rgba(249,115,22,0.04);';
                    elseif ($d['b31_60'] > 0) $rowColor = 'background:rgba(245,158,11,0.04);';
                @endphp
                <tr style="border-bottom:1px solid var(--color-border);{{ $rowColor }}">
                    <td data-label="Customer">
                        <strong>{{ $d['customer']?->name ?? 'Walk-in' }}</strong>
                        @if($d['days_overdue'] > 0)
                            <br><span style="font-size:0.75rem;color:var(--color-danger);">{{ $d['days_overdue'] }} days overdue</span>
                        @endif
                    </td>
                    <td data-label="Phone" style="font-size:0.85rem;color:var(--color-text-muted);">{{ $d['customer']?->phone ?? '—' }}</td>
                    <td data-label="0–30 Days" style="text-align:right;font-size:0.9rem;">
                        @if($d['b0_30'] > 0) KES {{ number_format($d['b0_30'], 2) }} @else — @endif
                    </td>
                    <td data-label="31–60 Days" style="text-align:right;font-size:0.9rem;color:{{ $d['b31_60'] > 0 ? '#f59e0b' : 'inherit' }};">
                        @if($d['b31_60'] > 0) KES {{ number_format($d['b31_60'], 2) }} @else — @endif
                    </td>
                    <td data-label="61–90 Days" style="text-align:right;font-size:0.9rem;color:{{ $d['b61_90'] > 0 ? '#f97316' : 'inherit' }};">
                        @if($d['b61_90'] > 0) KES {{ number_format($d['b61_90'], 2) }} @else — @endif
                    </td>
                    <td data-label="90+ Days" style="text-align:right;font-size:0.9rem;font-weight:{{ $d['b90plus'] > 0 ? '700' : 'normal' }};color:{{ $d['b90plus'] > 0 ? 'var(--color-danger)' : 'inherit' }};">
                        @if($d['b90plus'] > 0) KES {{ number_format($d['b90plus'], 2) }} @else — @endif
                    </td>
                    <td data-label="Total" style="text-align:right;font-weight:700;">
                        KES {{ number_format($d['total'], 2) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="border-top:2px solid var(--color-border);background:var(--color-surface);">
                    <td colspan="2" style="font-weight:700;font-size:0.9rem;">TOTALS</td>
                    <td data-label="0–30 Days" style="text-align:right;font-weight:700;">KES {{ number_format($totals['b0_30'], 2) }}</td>
                    <td data-label="31–60 Days" style="text-align:right;font-weight:700;color:#f59e0b;">KES {{ number_format($totals['b31_60'], 2) }}</td>
                    <td data-label="61–90 Days" style="text-align:right;font-weight:700;color:#f97316;">KES {{ number_format($totals['b61_90'], 2) }}</td>
                    <td data-label="90+ Days" style="text-align:right;font-weight:700;color:var(--color-danger);">KES {{ number_format($totals['b90plus'], 2) }}</td>
                    <td data-label="Total" style="text-align:right;font-weight:700;">KES {{ number_format($totals['total'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
        @endif
    </div>
</div>
@endsection
