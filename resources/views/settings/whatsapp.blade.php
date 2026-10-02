@extends('layouts.app')
@section('title', 'WhatsApp Settings')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}?v={{ @filemtime(public_path('css/settings.css')) ?: '1' }}">
@endpush

@section('content')
<div class="page">

<div class="page-header">
    <div>
        <h1 class="page-title">WhatsApp Notifications</h1>
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

        <div class="settings-card" style="margin-bottom: var(--space-5);">
            <div class="settings-card-header">
                <h2>Notification Settings</h2>
                <p>Uses the same SMS credentials (provider, API key, sender ID) configured under SMS settings.</p>
            </div>
            <div class="settings-card-body">
                <form method="POST" action="{{ route('settings.whatsapp.update') }}">
                    @csrf @method('POST')
                    <div class="form-group" style="display:flex; align-items:center; gap: var(--space-3);">
                        <input type="hidden" name="whatsapp_enabled" value="0">
                        <input type="checkbox" name="whatsapp_enabled" value="1" id="wa_enabled"
                               {{ $business->whatsapp_enabled ? 'checked' : '' }}
                               style="width:18px; height:18px; cursor:pointer;">
                        <label for="wa_enabled" style="font-weight:600; cursor:pointer;">Enable WhatsApp notifications</label>
                    </div>

                    <div style="background:var(--color-surface-2); border:1px solid var(--color-border); border-radius:var(--radius); padding: var(--space-4); margin-bottom: var(--space-5); font-size:0.85rem;">
                        <strong>What gets sent via WhatsApp:</strong>
                        <ul style="margin:var(--space-2) 0 0 1.2rem;">
                            <li>Sale receipt confirmation to customer after each sale</li>
                            <li>Invoice payment reminder when you send an invoice</li>
                            <li>Low stock alerts to the business owner</li>
                        </ul>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="settings-card">
            <div class="settings-card-header">
                <h2>Send Test Message</h2>
            </div>
            <div class="settings-card-body">
                <form method="POST" action="{{ route('settings.whatsapp.test') }}" style="display:flex; gap: var(--space-3); align-items:flex-end;">
                    @csrf
                    <div class="form-group" style="flex:1; margin-bottom:0;">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" placeholder="+254712345678">
                    </div>
                    <button type="submit" class="btn btn-secondary">Send Test</button>
                </form>
            </div>
        </div>

    </div>
</div>
</div>{{-- end .page --}}
@endsection
