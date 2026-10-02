@extends('layouts.app')
@section('title', 'Dashboard')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v={{ @filemtime(public_path('css/dashboard.css')) ?: '1' }}">
@endpush

@section('content')
<div class="page">

@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:14px;">{{ session('success') }}</div>
@endif
@if(session('warning'))
    <div class="alert alert-warning" style="margin-bottom:14px;">{{ session('warning') }}</div>
@endif

{{-- Low Stock Banner — dismissing this used to do nothing but a client-side
.remove(): no localStorage, no server flag, nothing. Reload the dashboard
(or just navigate away and back) and it was right back, every time,
making the × button a complete lie. Now the dismissal is remembered per
browser, keyed to the current count specifically — so it stays dismissed
while the situation is unchanged, but comes back the moment the count
actually changes (more items dip low, or it's restocked down to a
different count), which is the useful behavior, not just "gone forever
until it isn't." --}}
@if($lowStockCount > 0)
{{-- .low-stock-banner-wrap takes zero vertical space (height:0) and the
banner itself is absolutely positioned inside it — a floating alert over
the top of the page instead of a normal-flow block, so opening/dismissing
it never shifts the welcome bar or anything below it. Scoped to this one
wrapper rather than making the shared .page class a positioning context,
which every other page in the app also uses and shouldn't have its
behavior changed for. --}}
<div class="low-stock-banner-wrap">
<div class="low-stock-banner" id="lowStockBanner" data-count="{{ $lowStockCount }}">
    <span>
        <strong>{{ $lowStockCount }} product{{ $lowStockCount > 1 ? 's are' : ' is' }} running low on stock.</strong>
        Restock soon to avoid lost sales.
        <a href="{{ route('inventory.index', ['low_stock' => 1]) }}">View products</a>
    </span>
    <button type="button" class="low-stock-banner-close" id="lowStockBannerClose" aria-label="Dismiss">&times;</button>
</div>
</div>
@push('scripts')
{{-- Not role-gated (unlike the dashboard.js bundle below) — $lowStockCount
has no role check either, so a cashier can see this banner too and needs
the dismiss button to actually work for them as well. --}}
<script>
(function () {
    var banner = document.getElementById('lowStockBanner');
    var closeBtn = document.getElementById('lowStockBannerClose');
    if (!banner || !closeBtn) return;

    var STORAGE_KEY = 'lowStockBannerDismissedCount';
    var currentCount = banner.getAttribute('data-count');

    try {
        if (localStorage.getItem(STORAGE_KEY) === currentCount) {
            banner.remove();
            return;
        }
    } catch (e) {}

    closeBtn.addEventListener('click', function () {
        try { localStorage.setItem(STORAGE_KEY, currentCount); } catch (e) {}
        banner.remove();
    });
})();
</script>
@endpush
@endif

{{-- Welcome bar --}}
<div class="welcome-bar">
    <div class="welcome-content">
        <h1>Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ $user->name }}!</h1>
        <p>{{ now()->format('l, d F Y') }} &bull; {{ $business->name }}</p>
    </div>
    @role('owner','overall_manager','manager')
        @if($business->isOnTrial())
            <div class="trial-badge">
                <span>Trial ends <strong>{{ $business->trial_ends_at->diffForHumans() }}</strong></span>
                @role('owner')
                <a href="{{ route('settings.subscription') }}">Upgrade</a>
                @endrole
            </div>
        @endif
    @endrole
</div>

