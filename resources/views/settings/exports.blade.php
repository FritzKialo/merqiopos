@extends('layouts.app')
@section('title', 'Export Data')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}?v={{ @filemtime(public_path('css/settings.css')) ?: '1' }}">
@endpush

@section('content')
<div class="page">

<div class="page-header">
    <div>
        <h1 class="page-title">Settings</h1>
        <p class="page-subtitle">Manage your business and account</p>
    </div>
</div>

<div class="settings-layout">

    @include('settings._nav')

    <div>

        <div class="settings-grid-2" style="display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap: var(--space-5); margin-bottom: var(--space-5);">

            <div class="settings-card">
                <div class="settings-card-header">
                    <h2>QuickBooks Journal Export</h2>
                    <p>Exports sales, expenses, and payroll as a double-entry journal CSV that can be imported into QuickBooks Online.</p>
                </div>
                <div class="settings-card-body">
                    <form method="GET" action="{{ route('settings.export.quickbooks') }}">
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label class="form-label">From</label>
                                <input type="date" name="from" class="form-control" value="{{ now()->startOfMonth()->toDateString() }}">
                            </div>
                            <div class="form-group">
                                <label class="form-label">To</label>
                                <input type="date" name="to" class="form-control" value="{{ now()->toDateString() }}">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Download QuickBooks CSV</button>
                    </form>
                </div>
            </div>

            <div class="settings-card">
                <div class="settings-card-header">
                    <h2>Xero Invoice Export</h2>
                    <p>Exports sales, expenses, and payroll in Xero's CSV import format (invoices and bills).</p>
                </div>
                <div class="settings-card-body">
                    <form method="GET" action="{{ route('settings.export.xero') }}">
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label class="form-label">From</label>
                                <input type="date" name="from" class="form-control" value="{{ now()->startOfMonth()->toDateString() }}">
                            </div>
                            <div class="form-group">
                                <label class="form-label">To</label>
                                <input type="date" name="to" class="form-control" value="{{ now()->toDateString() }}">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Download Xero CSV</button>
                    </form>
                </div>
            </div>

        </div>

        <div class="settings-card">
            <div class="settings-card-header">
                <h2>What gets exported?</h2>
            </div>
            <div class="settings-card-body">
                <div class="settings-grid-3" style="display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap: var(--space-4); font-size:0.9rem;">
                    <div>
                        <strong style="display:block; margin-bottom:6px;">Sales</strong>
                        <ul style="margin:0; padding-left:16px; line-height:1.8; color: var(--color-text-muted);">
                            <li>Completed sales invoices</li>
                            <li>Customer names</li>
                            <li>Invoice numbers</li>
                            <li>Revenue amounts</li>
                        </ul>
                    </div>
                    <div>
                        <strong style="display:block; margin-bottom:6px;">Expenses</strong>
                        <ul style="margin:0; padding-left:16px; line-height:1.8; color: var(--color-text-muted);">
                            <li>All expense categories</li>
                            <li>Expense amounts</li>
                            <li>Expense dates</li>
                            <li>Descriptions</li>
                        </ul>
                    </div>
                    <div>
                        <strong style="display:block; margin-bottom:6px;">Payroll</strong>
                        <ul style="margin:0; padding-left:16px; line-height:1.8; color: var(--color-text-muted);">
                            <li>Paid payroll periods</li>
                            <li>Net pay totals</li>
                            <li>Payment dates</li>
                            <li>Period labels</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
</div>{{-- end .page --}}
@endsection
