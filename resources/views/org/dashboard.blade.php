@extends('layouts.org')
@section('title', 'Organization Overview')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v={{ @filemtime(public_path('css/dashboard.css')) ?: '1' }}">
@endpush

@section('content')
<div class="page">

{{-- Welcome bar --}}
<div class="org-welcome-bar">
    <div>
        <h1>Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ Auth::user()->name }}!</h1>
        <p>{{ $organization->name }} &bull; {{ $businesses->count() }} {{ Str::plural('store', $businesses->count()) }}</p>
    </div>
    @if($organization->isOnTrial())
    <div class="trial-badge">
        <span>Trial ends <strong>{{ $organization->trial_ends_at->diffForHumans() }}</strong></span>
        <a href="{{ route('settings.subscription') }}">Upgrade</a>
    </div>
    @endif
</div>

{{-- Quick Links --}}
<div class="quick-links-grid">
    @if($canAddStore)
    <a href="{{ route('org.stores.create') }}" class="quick-link-tile ql-indigo">
        <span class="quick-link-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 9v.01"/><path d="M9 13v.01"/><path d="M9 17v.01"/><path d="M15 9v.01"/><path d="M15 13v.01"/><path d="M15 17v.01"/></svg></span>
        <span class="quick-link-label">Add store</span>
    </a>
    @else
    <a href="{{ route('settings.subscription') }}" class="quick-link-tile ql-indigo">
        <span class="quick-link-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 9v.01"/><path d="M9 13v.01"/><path d="M9 17v.01"/><path d="M15 9v.01"/><path d="M15 13v.01"/><path d="M15 17v.01"/></svg></span>
        <span class="quick-link-label">Upgrade to add more stores</span>
    </a>
    @endif
    <a href="{{ route('settings.subscription') }}" class="quick-link-tile ql-violet">
        <span class="quick-link-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></span>
        <span class="quick-link-label">{{ $organization->upgradePlan() ? 'Upgrade plan' : 'Manage subscription' }}</span>
    </a>
    @if($hasPayroll)
    <a href="#payrollSummary" class="quick-link-tile ql-emerald">
        <span class="quick-link-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>
        <span class="quick-link-label">Payroll summary</span>
    </a>
    @endif
</div>

{{-- KPI grid row 1 --}}
<div class="org-kpi-grid">
    <div class="org-kpi-card">
        <div class="org-kpi-label">Today's Revenue (All Stores)</div>
        <div class="org-kpi-value">KES {{ number_format($todayRevenue, 2) }}</div>
        <div class="org-kpi-sub">Across {{ $businesses->count() }} {{ Str::plural('store', $businesses->count()) }}</div>
    </div>
    <div class="org-kpi-card">
        <div class="org-kpi-label">MTD Revenue</div>
        <div class="org-kpi-value">KES {{ number_format($mtdRevenue, 2) }}</div>
        <div class="org-kpi-sub">{{ now()->format('F Y') }}</div>
    </div>
    <div class="org-kpi-card">
        <div class="org-kpi-label">MTD Expenses</div>
        <div class="org-kpi-value">KES {{ number_format($mtdExpenses, 2) }}</div>
        <div class="org-kpi-sub">{{ now()->format('F Y') }}</div>
    </div>
    <div class="org-kpi-card">
        <div class="org-kpi-label">MTD Profit</div>
        <div class="org-kpi-value" style="{{ $mtdProfit < 0 ? 'color:var(--color-danger)' : '' }}">
            KES {{ number_format(abs($mtdProfit), 2) }}{{ $mtdProfit < 0 ? ' (Loss)' : '' }}
        </div>
        <div class="org-kpi-sub">Revenue minus expenses</div>
    </div>
</div>

{{-- KPI grid row 2 — payroll --}}
@if($hasPayroll)
<div class="org-kpi-grid">
    <div class="org-kpi-card">
        <div class="org-kpi-label">MTD Payroll Paid</div>
        <div class="org-kpi-value">KES {{ number_format($mtdPayroll, 2) }}</div>
        <div class="org-kpi-sub">{{ now()->format('F Y') }}</div>
    </div>
    <div class="org-kpi-card">
        <div class="org-kpi-label">Pending Pay Runs</div>
        <div class="org-kpi-value" style="{{ $pendingPayrollCount > 0 ? 'color:var(--color-warning)' : '' }}">
            {{ $pendingPayrollCount > 0 ? $pendingPayrollCount : '—' }}
        </div>
        <div class="org-kpi-sub">Draft or awaiting payment</div>
    </div>
</div>
@endif

