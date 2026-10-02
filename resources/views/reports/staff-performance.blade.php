@extends('layouts.app')
@section('title', 'Staff Performance')
@push('styles')
<style>
@media (min-width: 769px) {
    .staff-perf-table th { padding: 8px 12px; }
    .staff-perf-table td { padding: 10px 12px; }
}
.staff-flag-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em;
    color: #b91c1c; background: #fee2e2; border: 1px solid #fecaca;
    padding: 2px 8px; border-radius: 999px; margin-left: 6px;
}
.staff-card--flagged { border-color: #fecaca; box-shadow: 0 0 0 1px #fecaca; }
</style>
@endpush
@section('content')
<div class="page">

<div class="page-header">
    <div>
        <h1 class="page-title">Staff Performance</h1>
        <p class="page-subtitle">Completed sales exclude cancelled transactions. Cancellation rate is flagged when it's a real outlier against the team average, not just "someone cancelled a sale."</p>
    </div>
    <form method="GET" style="display:flex; gap:8px; align-items:center;">
        <select name="period" class="form-control" onchange="this.form.submit()">
            <option value="this_month" {{ request('period','this_month')==='this_month'?'selected':'' }}>This Month</option>
            <option value="last_month" {{ request('period')==='last_month'?'selected':'' }}>Last Month</option>
            <option value="this_year" {{ request('period')==='this_year'?'selected':'' }}>This Year</option>
        </select>
    </form>
</div>

@if($staffStats->isEmpty())
<div class="empty-state"><p>No staff data available.</p></div>
@else
<div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:16px; margin-bottom:32px;">
    @foreach($staffStats as $stat)
    <div class="card {{ $stat['flagged'] ? 'staff-card--flagged' : '' }}">
        <div class="card-body">
            <div style="font-weight:bold; margin-bottom:4px; display:flex; align-items:center; flex-wrap:wrap;">
                {{ $stat['name'] }}
                @if($stat['flagged'])
                    <span class="staff-flag-badge"><i class="ph-bold ph-warning"></i> Review</span>
                @endif
            </div>
            <div style="font-size:0.8rem; color:var(--color-text-muted); margin-bottom:12px;">{{ $stat['role'] }}</div>
            <div style="display:flex; justify-content:space-between; font-size:0.9rem; margin-bottom:6px;">
                <span>Sales</span><span style="font-weight:bold;">{{ $stat['sales_count'] }}</span>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:0.9rem; margin-bottom:6px;">
                <span>Revenue</span><span style="font-weight:bold;">KES {{ number_format($stat['revenue'], 2) }}</span>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:0.9rem; margin-bottom:6px;">
                <span>Avg. Sale</span><span>KES {{ $stat['sales_count'] > 0 ? number_format($stat['revenue'] / $stat['sales_count'], 2) : 0 }}</span>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:0.9rem; {{ $stat['flagged'] ? 'color:#b91c1c; font-weight:600;' : '' }}">
                <span>Cancelled</span><span>{{ $stat['cancelled_count'] }} ({{ number_format($stat['cancellation_rate'] * 100, 1) }}%)</span>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="card">
    <div class="card-body">
        <table class="staff-perf-table" style="width:100%; border-collapse:collapse;">
            <thead><tr style="border-bottom:2px solid var(--color-text);">
                <th style="text-align:left;">Staff Member</th>
                <th style="text-align:right;">Sales</th>
                <th style="text-align:right;">Revenue</th>
                <th style="text-align:right;">Avg Value</th>
                <th style="text-align:right;">Returns</th>
                <th style="text-align:right;">Cancelled</th>
                <th style="text-align:right;">Cancel Rate</th>
            </tr></thead>
            <tbody>
                @foreach($staffStats as $stat)
                <tr style="border-bottom:1px solid var(--color-border); {{ $stat['flagged'] ? 'background:#fef2f2;' : '' }}">
                    <td data-label="Staff Member">
                        {{ $stat['name'] }} <span style="font-size:0.8rem; color:var(--color-text-muted);">({{ $stat['role'] }})</span>
                        @if($stat['flagged'])
                            <span class="staff-flag-badge"><i class="ph-bold ph-warning"></i> Review</span>
                        @endif
                    </td>
                    <td data-label="Sales" style="text-align:right;">{{ $stat['sales_count'] }}</td>
                    <td data-label="Revenue" style="text-align:right;">KES {{ number_format($stat['revenue'], 2) }}</td>
                    <td data-label="Avg Value" style="text-align:right;">KES {{ $stat['sales_count'] > 0 ? number_format($stat['revenue'] / $stat['sales_count'], 2) : 0 }}</td>
                    <td data-label="Returns" style="text-align:right;">{{ $stat['returns_count'] }}</td>
                    <td data-label="Cancelled" style="text-align:right;">{{ $stat['cancelled_count'] }} <span style="color:var(--color-text-muted); font-size:0.8rem;">(KES {{ number_format($stat['cancelled_value'], 0) }})</span></td>
                    <td data-label="Cancel Rate" style="text-align:right; {{ $stat['flagged'] ? 'color:#b91c1c; font-weight:700;' : '' }}">{{ number_format($stat['cancellation_rate'] * 100, 1) }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($avgCancellationRate > 0)
        <p style="margin-top:12px; font-size:0.8rem; color:var(--color-text-muted);">
            Team average cancellation rate this period: {{ number_format($avgCancellationRate * 100, 1) }}%. "Review" is flagged at 3+ cancellations and a rate meaningfully above that average.
        </p>
        @endif
    </div>
</div>
@endif

</div>{{-- end .page --}}
@endsection