{{-- Quick Links --}}
<div class="quick-links-grid">
    @unless($user->hasRole('overall_manager'))
    <a href="{{ route('sales.create') }}" class="quick-link-tile ql-emerald">
        <span class="quick-link-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg></span>
        <span class="quick-link-label">New sale</span>
    </a>
    @endunless
    @role('owner','overall_manager','manager')
    <a href="{{ route('inventory.create') }}" class="quick-link-tile ql-indigo">
        <span class="quick-link-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></span>
        <span class="quick-link-label">Add product</span>
    </a>
    @endrole
    @if($business->hasFeature('expenses'))
    <a href="{{ route('expenses.create') }}" class="quick-link-tile ql-rose">
        <span class="quick-link-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>
        <span class="quick-link-label">Record expense</span>
    </a>
    @endif
    @if($business->hasFeature('customers'))
    <a href="{{ route('customers.create') }}" class="quick-link-tile ql-violet">
        <span class="quick-link-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg></span>
        <span class="quick-link-label">Add customer</span>
    </a>
    @endif
</div>

{{-- Personal self-service (clock in/out, my leave/advances/payslips) --}}
@include('partials.self-service')

{{-- Insights & Recommendations — shown first so the actionable stuff is
     seen before scrolling past the KPI cards and chart. --}}
@role('owner','overall_manager','manager')
@if(!empty($insights))
<div class="dash-panel insights-panel">
    <div class="dash-panel-header">
        <h2 class="dash-panel-title">Insights &amp; recommendations</h2>
        <span class="dash-panel-meta">Based on this month's numbers</span>
    </div>
    <ul class="insights-list">
        @foreach($insights as $insight)
        <li class="insight-item insight-{{ $insight['type'] }}">
            <div class="insight-icon">
                @switch($insight['icon'])
                    @case('trend-up')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                        @break
                    @case('trend-down')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/></svg>
                        @break
                    @case('alert')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        @break
                    @case('cash')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        @break
                    @case('box')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                        @break
                    @case('clock')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        @break
                    @case('info')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        @break
                    @default
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                @endswitch
            </div>
            <div class="insight-body">
                <span class="insight-title">{{ $insight['title'] }}</span>
                <span class="insight-text">{{ $insight['text'] }}</span>
            </div>
            @if(!empty($insight['action_route']))
                <a href="{{ route($insight['action_route'], $insight['action_params'] ?? []) }}" class="insight-action">{{ $insight['action_label'] ?? 'View' }}</a>
            @endif
        </li>
        @endforeach
    </ul>
</div>
@endif
@endrole

{{-- KPI cards — owner / manager --}}
@role('owner','overall_manager','manager')
<div class="kpi-grid">
    <div class="kpi-card kpi-emerald">
        <div class="kpi-body">
            <span class="kpi-value" id="kpiTodaySales" data-raw="{{ $todaySales }}">KES {{ number_format($todaySales, 2) }}</span>
            <span class="kpi-label">Today's revenue</span>
        </div>
    </div>
    <div class="kpi-card kpi-rose">
        <div class="kpi-body">
            <span class="kpi-value" id="kpiTodayExpenses" data-raw="{{ $todayExpenses }}">KES {{ number_format($todayExpenses, 2) }}</span>
            <span class="kpi-label">Today's expenses</span>
        </div>
    </div>
    <div class="kpi-card {{ $todayProfit < 0 ? 'kpi-rose' : 'kpi-violet' }}" id="kpiTodayProfitCard">
        <div class="kpi-body">
            <span class="kpi-value {{ $todayProfit < 0 ? 'is-negative' : '' }}" id="kpiTodayProfit" data-raw="{{ $todayProfit }}">KES {{ number_format($todayProfit, 2) }}</span>
            <span class="kpi-label">Today's profit</span>
        </div>
    </div>
    <div class="kpi-card kpi-indigo">
        <div class="kpi-body">
            <span class="kpi-value" id="kpiMonthSales" data-raw="{{ $monthSales }}">KES {{ number_format($monthSales, 2) }}</span>
            <span class="kpi-label">Month revenue</span>
            @if($prevMonthSales > 0)
                @php $salesTrend = round((($monthSales - $prevMonthSales) / $prevMonthSales) * 100, 1); @endphp
                <span class="kpi-trend {{ $salesTrend >= 0 ? 'kpi-trend-up' : 'kpi-trend-down' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:10px;height:10px;">
                        @if($salesTrend >= 0)<polyline points="18 15 12 9 6 15"/>@else<polyline points="6 9 12 15 18 9"/>@endif
                    </svg>
                    {{ number_format(abs($salesTrend), 1) }}% vs last month
                </span>
            @endif
        </div>
    </div>
    <div class="kpi-card {{ $monthProfit < 0 ? 'kpi-rose' : 'kpi-amber' }}" id="kpiMonthProfitCard">
        <div class="kpi-body">
            <span class="kpi-value {{ $monthProfit < 0 ? 'is-negative' : '' }}" id="kpiMonthProfit" data-raw="{{ $monthProfit }}">KES {{ number_format($monthProfit, 2) }}</span>
            <span class="kpi-label">Month profit</span>
            @if($prevMonthProfit != 0)
                @php $profitTrend = round((($monthProfit - $prevMonthProfit) / abs($prevMonthProfit)) * 100, 1); @endphp
                <span class="kpi-trend {{ $profitTrend >= 0 ? 'kpi-trend-up' : 'kpi-trend-down' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:10px;height:10px;">
                        @if($profitTrend >= 0)<polyline points="18 15 12 9 6 15"/>@else<polyline points="6 9 12 15 18 9"/>@endif
                    </svg>
                    {{ number_format(abs($profitTrend), 1) }}% vs last month
                </span>
            @endif
        </div>
    </div>
