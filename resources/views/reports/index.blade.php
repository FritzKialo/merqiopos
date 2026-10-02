@extends('layouts.app')
@section('title', 'Reports')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/reports.css') }}?v={{ @filemtime(public_path('css/reports.css')) ?: '1' }}">
@endpush

@section('content')
@php $business = Auth::user()->currentBusiness(); @endphp
<div class="page">

<div class="page-header">
    <div>
        <h1 class="page-title">Reports</h1>
        <p class="page-subtitle">Business insights and analytics</p>
    </div>
</div>

@if(!$business->hasFeature('reports_advanced'))
<div class="alert alert-warning" style="margin-bottom:1.5rem;display:flex;align-items:center;gap:.75rem;">
    <span>Advanced reports are available on the <strong>Business plan</strong> and above.
    <a href="{{ route('settings.subscription') }}" style="color:inherit;font-weight:600;text-decoration:underline;">Upgrade now →</a></span>
</div>
@endif

{{-- ── Sales & Finance ───────────────────────────────────────── --}}
<h2 style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--color-text-muted);margin:0 0 12px;">Sales &amp; Finance</h2>
<div class="reports-grid" style="margin-bottom:2rem;">

    {{-- Profit & Loss --}}
    @if($business->hasFeature('reports_advanced'))
        <a href="{{ route('reports.profit_loss') }}" class="report-card">
            <div class="report-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1em; height:1em;"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></div>
            <h3>Profit &amp; Loss</h3>
            <p>Revenue, cost of goods, gross profit, operating expenses and net profit by period.</p>
            <span class="report-card-action">View Report →</span>
        </a>
    @else
        <div class="report-card report-card--locked">
            <div class="report-card-lock-badge">Business Plan</div>
            <div class="report-card-icon" style="opacity:.4;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1em; height:1em;"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></div>
            <h3 style="opacity:.5;">Profit &amp; Loss</h3>
            <p style="opacity:.5;">Revenue, cost of goods, gross profit, operating expenses and net profit by period.</p>
            <a href="{{ route('settings.subscription') }}" class="report-card-action" style="color:var(--color-primary);">Upgrade to Unlock →</a>
        </div>
    @endif

    {{-- Gross Margin --}}
    @if($business->hasFeature('reports_advanced'))
        <a href="{{ route('reports.gross-margin') }}" class="report-card">
            <div class="report-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1em; height:1em;"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div>
            <h3>Gross Margin</h3>
            <p>Product-level margin analysis — see which items are most and least profitable.</p>
            <span class="report-card-action">View Report →</span>
        </a>
    @else
        <div class="report-card report-card--locked">
            <div class="report-card-lock-badge">Business Plan</div>
            <div class="report-card-icon" style="opacity:.4;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1em; height:1em;"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div>
            <h3 style="opacity:.5;">Gross Margin</h3>
            <p style="opacity:.5;">Product-level margin analysis — see which items are most and least profitable.</p>
            <a href="{{ route('settings.subscription') }}" class="report-card-action" style="color:var(--color-primary);">Upgrade to Unlock →</a>
        </div>
    @endif

    {{-- Expenses Report --}}
    @if($business->hasFeature('reports_advanced'))
        <a href="{{ route('reports.expenses') }}" class="report-card">
            <div class="report-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1em; height:1em;"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
            <h3>Expenses Report</h3>
            <p>Detailed breakdown of expenses by category, payment method and daily trend.</p>
            <span class="report-card-action">View Report →</span>
        </a>
    @else
        <div class="report-card report-card--locked">
            <div class="report-card-lock-badge">Business Plan</div>
            <div class="report-card-icon" style="opacity:.4;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1em; height:1em;"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
            <h3 style="opacity:.5;">Expenses Report</h3>
            <p style="opacity:.5;">Detailed breakdown of expenses by category, payment method and daily trend.</p>
            <a href="{{ route('settings.subscription') }}" class="report-card-action" style="color:var(--color-primary);">Upgrade to Unlock →</a>
        </div>
    @endif

    {{-- Aged Debtors --}}
    <a href="{{ route('reports.aged-debtors') }}" class="report-card">
        <div class="report-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1em; height:1em;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></div>
        <h3>Aged Debtors</h3>
        <p>Outstanding customer balances grouped by age — 0–30, 31–60, 61–90, and 90+ days.</p>
        <span class="report-card-action">View Report →</span>
    </a>

    {{-- Sales Forecast --}}
    <a href="{{ route('reports.sales-forecast') }}" class="report-card">
        <div class="report-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1em; height:1em;"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
        <h3>Sales Forecast</h3>
        <p>Projected revenue based on historical sales trends and seasonal patterns.</p>
        <span class="report-card-action">View Report →</span>
    </a>

    {{-- VAT Return --}}
    <a href="{{ route('reports.vat-return') }}" class="report-card">
        <div class="report-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1em; height:1em;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></div>
        <h3>VAT Return</h3>
        <p>Output and input VAT summary for filing your KRA VAT return.</p>
        <span class="report-card-action">View Report →</span>
    </a>

    {{-- Withholding Tax: full route/controller/view existed with zero link
    anywhere in the app — same orphaned-feature shape found elsewhere. --}}
    <a href="{{ route('withholding-tax.index') }}" class="report-card">
        <div class="report-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1em; height:1em;"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div>
        <h3>Withholding Tax</h3>
        <p>Track and export withholding tax certificates for KRA filing.</p>
        <span class="report-card-action">View Report →</span>
    </a>