{{-- Your Stores --}}
<div class="org-section-header">
    <h2 class="org-section-title">Your stores</h2>
    <div style="display:flex;align-items:center;gap:12px;">
        <span class="org-section-meta">{{ $businesses->count() }} / {{ $storeLimit === -1 ? '∞' : $storeLimit }} stores used</span>
        @if($canAddStore)
        <a href="{{ route('org.stores.create') }}" class="quick-action-btn quick-action-primary" style="padding:5px 13px;font-size:12px;">+ Add store</a>
        @else
        <a href="{{ route('settings.subscription') }}" class="quick-action-btn quick-action-secondary" style="padding:5px 13px;font-size:12px;">Upgrade to add more</a>
        @endif
    </div>
</div>

@if($businesses->isEmpty())
<div class="org-panel text-muted" style="padding:32px;text-align:center;font-size:14px;">
    No stores yet. <a href="{{ route('org.stores.create') }}" style="color:var(--color-text);font-weight:600;">Add your first store →</a>
</div>
@else
<div class="store-cards-grid">
    @foreach($businesses as $business)
    @php $stats = $storeStats[$business->id] ?? []; @endphp
    <div class="store-card">
        <div class="store-card-header">
            <div class="store-card-name-wrap">
                <h3 class="store-card-name">{{ $business->name }}</h3>
                <span class="store-type-badge">{{ ucfirst($business->business_type ?? 'Retail') }}</span>
            </div>
            <div class="store-card-status">
                @if($business->isActive())
                    <span class="badge-success">Active</span>
                @else
                    <span class="badge-danger">Inactive</span>
                @endif
            </div>
        </div>

        <div class="store-card-stats">
            <div class="store-stat">
                <span class="store-stat-label">Today</span>
                <span class="store-stat-value">KES {{ number_format($stats['today_revenue'] ?? 0, 2) }}</span>
            </div>
            <div class="store-stat">
                <span class="store-stat-label">This month</span>
                <span class="store-stat-value">KES {{ number_format($stats['mtd_revenue'] ?? 0, 2) }}</span>
            </div>
            <div class="store-stat">
                <span class="store-stat-label">Staff</span>
                <span class="store-stat-value">{{ $stats['staff_count'] ?? 0 }}</span>
            </div>
        </div>

        <div class="store-card-actions">
            <form method="POST" action="{{ route('org.switch', $business) }}" style="display:inline;">
                @csrf
                <button type="submit" class="quick-action-btn quick-action-primary" style="padding:5px 13px;font-size:12px;">Enter store</button>
            </form>
            <a href="{{ route('org.stores.edit', $business) }}" class="quick-action-btn quick-action-secondary" style="padding:5px 13px;font-size:12px;">Edit</a>
        </div>
    </div>
    @endforeach
</div>
@endif

{{-- Revenue Trend — per store, not just one flat combined line --}}
<div class="org-panel">
    <div class="org-panel-header">
        <h2 class="org-panel-title">Revenue by store — last 6 months</h2>
    </div>
    <div class="chart-wrapper" style="height:280px;">
        <canvas id="revenueTrendChart"></canvas>
    </div>
</div>

{{-- New visualizations: store comparison + revenue share --}}
@if($businesses->count() > 1)
<div class="dash-two-col">
    <div class="dash-panel">
        <div class="dash-panel-header">
            <h2 class="dash-panel-title">Store comparison</h2>
            <span class="dash-panel-meta">Today vs. this month</span>
        </div>
        <div class="chart-wrapper" style="height:260px;">
            <canvas id="storeComparisonChart"></canvas>
        </div>
    </div>
    <div class="dash-panel">
        <div class="dash-panel-header">
            <h2 class="dash-panel-title">Revenue share by store</h2>
            <span class="dash-panel-meta">This month</span>
        </div>
        @if(array_sum($storeMtdRevenue) > 0)
        <div class="chart-wrapper" style="height:260px;">
            <canvas id="storeShareChart"></canvas>
        </div>
        @else
        <div class="dash-panel-body" style="color:var(--color-text-muted);font-size:13px;">No sales this month yet.</div>
        @endif
    </div>
</div>
@endif