</div>
@endrole

{{-- Cashier KPI --}}
@cashier
<div class="kpi-grid">
    <div class="kpi-card kpi-indigo">
        <div class="kpi-body">
            <span class="kpi-value">{{ $todayTransactions }}</span>
            <span class="kpi-label">My sales today</span>
        </div>
    </div>
    <div class="kpi-card kpi-emerald">
        <div class="kpi-body">
            <span class="kpi-value">KES {{ number_format($myRevenueToday, 2) }}</span>
            <span class="kpi-label">My revenue today</span>
        </div>
    </div>
    <div class="kpi-card {{ $lowStockCount > 0 ? 'kpi-rose' : 'kpi-emerald' }}">
        <div class="kpi-body">
            <span class="kpi-value {{ $lowStockCount > 0 ? 'is-negative' : '' }}">{{ $lowStockCount }}</span>
            <span class="kpi-label">Low stock products</span>
        </div>
    </div>
</div>
@endcashier

@role('owner','overall_manager','manager')

{{-- Revenue vs Expenses Chart --}}
<div class="dash-panel">
    <div class="dash-panel-header dash-panel-header-wrap">
        <h2 class="dash-panel-title" id="chartTitle">Revenue vs Expenses — Last 6 Months</h2>
        <div class="chart-controls">
            <div class="chart-range-group" id="chartRangeGroup">
                <button class="chart-toggle-btn" data-days="7">7D</button>
                <button class="chart-toggle-btn" data-days="30">30D</button>
                <button class="chart-toggle-btn" data-days="90">3M</button>
                <button class="chart-toggle-btn active" data-days="180">6M</button>
                <button class="chart-toggle-btn" data-days="365">1Y</button>
                <button class="chart-toggle-btn" id="chartCustomBtn" type="button">Custom</button>
            </div>
            <label class="chart-compare-toggle">
                <input type="checkbox" id="chartCompareToggle">
                <span>Compare previous period</span>
            </label>
            <div class="chart-type-group" id="chartTypeGroup">
                <button class="chart-toggle-btn active" data-type="line">Line</button>
                <button class="chart-toggle-btn" data-type="bar">Bar</button>
                <button class="chart-toggle-btn" data-type="area">Area</button>
            </div>
            <div class="chart-export-group">
                <button class="chart-toggle-btn" id="chartExportPng" type="button" title="Download chart as an image">PNG</button>
                <button class="chart-toggle-btn" id="chartExportCsv" type="button" title="Download the chart's data as CSV">CSV</button>
            </div>
        </div>
    </div>
    <div class="chart-custom-range" id="chartCustomRange" style="display:none;">
        <input type="date" id="chartStartDate" class="form-control">
        <span>to</span>
        <input type="date" id="chartEndDate" class="form-control">
        <button class="chart-toggle-btn" id="chartCustomApply" type="button">Apply</button>
    </div>
    <div class="chart-toggle-group" id="chartToggle">
        <button class="chart-toggle-btn active" data-dataset="all">All</button>
        <button class="chart-toggle-btn" data-dataset="revenue">Revenue</button>
        <button class="chart-toggle-btn" data-dataset="expenses">Expenses</button>
        <button class="chart-toggle-btn" data-dataset="profit">Profit</button>
    </div>
    <div class="chart-legend" id="chartLegend">
        <div class="chart-legend-item">
            <div class="chart-legend-dot" style="background:#0f766e;"></div>
            <span style="font-size:12px;color:var(--color-text-muted);" id="chartLegendRevenue">KES {{ number_format($monthSales, 2) }} Revenue</span>
        </div>
        <div class="chart-legend-item">
            <div class="chart-legend-dot" style="background:#d97706;"></div>
            <span style="font-size:12px;color:var(--color-text-muted);" id="chartLegendExpenses">KES {{ number_format($monthExpenses, 2) }} Expenses</span>
        </div>
        <div class="chart-legend-item">
            <div class="chart-legend-dot" style="background:#64748b;"></div>
            <span style="font-size:12px;color:var(--color-text-muted);" id="chartLegendProfit">KES {{ number_format($monthProfit, 2) }} Net profit</span>
        </div>
    </div>
    <div class="chart-wrapper" style="height:260px;position:relative;">
        <canvas id="revenueChart"></canvas>
        <div class="chart-loading" id="chartLoading" style="display:none;">Loading&hellip;</div>
    </div>
    <p class="chart-hint">Tip: click a point on the chart to see that period's sales.</p>
