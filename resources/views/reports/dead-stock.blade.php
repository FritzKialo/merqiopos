@extends('layouts.app')
@section('title', 'Dead Stock Report')
@push('styles')
<style>
@media (min-width: 769px) {
    .ds-table th, .ds-table td { padding: 0.75rem 1rem; }
}
@media (max-width: 480px) {
    .ds-cards-grid { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header" style="flex-wrap:wrap;gap:0.75rem;">
        <div>
            <h1 class="page-title">Dead Stock / Slow-Moving Report</h1>
            <p class="page-subtitle">Products with no sales in the last {{ $days }} days</p>
        </div>
        <div style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
            <form method="GET" style="display:flex;gap:0.5rem;align-items:center;">
                <select name="days" class="form-control" onchange="this.form.submit()">
                    @foreach([30,60,90,180] as $d)
                        <option value="{{ $d }}" {{ $days == $d ? 'selected' : '' }}>{{ $d }} days</option>
                    @endforeach
                </select>
            </form>
            <a href="{{ route('reports.dead-stock', ['days' => $days, 'export' => 'csv']) }}" class="btn btn-secondary">Export CSV</a>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="ds-cards-grid" style="display:grid;grid-template-columns:repeat(2,1fr);gap:1rem;margin-bottom:1.5rem;max-width:600px;">
        <div class="card" style="padding:1.25rem;text-align:center;">
            <div style="font-size:0.75rem;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.4rem;">Dead Stock Products</div>
            <div style="font-size:2rem;font-weight:700;color:var(--color-danger);">{{ $totalCount }}</div>
        </div>
        <div class="card" style="padding:1.25rem;text-align:center;">
            <div style="font-size:0.75rem;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.4rem;">Capital Tied Up</div>
            <div style="font-size:1.4rem;font-weight:700;color:var(--color-warning);">KES {{ number_format($totalCapital, 2) }}</div>
        </div>
    </div>

    <div class="card">
        @if($products->isEmpty())
            <div style="padding:3rem;text-align:center;color:var(--color-text-muted);">No dead stock found for the selected period.</div>
        @else
        <table class="ds-table" style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:var(--color-surface);border-bottom:2px solid var(--color-border);">
                    <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Product</th>
                    <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Category</th>
                    <th style="text-align:right;font-size:0.8rem;text-transform:uppercase;">Stock Qty</th>
                    <th style="text-align:right;font-size:0.8rem;text-transform:uppercase;">Cost Price</th>
                    <th style="text-align:right;font-size:0.8rem;text-transform:uppercase;">Capital Tied Up</th>
                    <th style="text-align:center;font-size:0.8rem;text-transform:uppercase;">Last Sold</th>
                    <th style="text-align:center;font-size:0.8rem;text-transform:uppercase;">Days Since Sale</th>
                </tr>
            </thead>
            <tbody>
                @foreach($products as $p)
                    @php
                        $daysSince = $p->last_sold ? \Carbon\Carbon::parse($p->last_sold)->diffInDays(now()) : null;
                    @endphp
                    <tr style="border-bottom:1px solid var(--color-border);">
                        <td data-label="Product" style="font-weight:500;">{{ $p->name }}</td>
                        <td data-label="Category" style="color:var(--color-text-muted);">{{ $p->category_name ?? '—' }}</td>
                        <td data-label="Stock Qty" style="text-align:right;">{{ number_format($p->stock_qty) }}</td>
                        <td data-label="Cost Price" style="text-align:right;">KES {{ number_format($p->buying_price, 2) }}</td>
                        <td data-label="Capital Tied Up" style="text-align:right;font-weight:600;color:var(--color-warning);">
                            KES {{ number_format($p->capital_tied_up, 2) }}
                        </td>
                        <td data-label="Last Sold" style="text-align:center;">
                            @if($p->last_sold)
                                {{ \Carbon\Carbon::parse($p->last_sold)->format('d M Y') }}
                            @else
                                <span class="badge badge-danger">Never sold</span>
                            @endif
                        </td>
                        <td data-label="Days Since Sale" style="text-align:center;">
                            @if($daysSince !== null)
                                <span style="color:{{ $daysSince > 90 ? 'var(--color-danger)' : 'var(--color-warning)' }};">{{ $daysSince }}</span>
                            @else
                                <span class="badge badge-secondary">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background:#f9f9f9;font-weight:700;border-top:2px solid var(--color-border);">
                    <td colspan="4">Total</td>
                    <td data-label="Capital Tied Up" style="text-align:right;">KES {{ number_format($totalCapital, 2) }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
        @endif
    </div>
</div>
@endsection
