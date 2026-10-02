@extends('layouts.app')
@section('title', 'Profit & Loss')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/reports.css') }}?v={{ @filemtime(public_path('css/reports.css')) ?: '1' }}">
@endpush

@section('content')
<div class="page">

<div class="page-header">
    <div>
        <h1 class="page-title">Profit &amp; Loss</h1>
        <p class="page-subtitle">{{ $months[$month] }} {{ $year }}</p>
    </div>
    <div style="display: flex; gap: 8px;">
        <button onclick="window.print()" class="btn btn--outline">Print</button>
        <a href="{{ route('reports.index') }}" class="btn btn--outline">Reports</a>
    </div>
</div>

{{-- Period Selector --}}
<form method="GET" action="{{ route('reports.profit_loss') }}">
    <div class="toolbar" style="margin-bottom: 1.5rem;">
        <label style="font-size: 0.875rem; color: var(--color-text-muted);">Period:</label>
        <select name="month" class="toolbar-select" onchange="this.form.submit()">
            @foreach($months as $num => $name)
                <option value="{{ $num }}" {{ $month == $num ? 'selected' : '' }}>{{ $name }}</option>
            @endforeach
        </select>
        <select name="year" class="toolbar-select" onchange="this.form.submit()">
            @foreach($years as $y)
                <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
            @endforeach
        </select>
    </div>
</form>

{{-- KPI Strip --}}
<div class="kpi-strip">
    <div class="kpi-item">
        <span class="kpi-value" style="color: var(--color-success);">KES {{ number_format($totalRevenue, 2) }}</span>
        <span class="kpi-label">Total Revenue &mdash; {{ $salesCount }} sale(s){{ $invoiceCount > 0 ? ' + ' . $invoiceCount . ' invoice(s)' : '' }}</span>
    </div>
    <div class="kpi-item">
        <span class="kpi-value" style="color: var(--color-danger);">KES {{ number_format($cogs, 2) }}</span>
        <span class="kpi-label">Cost of Goods Sold</span>
    </div>
    <div class="kpi-item">
        <span class="kpi-value">KES {{ number_format($grossProfit, 2) }}</span>
        <span class="kpi-label">Gross Profit &mdash; {{ $grossMargin }}% margin</span>
    </div>
    <div class="kpi-item">
        <span class="kpi-value" style="color: {{ $netProfit >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }};">
            KES {{ number_format($netProfit, 2) }}
        </span>
        <span class="kpi-label">Net Profit &mdash; {{ $netMargin }}% margin</span>
    </div>
</div>

<div class="report-layout">

    {{-- Daily Revenue Chart --}}
    <div class="report-section">
        <div class="report-section-header">
            <h2>Daily Revenue</h2>
        </div>
        <div class="chart-wrapper">
            <canvas id="dailyChart"></canvas>
        </div>
    </div>

    {{-- Expense Breakdown --}}
    <div class="report-section">
        <div class="report-section-header">
            <h2>Expense Breakdown</h2>
            <span style="font-size: 0.875rem; font-weight: 700; color: var(--color-danger);">
                KES {{ number_format($totalExpenses, 2) }}
            </span>
        </div>
        @if($expensesByCategory->isEmpty() && $pettyCashTotal <= 0)
            <p style="color: var(--color-text-muted); font-size: 0.875rem; text-align: center;">No expenses this period.</p>
        @else
            @php $maxExp = max($expensesByCategory->max('total') ?: 0, $pettyCashTotal) ?: 1; @endphp
            @foreach($expensesByCategory as $row)
            <div style="margin-bottom: 1rem;">
                <div style="display: flex; justify-content: space-between; font-size: 0.875rem; margin-bottom: 4px;">
                    <span style="font-weight: 600;">{{ $row->category->name ?? 'Uncategorised' }}</span>
                    <span style="color: var(--color-danger);">KES {{ number_format($row->total, 2) }}</span>
                </div>
                <div class="progress-track">
                    <div class="progress-fill" style="width: {{ ($row->total / $maxExp) * 100 }}%; background: var(--color-danger);"></div>
                </div>
            </div>
            @endforeach
            @if($pettyCashTotal > 0)
            <div style="margin-bottom: 1rem;">
                <div style="display: flex; justify-content: space-between; font-size: 0.875rem; margin-bottom: 4px;">
                    <span style="font-weight: 600;">Petty cash disbursements</span>
                    <span style="color: var(--color-danger);">KES {{ number_format($pettyCashTotal, 2) }}</span>
                </div>
                <div class="progress-track">
                    <div class="progress-fill" style="width: {{ ($pettyCashTotal / $maxExp) * 100 }}%; background: var(--color-danger);"></div>
                </div>
            </div>
            @endif
        @endif
    </div>
</div>

{{-- P&L Summary Table --}}
<div class="report-section">
    <div class="report-section-header">
        <h2>P&amp;L Summary</h2>
    </div>
    <table class="table-plain">
        <tbody>
            <tr>
                <td style="font-weight: 700; padding: 12px 0;">Total Revenue</td>
                <td></td>
                <td style="font-weight: 700; color: var(--color-success); text-align: right; padding: 12px 0;">
                    KES {{ number_format($totalRevenue, 2) }}
                </td>
            </tr>
            <tr>
                <td style="padding: 10px 0 10px 1.5rem; color: var(--color-text-muted);">Less: Cost of Goods Sold</td>
                <td></td>
                <td style="text-align: right; padding: 10px 0; color: var(--color-danger);">
                    (KES {{ number_format($cogs, 2) }})
                </td>
            </tr>
            <tr style="border-top: 1px solid var(--color-border);">
                <td style="font-weight: 700; padding: 12px 0;">Gross Profit</td>
                <td style="color: var(--color-text-muted); font-size: 0.85rem; padding: 12px 0;">{{ $grossMargin }}% margin</td>
                <td style="font-weight: 700; text-align: right; padding: 12px 0;">
                    KES {{ number_format($grossProfit, 2) }}
                </td>
            </tr>
            <tr>
                <td style="padding: 10px 0 10px 1.5rem; color: var(--color-text-muted);">Less: Operating Expenses</td>
                <td></td>
                <td style="text-align: right; padding: 10px 0; color: var(--color-danger);">
                    (KES {{ number_format($totalExpenses, 2) }})
                </td>
            </tr>
            <tr style="border-top: 2px solid var(--color-text);">
                <td style="font-size: 1.05rem; font-weight: 700; padding: 14px 0;">Net Profit</td>
                <td style="color: var(--color-text-muted); font-size: 0.85rem; padding: 14px 0;">{{ $netMargin }}% margin</td>
                <td style="font-size: 1.05rem; font-weight: 700; color: {{ $netProfit >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }}; text-align: right; padding: 14px 0;">
                    KES {{ number_format($netProfit, 2) }}
                </td>
            </tr>
        </tbody>
    </table>
</div>

{{-- Pass data to JS --}}
<script>
    var DAILY_LABELS  = @json($dailyRevenue->pluck('day')->map(fn($d) => 'Day ' . $d));
    var DAILY_REVENUE = @json($dailyRevenue->pluck('revenue'));
</script>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/reports.js') }}?v={{ @filemtime(public_path('js/reports.js')) ?: '1' }}"></script>
@endpush