{{-- Payroll Summary --}}
@if($hasPayroll)
<div class="org-panel" id="payrollSummary">
    <div class="org-panel-header">
        <h2 class="org-panel-title">Payroll summary</h2>
        <span class="org-panel-meta">YTD: <strong>KES {{ number_format($ytdPayroll, 2) }}</strong></span>
    </div>
    {{-- Was a bare inline style="overflow-x:auto;" — not one of the
    classes (.table-wrapper/.items-table-wrapper) responsive.css neutralizes
    on mobile, so the always-on horizontal scroll let this table's stacked
    <tr>s size to their natural ~540px content width instead of the 375px
    viewport, pushing every value (padding-left:48% of that oversized
    width) off-screen to the right — the table LOOKED entirely blank on a
    phone even though every value was present in the DOM. --}}
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Store</th>
                    <th>MTD payroll paid</th>
                    <th>Pending</th>
                    <th>Staff</th>
                    <th style="text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($businesses as $business)
                @php $stats = $storeStats[$business->id] ?? []; @endphp
                <tr>
                    <td data-label="Store">
                        <div style="font-weight:600;">{{ $business->name }}</div>
                        <div class="text-muted" style="font-size:11.5px;">{{ ucfirst($business->business_type ?? 'Retail') }}</div>
                    </td>
                    <td data-label="MTD payroll paid">
                        @if(($stats['payroll_mtd'] ?? 0) > 0)
                            <span style="font-weight:600;">KES {{ number_format($stats['payroll_mtd'], 2) }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td data-label="Pending">
                        @if(($stats['pending_payroll'] ?? 0) > 0)
                            <span style="color:var(--color-warning);font-weight:600;">KES {{ number_format($stats['pending_payroll'], 2) }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td data-label="Staff">
                        <span class="badge-secondary">{{ $stats['staff_count'] ?? 0 }}</span>
                    </td>
                    <td data-label="" style="text-align:right;">
                        <form method="POST" action="{{ route('org.switch', $business) }}" style="display:inline;">
                            @csrf
                            <button type="submit" class="quick-action-btn quick-action-secondary" style="padding:4px 11px;font-size:12px;">Payroll</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td data-label="Store">Organisation total</td>
                    <td data-label="MTD payroll paid" style="font-weight:700;">KES {{ number_format($mtdPayroll, 2) }}</td>
                    <td data-label="Pending" class="{{ $pendingPayrollCount > 0 ? '' : 'text-muted' }}" style="{{ $pendingPayrollCount > 0 ? 'color:var(--color-warning);font-weight:600;' : '' }}">
                        {{ $pendingPayrollCount > 0 ? $pendingPayrollCount . ' period' . ($pendingPayrollCount > 1 ? 's' : '') . ' pending' : 'All clear' }}
                    </td>
                    <td data-label="Staff">{{ $businesses->sum(fn($b) => $storeStats[$b->id]['staff_count'] ?? 0) }}</td>
                    <td data-label=""></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- YTD payroll by store --}}
@if($ytdPayroll > 0)
<div class="org-panel">
    <div class="org-panel-header">
        <h2 class="org-panel-title">Year-to-date payroll by store ({{ now()->format('Y') }})</h2>
    </div>
    <div class="org-panel-body">
        @php $maxYtd = max(array_column(array_values($ytdPayrollByStore), 'ytd') ?: [1]); @endphp
        @foreach(array_values($ytdPayrollByStore) as $row)
        @if($row['ytd'] > 0)
        <div class="org-ytd-bar-row">
            <span class="org-ytd-bar-label">{{ Str::limit($row['name'], 10) }}</span>
            <div class="org-ytd-bar-track">
                <div class="org-ytd-bar-fill" style="width:{{ round(($row['ytd'] / $maxYtd) * 100) }}%;"></div>
            </div>
            <span class="org-ytd-bar-amount">KES {{ number_format($row['ytd'], 2) }}</span>
        </div>
        @endif
        @endforeach
    </div>
</div>
@endif
@endif

{{-- Subscription --}}
<div class="org-panel">
    <div class="org-panel-header">
        <h2 class="org-panel-title">Subscription</h2>
    </div>
    <div class="org-sub-card">
        <div>
            <div class="org-sub-plan">{{ $organization->planName() }} Plan</div>
            @if($activeSubscription)
            <div class="text-muted" style="font-size:13px;margin-top:3px;">
                Valid until {{ $activeSubscription->end_date->format('d M Y') }}
                ({{ $activeSubscription->end_date->diffForHumans() }})
            </div>
            @elseif($organization->isOnTrial())
            <div style="margin-top:6px;">
                <span class="org-trial-tag">Trial ends {{ $organization->trial_ends_at->diffForHumans() }}</span>
            </div>
            @else
            <div style="font-size:13px;color:var(--color-danger);margin-top:3px;">Subscription expired</div>
            @endif
        </div>
        <a href="{{ route('settings.subscription') }}" class="quick-action-btn quick-action-secondary" style="padding:7px 16px;font-size:13px;">
            {{ $organization->upgradePlan() ? 'Upgrade plan' : 'Manage subscription' }}
        </a>
    </div>