</div>

{{-- Row 2: Recent Sales + At a Glance / Needs Attention --}}
<div class="dash-two-col">

    {{-- Recent Sales --}}
    <div class="dash-panel">
        <div class="dash-panel-header">
            <h2 class="dash-panel-title">Recent sales</h2>
            @unless($user->hasRole('overall_manager'))<a href="{{ route('sales.create') }}" class="quick-action-btn quick-action-primary" style="padding:5px 13px;font-size:12px;">New sale</a>@endunless
        </div>
        @if($recentSales->isEmpty())
            <div class="dash-panel-body" style="color:#888;font-size:13px;">No sales recorded today.</div>
        @else
        <table>
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentSales as $sale)
                <tr>
                    <td data-label="Invoice"><a href="{{ route('sales.show', $sale) }}" style="font-weight:700;color:#0a0a0a;">{{ $sale->invoice_number }}</a></td>
                    <td data-label="Customer">{{ $sale->customer->name ?? 'Walk-in' }}</td>
                    <td data-label="Total"><strong>KES {{ number_format($sale->total_amount, 2) }}</strong></td>
                    <td data-label="Status">
                        @if($sale->payment_status === 'paid') <span class="badge-success">Paid</span>
                        @elseif($sale->payment_status === 'partial') <span class="badge-warning">Partial</span>
                        @else <span class="badge-danger">Unpaid</span>
                        @endif
                    </td>
                    <td data-label="Time" style="color:#888;font-size:12px;">{{ $sale->created_at->format('g:i A') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- Right column --}}
    <div>
        {{-- At a Glance --}}
        <div class="dash-panel">
            <div class="dash-panel-header">
                <h2 class="dash-panel-title">At a glance</h2>
            </div>
            <ul class="quick-stats-list">
                <li>
                    <a href="{{ route('inventory.index') }}">
                        <span class="qs-label">Active products</span>
                        <span class="qs-value">{{ $totalProducts }}</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('inventory.index', ['low_stock' => 1]) }}">
                        <span class="qs-label">Low stock</span>
                        <span class="qs-value" style="{{ $lowStockCount > 0 ? 'color:var(--color-danger)' : '' }}">{{ $lowStockCount }}</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('customers.index') }}">
                        <span class="qs-label">Total customers</span>
                        <span class="qs-value">{{ $totalCustomers }}</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('customers.index', ['with_debt' => 1]) }}">
                        <span class="qs-label">Outstanding debt</span>
                        <span class="qs-value" style="{{ $outstandingDebt > 0 ? 'color:var(--color-danger)' : '' }}">KES {{ number_format($outstandingDebt, 2) }}</span>
                    </a>
                </li>
                <li>
                    <span class="qs-label">Today's transactions</span>
                    <span class="qs-value">{{ $todayTransactions }}</span>
                </li>
            </ul>
        </div>

        {{-- Needs Attention --}}
        @if($lowStockCount > 0 || $overdueCount > 0 || $pendingQuotesCount > 0 || $upcomingRecurringCount > 0)
        <div class="dash-panel" style="margin-top:16px;">
            <div class="dash-panel-header">
                <h2 class="dash-panel-title">Needs attention</h2>
            </div>
            <ul class="alert-list">
                @if($lowStockCount > 0)
                <li>
                    <div>
                        <span class="al-text">{{ $lowStockCount }} low stock product{{ $lowStockCount > 1 ? 's' : '' }}</span>
                        <span class="al-sub">Restock to avoid lost sales</span>
                    </div>
                    <a href="{{ route('inventory.index', ['low_stock'=>1]) }}">View</a>
                </li>
                @endif
                @if($overdueCount > 0)
                <li>
                    <div>
                        <span class="al-text">{{ $overdueCount }} overdue invoice{{ $overdueCount > 1 ? 's' : '' }}</span>
                        <span class="al-sub">KES {{ number_format($overdueAmount, 2) }} uncollected</span>
                    </div>
                    <a href="{{ route('sales.index', ['payment_status'=>'unpaid']) }}">View</a>
                </li>
                @endif
                @if($pendingQuotesCount > 0 && $business->hasFeature('quotes'))
                <li>
                    <div>
                        <span class="al-text">{{ $pendingQuotesCount }} pending quote{{ $pendingQuotesCount > 1 ? 's' : '' }}</span>
                        <span class="al-sub">Awaiting customer response</span>
                    </div>
                    <a href="{{ route('quotes.index', ['status'=>'sent']) }}">View</a>
                </li>
                @endif
                @if($upcomingRecurringCount > 0 && $business->hasFeature('recurring_invoices'))
                <li>
                    <div>
                        <span class="al-text">{{ $upcomingRecurringCount }} recurring invoice{{ $upcomingRecurringCount > 1 ? 's' : '' }} due soon</span>
                        <span class="al-sub">Due within 7 days</span>
                    </div>
                    <a href="{{ route('recurring.index') }}">View</a>
                </li>
                @endif
            </ul>
        </div>
        @endif
    </div>

