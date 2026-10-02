@extends('layouts.app')
@section('title', 'eTIMS Settings')

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

        {{-- Status Banner --}}
        @if($business->isEtimsConfigured())
            <div class="alert alert-success" style="margin-bottom: var(--space-5);">
                eTIMS is <strong>configured and enabled</strong>. Invoices and sales will be submitted automatically.
            </div>
        @else
            <div class="alert alert-warning" style="margin-bottom: var(--space-5);">
                eTIMS is <strong>not configured</strong>. Configure your KRA eTIMS credentials below.
            </div>
        @endif

        <div class="settings-card">
            <div class="settings-card-header">
                <h2 class="settings-card-title">KRA eTIMS Integration</h2>
                <p class="settings-card-desc">Connect to Kenya Revenue Authority's Electronic Tax Invoice Management System (eTIMS) for VAT compliance.</p>
            </div>
            <div class="settings-card-body">
                <form method="POST" action="{{ route('settings.etims.update') }}">
                    @csrf
                    @method('POST')

                    <div class="form-group" style="margin-bottom: 1.25rem;">
                        <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                            <input type="hidden" name="etims_enabled" value="0">
                            <input type="checkbox" name="etims_enabled" value="1"
                                   {{ $business->etims_enabled ? 'checked' : '' }}
                                   style="width:18px;height:18px;">
                            <span style="font-weight:600;">Enable eTIMS Submission</span>
                        </label>
                        <p style="color:var(--color-text-muted);font-size:0.85rem;margin-top:0.25rem;">When enabled, invoices and sales will be automatically submitted to KRA eTIMS.</p>
                    </div>

                    <div class="form-group" style="margin-bottom: 1.25rem;">
                        <label class="form-label">Environment</label>
                        <select name="etims_environment" class="form-control">
                            <option value="sandbox" {{ $business->etims_environment === 'sandbox' ? 'selected' : '' }}>Sandbox (Testing)</option>
                            <option value="production" {{ $business->etims_environment === 'production' ? 'selected' : '' }}>Production (Live)</option>
                        </select>
                        <p style="color:var(--color-text-muted);font-size:0.85rem;margin-top:0.25rem;">Use Sandbox for testing. Switch to Production when you are live with KRA.</p>
                    </div>

                    <div class="form-group" style="margin-bottom: 1.25rem;">
                        <label class="form-label">Device Serial Number</label>
                        <input type="text" name="etims_device_serial" class="form-control"
                               value="{{ $business->etims_device_serial }}"
                               placeholder="Serial number on your approved OSCU service request"
                               maxlength="100">
                        <p style="color:var(--color-text-muted);font-size:0.85rem;margin-top:0.25rem;">The device serial number KRA gave you when your OSCU service request was approved.</p>
                    </div>

                    <div class="form-group" style="margin-bottom: 1.25rem;">
                        <label class="form-label">Branch ID</label>
                        <input type="text" name="etims_bhf_id" class="form-control" style="max-width:120px;"
                               value="{{ $business->etims_bhf_id ?: '00' }}" maxlength="2">
                        <p style="color:var(--color-text-muted);font-size:0.85rem;margin-top:0.25rem;">Two digits. 00 is the head office; 01, 02 &hellip; are branches.</p>
                    </div>

                    <div class="form-group" style="margin-bottom: 1.75rem;">
                        <label class="form-label">Default Item Classification Code</label>
                        <input type="text" name="etims_default_item_cls_cd" class="form-control" style="max-width:220px;"
                               value="{{ $business->etims_default_item_cls_cd }}" maxlength="10">
                        <p style="color:var(--color-text-muted);font-size:0.85rem;margin-top:0.25rem;">KRA requires every item to carry a classification code (10 digits, from KRA's item classification list). Used for products that don't have their own.</p>
                    </div>

                    <div style="display:flex;gap:0.75rem;align-items:center;">
                        <button type="submit" class="btn btn-primary">Save eTIMS Settings</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Device activation --}}
        <div class="settings-card" style="margin-top: var(--space-5);">
            <div class="settings-card-header">
                <h2>Device Activation</h2>
                <p class="settings-card-desc">After saving the PIN and device serial above, activate the device with KRA. KRA returns a communication key that authorises all later submissions.</p>
            </div>
            <div class="settings-card-body">
                @if($business->etims_initialized_at)
                    <p style="font-size:0.9rem;margin-bottom:1rem;">
                        Activated {{ $business->etims_initialized_at->format('d M Y H:i') }}
                        @if($business->etims_sdc_id) &middot; CU ID: <strong>{{ $business->etims_sdc_id }}</strong>@endif
                    </p>
                @endif
                <form method="POST" action="{{ route('settings.etims.activate') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline">{{ $business->etims_initialized_at ? 'Re-activate device' : 'Activate device with KRA' }}</button>
                </form>
            </div>
        </div>

        {{-- Info Card --}}
        <div class="settings-card" style="margin-top: var(--space-5);">
            <div class="settings-card-header">
                <h2>About KRA eTIMS</h2>
            </div>
            <div class="settings-card-body">
                <ul style="color:var(--color-text-muted); font-size:0.85rem; line-height:1.8; padding-left:1.25rem; margin:0;">
                    <li>KRA requires eTIMS receipts from VAT-registered businesses, and it is also available to non-VAT taxpayers (they report sales as tax type D).</li>
                    <li>All sales invoices must be submitted to KRA in real-time.</li>
                    <li>Your business KRA PIN must be set in your Business Profile.</li>
                    <li>Request an OSCU device on the <a href="https://etims.kra.go.ke" target="_blank" style="color:var(--color-primary);">KRA eTIMS portal</a> (sandbox: etims-sbx.kra.go.ke) to get your device serial number.</li>
                    <li>Test in Sandbox mode before going live.</li>
                </ul>
            </div>
        </div>

    </div>
</div>
</div>
@endsection
