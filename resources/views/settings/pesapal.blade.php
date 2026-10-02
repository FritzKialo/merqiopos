@extends('layouts.app')
@section('title', 'Pesapal Settings')

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

        {{-- Status banner --}}
        @if($business->hasPesapalConfigured())
            <div class="alert alert-success" style="margin-bottom:var(--space-5);">
                Pesapal is active. Customers can pay by card, Airtel Money, or bank on your payment page.
            </div>
        @else
            <div class="alert alert-warning" style="margin-bottom:var(--space-5);">
                Pesapal is <strong>not configured</strong> yet. Add your credentials below to accept card payments.
            </div>
        @endif

        {{-- Credentials --}}
        <div class="settings-card">
            <div class="settings-card-header">
                <h2>Pesapal API Credentials</h2>
                <p>
                    Get credentials from
                    <a href="https://developer.pesapal.com" target="_blank" style="color:var(--color-primary);">developer.pesapal.com</a>.
                    Each business has independent credentials — payments go directly to that business's account.
                </p>
            </div>
            <div class="settings-card-body">
                <form method="POST" action="{{ route('settings.pesapal.update') }}">
                    @csrf
                    @method('PUT')

                    <div style="display:flex; gap:var(--space-4); margin-bottom:var(--space-5);">
                        @foreach(['sandbox' => ['label' => 'Sandbox', 'hint' => 'For testing — no real money'], 'production' => ['label' => 'Production', 'hint' => 'Live — real money']] as $env => $info)
                        <label style="flex:1; display:flex; align-items:center; gap:var(--space-2); padding:var(--space-3) var(--space-4); border:1.5px solid {{ $business->pesapal_environment === $env ? 'var(--color-primary)' : 'var(--color-border)' }}; border-radius:var(--radius-md); cursor:pointer;">
                            <input type="radio" name="pesapal_environment" value="{{ $env }}" {{ $business->pesapal_environment === $env ? 'checked' : '' }} style="accent-color:var(--color-primary);">
                            <div>
                                <div style="font-weight:600; font-size:0.9rem;">{{ $info['label'] }}</div>
                                <div style="font-size:0.75rem; color:var(--color-text-muted);">{{ $info['hint'] }}</div>
                            </div>
                        </label>
                        @endforeach
                    </div>

                    <div class="form-group">
                        <label class="form-label">Consumer Key *</label>
                        <input type="text" name="pesapal_consumer_key" class="form-control"
                            value="{{ old('pesapal_consumer_key', $business->pesapal_consumer_key) }}"
                            placeholder="Your Pesapal Consumer Key">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Consumer Secret *</label>
                        <input type="password" name="pesapal_consumer_secret" class="form-control"
                            value="{{ old('pesapal_consumer_secret', $business->pesapal_consumer_secret) }}"
                            placeholder="Your Pesapal Consumer Secret"
                            autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn--primary">Save Credentials</button>
                </form>
            </div>
        </div>

        {{-- IPN Registration --}}
        <div class="settings-card" style="margin-top:1.5rem;">
            <div class="settings-card-header">
                <h2>IPN Registration (Auto-Confirmation)</h2>
                <p>
                    Register your IPN URL with Pesapal so the system automatically marks sales as paid
                    the moment the customer completes payment — even if they close the browser.
                </p>
            </div>
            <div class="settings-card-body">
                @if(!$business->hasPesapalCredentials())
                    <div class="alert alert-warning">Save your credentials first before registering IPN.</div>
                @else
                    <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap; margin-bottom:14px;">
                        @if($business->pesapal_ipn_id)
                            <span class="alert alert-success" style="display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border-radius:20px; font-size:0.85rem; font-weight:600;">
                                IPN registered (ID: {{ $business->pesapal_ipn_id }})
                            </span>
                        @else
                            <span class="alert alert-warning" style="display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border-radius:20px; font-size:0.85rem; font-weight:600;">
                                IPN not registered yet
                            </span>
                        @endif
                        <form method="POST" action="{{ route('pesapal.register-ipn') }}" style="margin:0;">
                            @csrf
                            <button type="submit" class="btn btn--outline" style="font-size:0.9rem;">
                                {{ $business->pesapal_ipn_id ? 'Re-register IPN' : 'Register IPN with Pesapal' }}
                            </button>
                        </form>
                    </div>
                    <div style="background:var(--color-surface-2); border:1px solid var(--color-border); border-radius:8px; padding:14px; font-size:0.85rem; color:var(--color-text-muted);">
                        <strong>IPN URL that will be registered:</strong><br>
                        <code style="font-size:0.8rem; word-break:break-all;">{{ route('pesapal.ipn') }}</code>
                    </div>
                @endif
            </div>
        </div>

        {{-- How it works --}}
        <div class="settings-card" style="margin-top:1.5rem;">
            <div class="settings-card-header">
                <h2>How Card Payments Work</h2>
            </div>
            <div class="settings-card-body">
                <ol style="padding-left:1.2rem; color:var(--color-text-muted); font-size:0.9rem; line-height:2;">
                    <li>Cashier opens the payment page for a sale and clicks <strong>Pay by Card</strong></li>
                    <li>System creates a payment order and redirects to Pesapal's hosted checkout page</li>
                    <li>Customer pays with Visa, Mastercard, Airtel Money, or bank (whichever Pesapal enables for your account)</li>
                    <li>Pesapal sends an IPN notification → sale is marked <strong>paid</strong> automatically</li>
                    <li>Customer/cashier is redirected back to the invoice page with confirmation</li>
                </ol>
                <div class="alert alert--info" style="border-radius:8px; padding:12px 14px; margin-top:12px; font-size:0.85rem;">
                    Money goes directly to <strong>your</strong> Pesapal account — not through the platform.
                    Make sure your Pesapal account is fully verified and bank details are set.
                </div>
            </div>
        </div>

    </div>
</div>
</div>
@endsection
