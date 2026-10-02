@extends('layouts.app')
@section('title', 'Google Sheets Export')

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
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if($business->hasGoogleSheetsConnected())
        <div class="settings-card" style="margin-bottom: var(--space-5);">
            <div class="settings-card-header">
                <h2>Google Sheets Export</h2>
                <p>Your shop's data is being exported live to a Google Sheets workbook in your connected Google account.</p>
            </div>
            <div class="settings-card-body">
                <div class="alert alert-success" style="margin-bottom: var(--space-5);">
                    <div>
                        <div style="font-weight: 600; color: var(--color-text);">Connected</div>
                        <div style="font-size: var(--text-sm); color: var(--color-text-muted);">
                            Connected on {{ $business->google_sheets_connected_at?->format('d M Y') }}.
                            @if($business->google_sheets_last_synced_at)
                                Last synced {{ $business->google_sheets_last_synced_at->diffForHumans() }}.
                            @endif
                        </div>
                    </div>
                </div>

                <p style="font-size: var(--text-sm); color: var(--color-text-muted); margin-bottom: var(--space-4);">
                    <strong>Sales</strong> and <strong>Expenses</strong> add a new row the moment they happen.
                    <strong>Inventory</strong> and <strong>Customers</strong> refresh their whole tab on any change, so they always show current stock levels and balances rather than a history of edits.
                </p>

                <div class="form-actions" style="gap: var(--space-3);">
                    <a href="https://docs.google.com/spreadsheets/d/{{ $business->google_sheets_spreadsheet_id }}" target="_blank" rel="noopener" class="btn btn-primary">
                        Open Spreadsheet
                    </a>
                    <form method="POST" action="{{ route('settings.google-sheets.sync') }}" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-secondary">Re-sync All Data Now</button>
                    </form>
                    <form method="POST" action="{{ route('settings.google-sheets.disconnect') }}" style="display:inline;"
                        onsubmit="return confirm('Disconnect Google Sheets? The spreadsheet already created in your Google Drive will stay, but it will stop receiving updates.')">
                        @csrf
                        <button type="submit" class="btn btn-danger">Disconnect</button>
                    </form>
                </div>
            </div>
        </div>

        @else
        <div class="settings-card" style="margin-bottom: var(--space-5);">
            <div class="settings-card-header">
                <h2>Google Sheets Export</h2>
                <p>Automatically export your sales, inventory, customers, and expenses to a Google Sheets workbook in your own Google account.</p>
            </div>
            <div class="settings-card-body">
                <ul style="font-size: var(--text-sm); color: var(--color-text-muted); padding-left: var(--space-5); line-height: 1.8; margin-bottom: var(--space-5);">
                    <li>A new workbook is created in your Google Drive the moment you connect — you own it, not Merqio POS</li>
                    <li>Sales and Expenses log every new transaction as it happens</li>
                    <li>Inventory and Customers always reflect current stock and balances</li>
                    <li>Disconnect anytime — your existing spreadsheet stays in your Drive, it just stops updating</li>
                </ul>

                <a href="{{ route('settings.google-sheets.connect') }}" class="btn btn-primary">
                    Connect Google Account
                </a>
            </div>
        </div>
        @endif
    </div>
</div>
</div>
@endsection