</div>

{{-- ── Balance Sheet & Cash Flow ─────────────────────────────── --}}
<h2 style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--color-text-muted);margin:0 0 12px;">Financial Statements</h2>
<div class="reports-grid" style="margin-bottom:2rem;">

    {{-- Balance Sheet --}}
    @if($business->hasFeature('reports_advanced'))
        <a href="{{ route('reports.balance-sheet') }}" class="report-card">
            <div class="report-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1em; height:1em;"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg></div>
            <h3>Balance Sheet</h3>
            <p>Assets, liabilities and equity snapshot at any point in time.</p>
            <span class="report-card-action">View Report →</span>
        </a>
    @else
        <div class="report-card report-card--locked">
            <div class="report-card-lock-badge">Business Plan</div>
            <div class="report-card-icon" style="opacity:.4;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1em; height:1em;"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg></div>
            <h3 style="opacity:.5;">Balance Sheet</h3>
            <p style="opacity:.5;">Assets, liabilities and equity snapshot at any point in time.</p>
            <a href="{{ route('settings.subscription') }}" class="report-card-action" style="color:var(--color-primary);">Upgrade to Unlock →</a>
        </div>
    @endif

    {{-- Cash Flow --}}
    @if($business->hasFeature('reports_advanced'))
        <a href="{{ route('reports.cash-flow') }}" class="report-card">
            <div class="report-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1em; height:1em;"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg></div>
            <h3>Cash Flow</h3>
            <p>Operating, investing and financing cash movements over a selected period.</p>
            <span class="report-card-action">View Report →</span>
        </a>
    @else
        <div class="report-card report-card--locked">
            <div class="report-card-lock-badge">Business Plan</div>
            <div class="report-card-icon" style="opacity:.4;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1em; height:1em;"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg></div>
            <h3 style="opacity:.5;">Cash Flow</h3>
            <p style="opacity:.5;">Operating, investing and financing cash movements over a selected period.</p>
            <a href="{{ route('settings.subscription') }}" class="report-card-action" style="color:var(--color-primary);">Upgrade to Unlock →</a>
        </div>
    @endif

    {{-- Cash Reconciliation --}}
    <a href="{{ route('reports.cash-reconciliation') }}" class="report-card">
        <div class="report-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1em; height:1em;"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg></div>
        <h3>Cash Reconciliation</h3>
        <p>Whether the physical cash in your tills matches what sales, expenses and closed shifts say it should.</p>
        <span class="report-card-action">View Report →</span>
    </a>

</div>

{{-- ── Inventory & Staff ─────────────────────────────────────── --}}
<h2 style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--color-text-muted);margin:0 0 12px;">Inventory &amp; Staff</h2>
<div class="reports-grid" style="margin-bottom:2rem;">

    {{-- Inventory Report --}}
    <a href="{{ route('inventory.index') }}" class="report-card">
        <div class="report-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1em; height:1em;"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></div>
        <h3>Inventory Report</h3>
        <p>Current stock levels, valuation, low stock alerts and product performance overview.</p>
        <span class="report-card-action">View Inventory →</span>
    </a>

    {{-- Dead Stock --}}
    <a href="{{ route('reports.dead-stock') }}" class="report-card">
        <div class="report-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1em; height:1em;"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg></div>
        <h3>Dead Stock</h3>
        <p>Products with no sales activity — identify slow movers tying up cash.</p>
        <span class="report-card-action">View Report →</span>
    </a>

    {{-- Staff Performance --}}
    <a href="{{ route('reports.staff-performance') }}" class="report-card">
        <div class="report-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1em; height:1em;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
        <h3>Staff Performance</h3>
        <p>Sales per team member — units sold, revenue generated and average transaction value.</p>
        <span class="report-card-action">View Report →</span>
    </a>

</div>

{{-- ── Data Export ───────────────────────────────────────────── --}}
<h2 style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--color-text-muted);margin:0 0 12px;">Data Export</h2>
<div class="reports-grid">

    @if($business->hasFeature('data_export'))
        <a href="{{ route('reports.export') }}" class="report-card">
            <div class="report-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1em; height:1em;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg></div>
            <h3>Export Data</h3>
            <p>Download sales, inventory, customers and expenses as CSV files for external analysis.</p>
            <span class="report-card-action">Export →</span>
        </a>
    @else
        <div class="report-card report-card--locked">
            <div class="report-card-lock-badge">Enterprise Plan</div>
            <div class="report-card-icon" style="opacity:.4;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1em; height:1em;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg></div>
            <h3 style="opacity:.5;">Export Data</h3>
            <p style="opacity:.5;">Download sales, inventory, customers and expenses as CSV files for external analysis.</p>
            <a href="{{ route('settings.subscription') }}" class="report-card-action" style="color:var(--color-accent);">Upgrade to Unlock →</a>
        </div>
    @endif

</div>

</div>{{-- end .page --}}
@endsection