</div>

{{-- Row 3: Top Products + Cash Flow + Top Customers --}}
<div class="dash-two-col">

    {{-- Top Products --}}
    <div class="dash-panel">
        <div class="dash-panel-header">
            <h2 class="dash-panel-title">Top products</h2>
            <span class="dash-panel-meta">This month</span>
        </div>
        @if($topProducts->isEmpty())
            <div class="dash-panel-body" style="color:#888;font-size:13px;">No sales this month.</div>
        @else
        <table>
            <thead><tr><th>#</th><th>Product</th><th>Qty</th><th>Revenue</th></tr></thead>
            <tbody>
                @foreach($topProducts as $i => $p)
                <tr>
                    <td data-label="#" style="color:#aaa;font-weight:700;font-size:12px;">{{ $i + 1 }}</td>
                    <td data-label="Product"><strong>{{ $p->name }}</strong></td>
                    <td data-label="Qty">{{ $p->total_qty }}</td>
                    <td data-label="Revenue"><strong>KES {{ number_format($p->total_revenue, 2) }}</strong></td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- Cash Flow + Top Customers --}}
    <div>
        <div class="dash-panel">
            <div class="dash-panel-header">
                <h2 class="dash-panel-title">Cash flow — this month</h2>
            </div>
            <ul class="quick-stats-list">
                <li>
                    <span class="qs-label">Revenue</span>
                    <span class="qs-value" style="color:var(--color-success);">KES {{ number_format($monthSales, 2) }}</span>
                </li>
                <li>
                    <span class="qs-label">Cash collected</span>
                    <span class="qs-value">KES {{ number_format($cashCollected, 2) }}</span>
                </li>
                <li>
                    <span class="qs-label">Uncollected</span>
                    <span class="qs-value" style="{{ $cashOutstanding > 0 ? 'color:var(--color-danger)' : '' }}">KES {{ number_format($cashOutstanding, 2) }}</span>
                </li>
                <li>
                    <span class="qs-label">Expenses</span>
                    <span class="qs-value" style="color:var(--color-danger);">KES {{ number_format($monthExpenses, 2) }}</span>
                </li>
            </ul>
            <div style="padding:10px 18px 16px; height:80px; position:relative;">
                <p class="cashflow-label">Daily revenue — last 14 days</p>
                <canvas id="sparklineChart"></canvas>
            </div>
        </div>

        @if($business->hasFeature('customers') && !$topCustomers->isEmpty())
        <div class="dash-panel" style="margin-top:16px;">
            <div class="dash-panel-header">
                <h2 class="dash-panel-title">Top customers</h2>
                <span class="dash-panel-meta">This month</span>
            </div>
            <table>
                <thead><tr><th>#</th><th>Customer</th><th>Orders</th><th>Spent</th></tr></thead>
                <tbody>
                    @foreach($topCustomers as $i => $c)
                    <tr>
                        <td data-label="#" style="color:#aaa;font-weight:700;font-size:12px;">{{ $i + 1 }}</td>
                        <td data-label="Customer"><a href="{{ route('customers.show', $c->id) }}" style="font-weight:700;color:#0a0a0a;">{{ $c->name }}</a></td>
                        <td data-label="Orders">{{ $c->order_count }}</td>
                        <td data-label="Spent"><strong>KES {{ number_format($c->total_spent, 2) }}</strong></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

