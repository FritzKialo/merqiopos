@extends('layouts.app')
@section('title', 'Sales Forecast')
@push('styles')
<style>
@media (max-width: 640px) {
    .sf-stats-grid { grid-template-columns: 1fr !important; }
}
@media (min-width: 769px) {
    .sf-table th { padding: 8px 12px; }
    .sf-table td { padding: 10px 12px; }
}
</style>
@endpush
@section('content')
<div class="page">

<div class="page-header">
    <h1 class="page-title">Sales Forecast</h1>
    <form method="GET" style="display:flex; gap:8px; align-items:center;">
        <select name="months" class="form-control" onchange="this.form.submit()">
            <option value="3" {{ request('months',3)==3?'selected':'' }}>Next 3 Months</option>
            <option value="6" {{ request('months')==6?'selected':'' }}>Next 6 Months</option>
            <option value="12" {{ request('months')==12?'selected':'' }}>Next 12 Months</option>
        </select>
    </form>
</div>

<div class="sf-stats-grid" style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:32px;">
    <div class="stat-card">
        <div class="stat-label">Avg Monthly (6mo)</div>
        <div class="stat-value">KES {{ number_format($avgMonthly, 2) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Projected Next Month</div>
        <div class="stat-value">KES {{ number_format($forecast[0]['projected'] ?? 0, 2) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Growth Trend</div>
        <div class="stat-value {{ ($growthRate ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
            {{ ($growthRate ?? 0) >= 0 ? '+' : '' }}{{ number_format($growthRate ?? 0, 1) }}%
        </div>
    </div>
</div>

<div class="card" style="margin-bottom:24px;">
    <div class="card-body">
        <canvas id="forecastChart" height="80"></canvas>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table class="sf-table" style="width:100%; border-collapse:collapse;">
            <thead><tr style="border-bottom:2px solid var(--color-text);">
                <th style="text-align:left;">Month</th>
                <th style="text-align:right;">Actual</th>
                <th style="text-align:right;">Projected</th>
                <th style="text-align:right;">Variance</th>
            </tr></thead>
            <tbody>
                @foreach($forecast as $row)
                <tr style="border-bottom:1px solid var(--color-border);">
                    <td data-label="Month">{{ $row['month'] }}</td>
                    <td data-label="Actual" style="text-align:right;">{{ $row['actual'] !== null ? 'KES '.number_format($row['actual'], 2) : '—' }}</td>
                    <td data-label="Projected" class="text-muted" style="text-align:right;">KES {{ number_format($row['projected'], 2) }}</td>
                    <td data-label="Variance" style="text-align:right;">
                        @if($row['actual'] !== null && $row['projected'] > 0)
                        @php $var = (($row['actual'] - $row['projected']) / $row['projected']) * 100; @endphp
                        <span class="{{ $var >= 0 ? 'text-success' : 'text-danger' }}">{{ $var >= 0 ? '+' : '' }}{{ number_format($var, 1) }}%</span>
                        @else —
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

</div>{{-- end .page --}}
@push('scripts')
<script src="{{ asset('js/chart.umd.min.js') }}"></script>
<script>
const labels = @json(collect($forecast)->pluck('month'));
const actual  = @json(collect($forecast)->pluck('actual'));
const proj    = @json(collect($forecast)->pluck('projected'));
new Chart(document.getElementById('forecastChart'), {
    type: 'line',
    data: {
        labels,
        datasets: [
            { label: 'Actual', data: actual, borderColor: '#000', tension: 0.3, spanGaps: true },
            { label: 'Projected', data: proj, borderColor: '#aaa', borderDash: [6,4], tension: 0.3 }
        ]
    },
    options: { plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
});
</script>
@endpush
@endsection
