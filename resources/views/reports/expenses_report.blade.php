@extends('layouts.app')
@section('title', 'Expenses Report')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/reports.css') }}?v={{ @filemtime(public_path('css/reports.css')) ?: '1' }}">
    <style>
    @media (min-width: 769px) {
        .expenses-report-table tfoot td { padding: 12px 0; }
    }
    </style>
@endpush

@section('content')
<div class="page">

<div class="page-header">
    <div>
        <h1 class="page-title">Expenses Report</h1>
        <p class="page-subtitle">{{ $months[$month] }} {{ $year }}</p>
    </div>
    <div style="display: flex; gap: 8px;">
        <button onclick="window.print()" class="btn btn--outline">Print</button>
        <a href="{{ route('reports.index') }}" class="btn btn--outline">Reports</a>
    </div>
</div>

{{-- Period Selector --}}
<form method="GET" action="{{ route('reports.expenses') }}">
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
        <div style="margin-left: auto; font-size: 0.875rem; color: var(--color-text-muted);">
            vs Last Month:
            <strong style="color: {{ $monthChange <= 0 ? 'var(--color-success)' : 'var(--color-danger)' }};">
                {{ $monthChange > 0 ? '+' : '' }}{{ $monthChange }}%
            </strong>
            &nbsp;(KES {{ number_format($lastMonthTotal, 2) }})
        </div>
    </div>
</form>

{{-- KPI Strip --}}
<div class="kpi-strip">
    <div class="kpi-item">
        <span class="kpi-value" style="color: var(--color-danger);">KES {{ number_format($totalExpenses, 2) }}</span>
        <span class="kpi-label">Total Expenses</span>
    </div>
    <div class="kpi-item">
        <span class="kpi-value">{{ $expenses->count() }}</span>
        <span class="kpi-label">Entries This Period</span>
    </div>
    <div class="kpi-item">
        <span class="kpi-value">{{ $byCategory->count() }}</span>
        <span class="kpi-label">Categories Used</span>
    </div>
    <div class="kpi-item">
        <span class="kpi-value" style="color: {{ $monthChange <= 0 ? 'var(--color-success)' : 'var(--color-danger)' }};">
            {{ $monthChange > 0 ? '+' : '' }}{{ $monthChange }}%
        </span>
        <span class="kpi-label">vs Last Month</span>
    </div>
</div>

<div class="report-layout">

    {{-- By Category --}}
    <div class="report-section">
        <div class="report-section-header">
            <h2>By Category</h2>
        </div>
        @if($byCategory->isEmpty())
            <p style="color: var(--color-text-muted); font-size: 0.875rem; text-align: center;">No data.</p>
        @else
            @php $maxCat = $byCategory->max('total') ?: 1; @endphp
            @foreach($byCategory as $row)
            <div style="margin-bottom: 1rem;">
                <div style="display: flex; justify-content: space-between; font-size: 0.875rem; margin-bottom: 4px;">
                    <span style="font-weight: 600;">
                        {{ $row->category->name ?? 'Uncategorised' }}
                        <span style="color: var(--color-text-muted); font-weight: 400;">({{ $row->count }})</span>
                    </span>
                    <span style="color: var(--color-danger);">KES {{ number_format($row->total, 2) }}</span>
                </div>
                <div class="progress-track">
                    <div class="progress-fill" style="width: {{ ($row->total / $maxCat) * 100 }}%; background: var(--color-danger);"></div>
                </div>
                <div style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 2px;">
                    Avg: KES {{ number_format($row->average, 2) }} &nbsp;|&nbsp; Highest: KES {{ number_format($row->highest, 2) }}
                </div>
            </div>
            @endforeach
        @endif
    </div>

    {{-- By Payment Method --}}
    <div class="report-section">
        <div class="report-section-header">
            <h2>By Payment Method</h2>
        </div>
        @if($byMethod->isEmpty())
            <p style="color: var(--color-text-muted); font-size: 0.875rem; text-align: center;">No data.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Method</th>
                        <th>Count</th>
                        <th>Total</th>
                        <th>Share</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($byMethod as $row)
                    <tr>
                        <td data-label="Method">
                            <span class="badge badge-neutral">{{ ucfirst(str_replace('_', ' ', $row->payment_method)) }}</span>
                        </td>
                        <td data-label="Count">{{ $row->count }}</td>
                        <td data-label="Total">
                            <strong style="color: var(--color-danger);">KES {{ number_format($row->total, 2) }}</strong>
                        </td>
                        <td data-label="Share">
                            {{ $totalExpenses > 0 ? round(($row->total / $totalExpenses) * 100, 1) . '%' : '0%' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

{{-- All Expenses Table --}}
<div class="report-section">
    <div class="report-section-header">
        <h2>All Expenses</h2>
        <span style="font-size: 0.875rem; font-weight: 700; color: var(--color-danger);">KES {{ number_format($totalExpenses, 2) }}</span>
    </div>
    @if($expenses->isEmpty())
        <p style="text-align: center; padding: 2rem; color: var(--color-text-muted); font-size: 0.875rem;">No expenses recorded this period.</p>
    @else
        <table class="expenses-report-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Method</th>
                    <th>Amount</th>
                    <th>By</th>
                </tr>
            </thead>
            <tbody>
                @foreach($expenses as $expense)
                <tr>
                    <td data-label="Date" style="white-space: nowrap;">{{ $expense->expense_date->format('d M Y') }}</td>
                    <td data-label="Title">
                        <strong>{{ $expense->title }}</strong>
                        @if($expense->description)
                            <div style="font-size: 0.75rem; color: var(--color-text-muted);">{{ Str::limit($expense->description, 40) }}</div>
                        @endif
                    </td>
                    <td data-label="Category">
                        @if($expense->category)
                            <span class="badge badge-neutral">{{ $expense->category->name }}</span>
                        @else
                            <span style="color: var(--color-text-muted);">—</span>
                        @endif
                    </td>
                    <td data-label="Method">
                        <span class="badge badge-neutral">{{ ucfirst(str_replace('_', ' ', $expense->payment_method)) }}</span>
                    </td>
                    <td data-label="Amount">
                        <strong style="color: var(--color-danger);">KES {{ number_format($expense->amount, 2) }}</strong>
                    </td>
                    <td data-label="By" style="color: var(--color-text-muted); font-size: 0.85rem;">{{ $expense->user->name }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="border-top: 2px solid var(--color-border);">
                    <td colspan="4" data-label="Total" style="font-weight: 700; text-align: right;">Total</td>
                    <td data-label="Amount" style="font-weight: 700; color: var(--color-danger);">
                        KES {{ number_format($totalExpenses, 2) }}
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    @endif
</div>

</div>
@endsection