</div>

{{-- Row 4: New visualizations — category mix, payment mix, peak hours --}}
<div class="dash-three-col">
    <div class="dash-panel">
        <div class="dash-panel-header">
            <h2 class="dash-panel-title">Sales by category</h2>
            <span class="dash-panel-meta">This month</span>
        </div>
        @if(empty($categoryLabels))
            <div class="dash-panel-body" style="color:#888;font-size:13px;">No categorized sales this month.</div>
        @else
        <div class="chart-wrapper" style="height:220px;position:relative;padding:14px 18px;">
            <canvas id="categoryChart"></canvas>
        </div>
        @endif
    </div>

    <div class="dash-panel">
        <div class="dash-panel-header">
            <h2 class="dash-panel-title">Payment method mix</h2>
            <span class="dash-panel-meta">This month</span>
        </div>
        @if(empty($paymentLabels))
            <div class="dash-panel-body" style="color:#888;font-size:13px;">No sales this month.</div>
        @else
        <div class="chart-wrapper" style="height:220px;position:relative;padding:14px 18px;">
            <canvas id="paymentChart"></canvas>
        </div>
        @endif
    </div>

    <div class="dash-panel">
        <div class="dash-panel-header">
            <h2 class="dash-panel-title">Peak hours</h2>
            <span class="dash-panel-meta">Last 30 days</span>
        </div>
        <div class="chart-wrapper" style="height:220px;position:relative;padding:14px 18px;">
            <canvas id="peakHoursChart"></canvas>
        </div>
        <p class="chart-hint" style="padding:0 18px 14px;">Busiest hours tell you when to schedule more staff.</p>
    </div>
