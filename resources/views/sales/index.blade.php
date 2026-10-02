@extends('layouts.app')
@section('title', 'Sales')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/sales.css') }}?v={{ @filemtime(public_path('css/sales.css')) ?: '1' }}">
@endpush

@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Sales</h1>
            <p class="page-subtitle">Track revenue, transactions, and outstanding payments.</p>
        </div>
        <div class="action-buttons">
            @if(auth()->user()->currentBusiness()?->hasFeature('data_export'))
            <a href="{{ route('sales.export') }}" class="btn btn--outline">Export CSV</a>
            @endif
            <a href="{{ route('sales.create') }}" class="btn btn--primary">New Sale</a>
        </div>
    </div>

    {{-- KPI Strip --}}
    <div class="kpi-strip">
        <div class="kpi-item">
            <span class="kpi-value">KSh {{ number_format($stats['today_total'], 2) }}</span>
            <span class="kpi-label">Today's Sales</span>
        </div>
        <div class="kpi-item">
            <span class="kpi-value">{{ $stats['today_count'] }}</span>
            <span class="kpi-label">Transactions Today</span>
        </div>
        <div class="kpi-item">
            <span class="kpi-value">KSh {{ number_format($stats['month_total'], 2) }}</span>
            <span class="kpi-label">This Month</span>
        </div>
        <div class="kpi-item">
            <span class="kpi-value" style="{{ $stats['unpaid_total'] > 0 ? 'color: var(--color-danger);' : '' }}">
                KSh {{ number_format($stats['unpaid_total'], 2) }}
            </span>
            <span class="kpi-label">Debt Total</span>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('sales.index') }}" id="filterForm">
        <div class="toolbar">
            <div class="toolbar-search">
                <input type="text" name="search" placeholder="Invoice # or customer name..." value="{{ request('search') }}">
            </div>

            <select name="payment_status" class="toolbar-select" onchange="this.form.submit()">
                <option value="">All Payments</option>
                <option value="paid" {{ request('payment_status') == 'paid' ? 'selected' : '' }}>Paid</option>
                <option value="partial" {{ request('payment_status') == 'partial' ? 'selected' : '' }}>Partial</option>
                <option value="unpaid" {{ request('payment_status') == 'unpaid' ? 'selected' : '' }}>Unpaid</option>
            </select>

            <div style="display: flex; gap: 8px;">
                <input type="date" name="date_from" class="toolbar-select" value="{{ request('date_from') }}">
                <input type="date" name="date_to" class="toolbar-select" value="{{ request('date_to') }}">
            </div>

            <div style="margin-left: auto;">
                @if(request()->hasAny(['search','payment_status','date_from','date_to']))
                    <a href="{{ route('sales.index') }}" class="btn btn--outline btn--sm">Clear</a>
                @endif
            </div>
        </div>
    </form>

    {{-- Sales Table --}}
    <div class="table-section">
        @if($sales->isEmpty())
            <div class="empty-state">
                <h3>No sales recorded</h3>
                <p>Record your first transaction to see it here.</p>
                <a href="{{ route('sales.create') }}" class="btn btn--primary" style="margin-top: 1rem;">New Sale</a>
            </div>
        @else
            <div class="table-wrapper">
                <table class="table-responsive-cards">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sales as $sale)
                        <tr>
                            <td data-label="Invoice"><span class="invoice-number">{{ $sale->invoice_number }}</span></td>
                            <td data-label="Customer"><span class="customer-name">{{ $sale->customer->name ?? ($sale->table_guest_name ?: 'Walk-in') }}</span></td>
                            <td data-label="Items">{{ $sale->items->count() }}</td>
                            <td data-label="Total"><span class="sales-total">KSh {{ number_format($sale->total_amount, 2) }}</span></td>
                            <td data-label="Paid">KSh {{ number_format($sale->paid_amount, 2) }}</td>
                            <td data-label="Balance">
                                @if($sale->balance_due > 0)
                                    <span style="color: var(--color-danger); font-weight: 700;">KSh {{ number_format($sale->balance_due, 2) }}</span>
                                @else
                                    <span style="color: var(--color-success);">—</span>
                                @endif
                            </td>
                            <td data-label="Method">
                                <span class="badge badge-neutral">{{ ucfirst(str_replace('_', ' ', $sale->payment_method)) }}</span>
                            </td>
                            <td data-label="Status">
                                @if($sale->sale_status === 'cancelled')
                                    <span class="badge badge-neutral">Cancelled</span>
                                @elseif($sale->payment_status === 'paid')
                                    <span class="badge badge-success">Paid</span>
                                @elseif($sale->payment_status === 'partial')
                                    <span class="badge badge-warning">Partial</span>
                                @else
                                    <span class="badge badge-danger">Unpaid</span>
                                @endif
                            </td>
                            <td data-label="Time" style="color: var(--color-text-muted); font-size: 0.8rem;">
                                {{ $sale->created_at->format('d M, g:i A') }}
                            </td>
                            <td data-label="Actions">
                                <div class="action-buttons">
                                    <a href="{{ route('sales.show', $sale) }}" class="btn btn--outline btn--sm">View</a>
                                    <a href="{{ route('sales.invoice', $sale) }}" class="btn btn--primary btn--sm">Invoice</a>
                                    <a href="{{ route('sales.receipt', $sale) }}" class="btn btn--outline btn--sm" target="_blank" title="Print Receipt">&#128424;</a>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $sales->links('vendor.pagination.custom') }}
        @endif
    </div>
</div>
@endsection
