@extends('layouts.app')
@section('title', 'Gross Margin Report')
@push('styles')
<style>
@media (max-width: 700px) {
    .gm-stats-grid { grid-template-columns: 1fr 1fr !important; }
}
@media (max-width: 420px) {
    .gm-stats-grid { grid-template-columns: 1fr !important; }
}
@media (min-width: 769px) {
    .gm-table th { padding: 12px 16px; }
    .gm-table td { padding: 10px 16px; }
}
</style>
@endpush
@section('content')
<div class="page">

<div class="page-header">
    <h1 class="page-title">Gross Margin Report</h1>
    <form method="GET" style="display:flex;gap:8px;align-items:center;">
        <select name="period" class="form-control" onchange="this.form.submit()" style="width:auto;">
            <option value="this_month" {{ $period === 'this_month' ? 'selected' : '' }}>This Month</option>
            <option value="last_month" {{ $period === 'last_month' ? 'selected' : '' }}>Last Month</option>
            <option value="this_year" {{ $period === 'this_year' ? 'selected' : '' }}>This Year</option>
        </select>
    </form>
</div>

<div class="gm-stats-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;">
<div class="stat-card">
    <div class="stat-label">Total Revenue</div>
    <div class="stat-value">KES {{ number_format($totalRevenue, 2) }}</div>
</div>
<div class="stat-card">
    <div class="stat-label">Total Cost</div>
    <div class="stat-value">KES {{ number_format($totalCost, 2) }}</div>
</div>
<div class="stat-card">
    <div class="stat-label">Gross Profit</div>
    <div class="stat-value {{ $totalProfit >= 0 ? 'text-success' : 'text-danger' }}">KES {{ number_format($totalProfit, 2) }}</div>
</div>
<div class="stat-card">
    <div class="stat-label">Overall Margin</div>
    @php $overallClass = $overallMargin >= 30 ? 'text-success' : ($overallMargin >= 10 ? 'text-warn' : 'text-danger'); @endphp
    <div class="stat-value {{ $overallClass }}">{{ $overallMargin }}%</div>
</div>
</div>

@if($products->count())
<div class="card" style="margin-bottom:24px;">
<div class="card-body" style="padding-bottom:8px;">
<h3 style="margin:0 0 16px;">Top 10 Products by Gross Profit</h3>
<canvas id="chart" height="80"></canvas>
</div>
</div>
@endif

<div class="card" style="margin-bottom:24px;">
<div class="card-body" style="padding:0;">
<table class="gm-table" style="width:100%;border-collapse:collapse;">
<thead><tr style="border-bottom:1px solid var(--color-border);background:var(--color-surface-2);">
    <th style="text-align:left;">Product</th>
    <th style="text-align:left;">Category</th>
    <th style="text-align:right;">Units Sold</th>
    <th style="text-align:right;">Revenue</th>
    <th style="text-align:right;">Cost</th>
    <th style="text-align:right;">Gross Profit</th>
    <th style="text-align:right;">Margin %</th>
</tr></thead>
<tbody>
@forelse($products as $p)
@php
    $margin = $p->revenue > 0 ? round(($p->gross_profit / $p->revenue) * 100, 1) : 0;
    $mBadge = $margin >= 30 ? 'badge-success' : ($margin >= 10 ? 'badge-warning' : 'badge-danger');
@endphp
<tr style="border-bottom:1px solid var(--color-border);">
    <td data-label="Product">{{ $p->product_name }}</td>
    <td data-label="Category" class="text-muted">{{ $p->category_name }}</td>
    <td data-label="Units Sold" style="text-align:right;">{{ number_format($p->units_sold, 2) }}</td>
    <td data-label="Revenue" style="text-align:right;">KES {{ number_format($p->revenue, 2) }}</td>
    <td data-label="Cost" style="text-align:right;">KES {{ number_format($p->cost, 2) }}</td>
    <td data-label="Gross Profit" class="{{ $p->gross_profit >= 0 ? 'text-success' : 'text-danger' }}" style="text-align:right;font-weight:600;">KES {{ number_format($p->gross_profit, 2) }}</td>
    <td data-label="Margin %" style="text-align:right;">
        <span class="{{ $mBadge }}">{{ $margin }}%</span>
    </td>
</tr>
@empty
<tr><td colspan="7" class="text-muted" style="padding:40px;text-align:center;">No sales data for this period.</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>

@if($byCategory->count())
<div class="card">
<div class="card-body" style="padding:0;">
<table class="gm-table" style="width:100%;border-collapse:collapse;">
<thead><tr style="border-bottom:1px solid var(--color-border);background:var(--color-surface-2);">
    <th style="text-align:left;">Category</th>
    <th style="text-align:right;">Revenue</th>
    <th style="text-align:right;">Cost</th>
    <th style="text-align:right;">Gross Profit</th>
    <th style="text-align:right;">Margin %</th>
</tr></thead>
<tbody>
@foreach($byCategory as $cat => $data)
@php $mBadge = $data['margin_pct'] >= 30 ? 'badge-success' : ($data['margin_pct'] >= 10 ? 'badge-warning' : 'badge-danger'); @endphp
<tr style="border-bottom:1px solid var(--color-border);">
    <td data-label="Category" style="font-weight:600;">{{ $cat }}</td>
    <td data-label="Revenue" style="text-align:right;">KES {{ number_format($data['revenue'], 2) }}</td>
    <td data-label="Cost" style="text-align:right;">KES {{ number_format($data['cost'], 2) }}</td>
    <td data-label="Gross Profit" class="{{ $data['profit'] >= 0 ? 'text-success' : 'text-danger' }}" style="text-align:right;">KES {{ number_format($data['profit'], 2) }}</td>
    <td data-label="Margin %" style="text-align:right;">
        <span class="{{ $mBadge }}">{{ $data['margin_pct'] }}%</span>
    </td>
</tr>
@endforeach
</tbody>
</table>
</div>
</div>
@endif

@if($products->count())
<script src="{{ asset('js/chart.umd.min.js') }}"></script>
<script>
const top10 = @json($products->take(10)->values());
new Chart(document.getElementById('chart'), {
    type: 'bar',
    data: {
        labels: top10.map(p => p.product_name),
        datasets: [
            {
                label: 'Revenue',
                data: top10.map(p => parseFloat(p.revenue)),
                backgroundColor: 'rgba(0,123,255,0.6)',
            },
            {
                label: 'Cost',
                data: top10.map(p => parseFloat(p.cost)),
                backgroundColor: 'rgba(255,100,100,0.6)',
            },
            {
                label: 'Gross Profit',
                data: top10.map(p => parseFloat(p.gross_profit)),
                backgroundColor: 'rgba(40,167,69,0.7)',
            },
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: { y: { beginAtZero: true } }
    }
});
</script>
@endif

</div>{{-- end .page --}}
@endsection