</div>

{{-- Sales Awaiting Payment — recent, non-credit sales still unpaid. Distinct
     from "Overdue invoices" below (30+ days old, an accounts-receivable
     aging concern) — this catches a much more time-sensitive case: a
     cash/M-Pesa/bank sale where stock already left the shelf at sale time
     but payment never actually landed (an M-Pesa STK push the customer
     cancelled or let time out is the most common cause). --}}
@if($awaitingPaymentCount > 0)
<div class="dash-panel">
    <div class="dash-panel-header">
        <h2 class="dash-panel-title">Sales awaiting payment</h2>
        <span class="dash-panel-meta" style="color:var(--color-danger);">{{ $awaitingPaymentCount }} unpaid &mdash; KES {{ number_format($awaitingPaymentAmount, 2) }} at risk</span>
    </div>
    <table>
        <thead>
            <tr>
                <th>Invoice</th>
                <th>Customer</th>
                <th>Method</th>
                <th>Balance</th>
                <th>Age</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($awaitingPaymentSales as $sale)
            <tr>
                <td data-label="Invoice"><a href="{{ route('sales.show', $sale) }}" style="font-weight:700;color:#0a0a0a;">{{ $sale->invoice_number }}</a></td>
                <td data-label="Customer">{{ $sale->customer->name ?? 'Walk-in' }}</td>
                <td data-label="Method">{{ ucfirst(str_replace('_', ' ', $sale->payment_method)) }}</td>
                <td data-label="Balance" style="color:var(--color-danger);font-weight:700;">KES {{ number_format($sale->balance_due, 2) }}</td>
                <td data-label="Age" style="color:#888;font-size:12px;">{{ $sale->created_at->diffForHumans() }}</td>
                <td data-label="">
                    @if($failedMpesaSaleIds->contains($sale->id))
                        <span class="badge badge--danger" style="font-size:11px;">Payment failed</span>
                    @else
                        <span class="badge badge--neutral" style="font-size:11px;">Awaiting</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @if($awaitingPaymentCount > 5)
    <div style="padding:12px 18px;border-top:1px solid #f0f0f0;">
        <a href="{{ route('sales.index', ['payment_status'=>'unpaid']) }}" style="font-size:13px;color:#0a0a0a;font-weight:600;">
            View all {{ $awaitingPaymentCount }} unpaid sales →
        </a>
    </div>
    @endif
</div>
@endif

