@extends('layouts.app')
@section('title', 'Statement — ' . $customer->name)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/customers.css') }}">
    <style>
        .statement-filter-form {
            display: flex;
            align-items: flex-end;
            gap: var(--space-md);
            flex-wrap: wrap;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: var(--space-md);
            margin-bottom: var(--space-lg);
        }
        .statement-filter-form .form-group {
            margin-bottom: 0;
        }
        .statement-filter-form label {
            font-size: var(--text-sm);
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 4px;
            display: block;
        }
        .tx-sale   td { color: var(--color-primary); }
        .tx-payment td { color: var(--color-success); }
        .tx-balance { font-weight: 600; }
        @media print {
            .page-header, .statement-filter-form,
            .profile-actions, .btn, form { display: none !important; }
            .wrapper, .card { box-shadow: none !important; border: none !important; }
            body { background: white !important; }
        }
    </style>
@endpush

@section('content')
<div class="page">

{{-- Page Header --}}
<div class="page-header">
    <div>
        <h1 class="page-title">
            Account Statement
        </h1>
        <p class="page-subtitle">{{ $customer->name }}</p>
    </div>
    <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
        <a href="{{ route('customers.statement.pdf', array_merge(['customer' => $customer->id], ['from_date' => $fromDate, 'to_date' => $toDate])) }}"
           class="btn btn-primary" target="_blank">
            Download PDF
        </a>
        @if($customer->email)
        <span style="font-size: var(--text-sm); color: var(--text-secondary);">
            {{ $customer->email }}
        </span>
        @endif
        <a href="{{ route('customers.show', $customer) }}" class="btn btn-outline">
            Back
        </a>
    </div>
</div>

{{-- Alerts --}}
@if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger">
        {{ session('error') }}
    </div>
@endif

{{-- Date Filter --}}
<form method="GET" action="{{ route('customers.statement', $customer) }}" class="statement-filter-form">
    <div class="form-group">
        <label>From Date</label>
        <input type="date" name="from_date" class="form-control"
               value="{{ $fromDate }}">
    </div>
    <div class="form-group">
        <label>To Date</label>
        <input type="date" name="to_date" class="form-control"
               value="{{ $toDate }}">
    </div>
    <div>
        <button type="submit" class="btn btn-primary">
            Filter
        </button>
    </div>
</form>

{{-- Customer Info Card --}}
<div class="report-section" style="margin-bottom: var(--space-lg); padding: var(--space-lg);">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: var(--space-md);">
        <div>
            <div style="font-size: var(--text-xs); color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Customer</div>
            <div style="font-weight: 600;">{{ $customer->name }}</div>
        </div>
        @if($customer->phone)
        <div>
            <div style="font-size: var(--text-xs); color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Phone</div>
            <div>{{ $customer->phone }}</div>
        </div>
        @endif
        @if($customer->email)
        <div>
            <div style="font-size: var(--text-xs); color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Email</div>
            <div>{{ $customer->email }}</div>
        </div>
        @endif
        @if($customer->address)
        <div>
            <div style="font-size: var(--text-xs); color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Address</div>
            <div>{{ $customer->address }}</div>
        </div>
        @endif
        <div>
            <div style="font-size: var(--text-xs); color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Statement Period</div>
            <div>{{ \Carbon\Carbon::parse($fromDate)->format('d M Y') }} — {{ \Carbon\Carbon::parse($toDate)->format('d M Y') }}</div>
        </div>
    </div>
</div>

{{-- KPI Strip --}}
<div class="kpi-strip">
    <div class="kpi-item">
        <span class="kpi-value">KES {{ number_format($summary['total_sales'], 2) }}</span>
        <span class="kpi-label">Total Purchases</span>
    </div>
    <div class="kpi-item">
        <span class="kpi-value" style="color: var(--color-success);">KES {{ number_format($summary['total_paid'], 2) }}</span>
        <span class="kpi-label">Total Paid</span>
    </div>
    <div class="kpi-item">
        <span class="kpi-value" style="{{ $summary['outstanding'] > 0 ? 'color: var(--color-danger);' : 'color: var(--color-success);' }}">
            KES {{ number_format($summary['outstanding'], 2) }}
        </span>
        <span class="kpi-label">Outstanding Balance</span>
    </div>
</div>

{{-- Transactions Table --}}
<div class="report-section">
    <div class="report-section-header">
        <h2>Transactions</h2>
    </div>

    @if(empty($transactions))
        <div class="empty-state">
            <div class="empty-state-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1em; height:1em;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <h3>No transactions found</h3>
            <p>No transactions recorded for this period.</p>
        </div>
    @else
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Type</th>
                        <th>Amount (KES)</th>
                        <th>Running Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transactions as $tx)
                    <tr class="{{ $tx['type'] === 'payment' ? 'tx-payment' : 'tx-sale' }}">
                        <td data-label="Date" style="white-space: nowrap;">
                            {{ \Carbon\Carbon::parse($tx['date'])->format('d M Y') }}
                        </td>
                        <td data-label="Reference">
                            @if($tx['type'] === 'sale')
                                <a href="{{ route('sales.show', $tx['sale']) }}"
                                   style="color: var(--primary); font-weight: 600;">
                                    {{ $tx['reference'] }}
                                </a>
                            @else
                                <span style="color: var(--success);">
                                    {{ $tx['reference'] }}
                                </span>
                            @endif
                        </td>
                        <td data-label="Type">
                            @if($tx['type'] === 'sale')
                                <span class="badge badge-primary" style="background: #eff6ff; color: var(--primary); border: 1px solid #bfdbfe;">
                                    Sale
                                </span>
                            @else
                                <span class="badge badge-success">
                                    Payment
                                </span>
                            @endif
                        </td>
                        <td data-label="Amount" class="tx-balance">
                            @if($tx['amount'] > 0)
                                <span style="color: var(--primary);">
                                    + KES {{ number_format($tx['amount'], 2) }}
                                </span>
                            @else
                                <span style="color: var(--success);">
                                    - KES {{ number_format(abs($tx['amount']), 2) }}
                                </span>
                            @endif
                        </td>
                        <td data-label="Balance" class="tx-balance">
                            @if($tx['balance'] > 0)
                                <span style="color: var(--danger);">
                                    KES {{ number_format($tx['balance'], 2) }}
                                </span>
                            @elseif($tx['balance'] == 0)
                                <span style="color: var(--success);">KES 0.00</span>
                            @else
                                <span style="color: var(--success);">
                                    KES {{ number_format($tx['balance'], 2) }}
                                </span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="background: var(--background);">
                        <td colspan="3"
                            style="text-align: right; font-weight: 700; padding: 12px 16px;">
                            Outstanding Balance
                        </td>
                        <td data-label="Outstanding"
                            colspan="2"
                            style="font-weight: 700; font-size: 1.05rem; color: {{ $summary['outstanding'] > 0 ? 'var(--danger)' : 'var(--success)' }}; padding: 12px 16px;">
                            KES {{ number_format($summary['outstanding'], 2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>

</div>{{-- end .page --}}
@endsection