</div>

</div>
@endsection

@push('scripts')
<script src="{{ asset('js/chart.umd.min.js') }}"></script>
<script>
(function() {
    var FONT = "'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif";
    // Same brand-aligned palette as the main dashboard (teal/amber/slate,
    // extended with a few more hues for orgs with several stores).
    var PALETTE = ['#0f766e', '#d97706', '#64748b', '#be185d', '#4f46e5', '#059669', '#0284c7', '#9333ea'];

    function fmtKes(n) {
        return 'KES ' + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // ── Revenue by store (multi-line) ───────────────────────────────────
    var trendCtx = document.getElementById('revenueTrendChart');
    if (trendCtx) {
        var labels     = @json($trendLabels);
        var storeTrend = @json($storeTrend);

        var datasets = storeTrend.map(function (store, i) {
            var color = PALETTE[i % PALETTE.length];
            return {
                label: store.name,
                data: store.data,
                borderColor: color,
                backgroundColor: 'transparent',
                borderWidth: 2,
                fill: false,
                tension: 0.35,
                pointStyle: 'circle',
                pointRadius: 2.5,
                pointHoverRadius: 5,
                pointBackgroundColor: '#fff',
                pointBorderColor: color,
                pointBorderWidth: 2,
            };
        });

        new Chart(trendCtx, {
            type: 'line',
            data: { labels: labels, datasets: datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        display: datasets.length > 1,
                        position: 'top', align: 'start',
                        labels: { boxWidth: 8, boxHeight: 8, usePointStyle: true, pointStyle: 'circle', padding: 16, color: '#5b6675', font: { size: 12, family: FONT } }
                    },
                    tooltip: {
                        backgroundColor: '#fff', titleColor: '#11181f', bodyColor: '#11181f',
                        borderColor: '#e3e7ec', borderWidth: 1, cornerRadius: 8, padding: 10, boxPadding: 4,
                        titleFont: { size: 12, weight: '600', family: FONT }, bodyFont: { size: 12, family: FONT },
                        callbacks: { label: function (c) { return '  ' + c.dataset.label + ':  ' + fmtKes(c.parsed.y); } }
                    }
                },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { color: '#8a93a0', font: { size: 11, family: FONT } } },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#eef1f4' }, border: { display: false },
                        ticks: { color: '#8a93a0', font: { size: 11, family: FONT }, callback: function (v) { return 'KES ' + v.toLocaleString(); } }
                    }
                }
            }
        });
    }

    // ── Store comparison (grouped bar: today vs this month) ─────────────
    var cmpCtx = document.getElementById('storeComparisonChart');
    if (cmpCtx) {
        new Chart(cmpCtx, {
            type: 'bar',
            data: {
                labels: @json($storeNames),
                datasets: [
                    { label: 'Today',      data: @json($storeTodayRevenue), backgroundColor: 'rgba(15,118,110,0.65)', borderRadius: 4, barPercentage: 0.6 },
                    { label: 'This month', data: @json($storeMtdRevenue),   backgroundColor: 'rgba(217,119,6,0.65)',  borderRadius: 4, barPercentage: 0.6 },
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', align: 'start', labels: { boxWidth: 8, boxHeight: 8, usePointStyle: true, pointStyle: 'circle', padding: 14, color: '#5b6675', font: { size: 12, family: FONT } } },
                    tooltip: { callbacks: { label: function (c) { return '  ' + c.dataset.label + ':  ' + fmtKes(c.raw); } } }
                },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { color: '#8a93a0', font: { size: 11, family: FONT } } },
                    y: { beginAtZero: true, grid: { color: '#eef1f4' }, border: { display: false }, ticks: { color: '#8a93a0', font: { size: 11, family: FONT }, callback: function (v) { return 'KES ' + v.toLocaleString(); } } }
                }
            }
        });
    }

    // ── Revenue share by store (donut) ───────────────────────────────────
    var shareCtx = document.getElementById('storeShareChart');
    if (shareCtx) {
        new Chart(shareCtx, {
            type: 'doughnut',
            data: {
                labels: @json($storeNames),
                datasets: [{ data: @json($storeMtdRevenue), backgroundColor: PALETTE, borderWidth: 2, borderColor: '#fff' }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 8, boxHeight: 8, usePointStyle: true, pointStyle: 'circle', padding: 12, color: '#5b6675', font: { size: 11.5, family: FONT } } },
                    tooltip: { callbacks: { label: function (c) { return c.label + ': ' + fmtKes(c.raw); } } }
                }
            }
        });
    }
})();
</script>
@endpush