{{-- Overdue Invoices --}}
@if($overdueCount > 0)
<div class="dash-panel">
    <div class="dash-panel-header">
        <h2 class="dash-panel-title">Overdue invoices</h2>
        <span class="dash-panel-meta" style="color:var(--color-danger);">{{ $overdueCount }} overdue &mdash; KES {{ number_format($overdueAmount, 2) }}</span>
    </div>
    <table>
        <thead>
            <tr>
                <th>Invoice</th>
                <th>Customer</th>
                <th>Balance</th>
                <th>Age</th>
            </tr>
        </thead>
        <tbody>
            @foreach($overdueInvoices as $inv)
            <tr>
                <td data-label="Invoice"><a href="{{ route('sales.show', $inv) }}" style="font-weight:700;color:#0a0a0a;">{{ $inv->invoice_number }}</a></td>
                <td data-label="Customer">{{ $inv->customer->name ?? 'Walk-in' }}</td>
                <td data-label="Balance" style="color:var(--color-danger);font-weight:700;">KES {{ number_format($inv->balance_due, 2) }}</td>
                <td data-label="Age" style="color:#888;font-size:12px;">{{ $inv->created_at->diffForHumans() }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @if($overdueCount > 5)
    <div style="padding:12px 18px;border-top:1px solid #f0f0f0;">
        <a href="{{ route('sales.index', ['payment_status'=>'unpaid']) }}" style="font-size:13px;color:#0a0a0a;font-weight:600;">
            View all {{ $overdueCount }} overdue invoices →
        </a>
    </div>
    @endif
</div>
@endif

@endrole {{-- end owner/manager --}}

{{-- Cashier: their own recent sales --}}
@cashier
<div class="dash-panel">
    <div class="dash-panel-header">
        <h2 class="dash-panel-title">My recent sales</h2>
        <a href="{{ route('sales.create') }}" class="quick-action-btn quick-action-primary" style="padding:5px 13px;font-size:12px;">New sale</a>
    </div>
    @if($recentSales->isEmpty())
        <div class="dash-panel-body" style="color:#888;font-size:13px;">You haven't made any sales yet.</div>
    @else
    <table>
        <thead><tr><th>Invoice</th><th>Customer</th><th>Total</th><th>Status</th><th>Time</th></tr></thead>
        <tbody>
            @foreach($recentSales as $sale)
            <tr>
                <td data-label="Invoice"><a href="{{ route('sales.show', $sale) }}" style="font-weight:700;color:#0a0a0a;">{{ $sale->invoice_number }}</a></td>
                <td data-label="Customer">{{ $sale->customer->name ?? 'Walk-in' }}</td>
                <td data-label="Total"><strong>KES {{ number_format($sale->total_amount, 2) }}</strong></td>
                <td data-label="Status">
                    @if($sale->payment_status === 'paid') <span class="badge-success">Paid</span>
                    @elseif($sale->payment_status === 'partial') <span class="badge-warning">Partial</span>
                    @else <span class="badge-danger">Unpaid</span>
                    @endif
                </td>
                <td data-label="Time" style="color:#888;font-size:12px;">{{ $sale->created_at->format('g:i A') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</div>
@endcashier

</div>{{-- end .page --}}

@role('owner','overall_manager','manager')
<script>
    var CHART_LABELS      = @json($chartLabels);
    var CHART_REVENUE     = @json($chartRevenue);
    var CHART_EXPENSES    = @json($chartExpenses);
    var CHART_PROFIT      = @json($chartProfit);
    var CHART_GRANULARITY = 'month';
    var CHART_RANGE_START = @json($chartRangeStart);
    var CHART_RANGE_END   = @json($chartRangeEnd);
    var SPARKLINE_DATA    = @json($sparklineData);

    var CATEGORY_LABELS   = @json($categoryLabels);
    var CATEGORY_REVENUE  = @json($categoryRevenue);
    var PAYMENT_LABELS    = @json($paymentLabels);
    var PAYMENT_REVENUE   = @json($paymentRevenue);
    var PEAK_HOUR_LABELS  = @json($peakHourLabels);
    var PEAK_HOUR_COUNTS  = @json($peakHourCounts);

    var DASHBOARD_CHART_DATA_URL = @json(route('dashboard.chart-data'));
    var DASHBOARD_KPI_URL        = @json(route('dashboard.kpi-snapshot'));
    var SALES_INDEX_URL          = @json(route('sales.index'));
</script>
@endrole

@endsection

@push('scripts')
@role('owner','overall_manager','manager')
    <script src="{{ asset('js/chart.umd.min.js') }}?v={{ @filemtime(public_path('js/chart.umd.min.js')) ?: '1' }}"></script>
    <script src="{{ asset('js/dashboard.js') }}?v={{ @filemtime(public_path('js/dashboard.js')) ?: '1' }}"></script>
@endrole
@endpush

