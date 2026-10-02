@extends('layouts.app')
@section('title', 'M-Pesa Settings')

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
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif

        {{-- Status banner --}}
        @if($business->hasMpesaConfigured())
            <div class="alert alert-success" style="margin-bottom: var(--space-5); display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
                <span>
                    M-Pesa is configured.
                    Customer sale payments will go directly to your
                    <strong>{{ $business->mpesa_environment === 'production' ? 'live' : 'sandbox' }}</strong>
                    shortcode <strong>{{ $business->mpesa_shortcode }}</strong>.
                </span>
                <form method="POST" action="{{ route('settings.mpesa.disconnect') }}" style="margin:0;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger" style="font-size:0.85rem; white-space:nowrap;"
                        onclick="return confirm('Disconnect M-Pesa? Customers will no longer see M-Pesa or QR payment options until you reconnect it. This cannot be undone — you\'ll need to re-enter your Daraja credentials to turn it back on.')">
                        Disconnect M-Pesa
                    </button>
                </form>
            </div>
        @else
            <div class="alert alert-warning" style="margin-bottom: var(--space-5);">
                M-Pesa is <strong>not configured</strong> yet.
                Your customers cannot pay via M-Pesa until you add your Daraja credentials below.
            </div>
        @endif

        {{-- Credentials form --}}
        <div class="settings-card">
            <div class="settings-card-header">
                <h2>Daraja API Credentials</h2>
                <p>Get credentials from <a href="https://developer.safaricom.co.ke" target="_blank" style="color:var(--color-primary);">developer.safaricom.co.ke</a>. Use Sandbox for testing, Production when live.</p>
            </div>
            <div class="settings-card-body">
                <form method="POST" action="{{ route('settings.mpesa.update') }}">
                    @csrf
                    @method('PUT')

                    {{-- Environment --}}
                    <div class="form-group" style="margin-bottom: var(--space-5);">
                        <label class="form-label">Environment</label>
                        <div style="display: flex; gap: var(--space-3);">
                            <label style="
                                display: flex;
                                align-items: center;
                                gap: var(--space-2);
                                padding: var(--space-3) var(--space-4);
                                border: 1.5px solid var(--color-border);
                                border-radius: var(--radius-md);
                                cursor: pointer;
                                flex: 1;
                                transition: var(--transition);"
                                id="env-sandbox-label">
                                <input type="radio" name="mpesa_environment" value="sandbox"
                                    {{ old('mpesa_environment', $business->mpesa_environment ?? 'sandbox') === 'sandbox' ? 'checked' : '' }}
                                    onchange="updateEnvLabels()">
                                <div>
                                    <div style="font-weight: 600; font-size: var(--text-sm); color: var(--color-text);">Sandbox</div>
                                    <div style="font-size: var(--text-xs); color: var(--color-text-muted);">For testing — no real money</div>
                                </div>
                            </label>
                            <label style="
                                display: flex;
                                align-items: center;
                                gap: var(--space-2);
                                padding: var(--space-3) var(--space-4);
                                border: 1.5px solid var(--color-border);
                                border-radius: var(--radius-md);
                                cursor: pointer;
                                flex: 1;
                                transition: var(--transition);"
                                id="env-production-label">
                                <input type="radio" name="mpesa_environment" value="production"
                                    {{ old('mpesa_environment', $business->mpesa_environment ?? 'sandbox') === 'production' ? 'checked' : '' }}
                                    onchange="updateEnvLabels()">
                                <div>
                                    <div style="font-weight: 600; font-size: var(--text-sm); color: var(--color-text);">Production</div>
                                    <div style="font-size: var(--text-xs); color: var(--color-text-muted);">Live — real money moves</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- Manual M-Pesa code confirmation policy --}}
                    <div class="form-group" style="margin-bottom: var(--space-5);">
                        <label style="display:flex;align-items:flex-start;gap:0.6rem;cursor:pointer;">
                            <input type="hidden" name="allow_unverified_mpesa_codes" value="0">
                            <input type="checkbox" name="allow_unverified_mpesa_codes" value="1"
                                   {{ ($business->allow_unverified_mpesa_codes ?? true) ? 'checked' : '' }}
                                   style="width:18px;height:18px;margin-top:2px;">
                            <span>
                                <strong>Let cashiers confirm M-Pesa codes the system can't verify</strong><br>
                                <span style="color:var(--color-text-muted);font-size:0.85rem;">
                                    When a code is typed in by hand, it is checked against payments this system received
                                    (STK push, QR, paybill). If it isn't found it can't be verified. Untick this so only owners
                                    and managers can confirm such payments. Unverified payments are always flagged on the sale.
                                </span>
                            </span>
                        </label>
                    </div>

                    {{-- Transaction Status (verify typed-in codes) --}}
                    <div class="form-group" style="margin-bottom: var(--space-5); padding: var(--space-4); border: 1px solid var(--color-border); border-radius: var(--radius-md);">
                        <div style="font-weight:600; margin-bottom:4px;">Verify typed-in M-Pesa codes with Safaricom</div>
                        <p style="color:var(--color-text-muted);font-size:0.85rem;margin-bottom:var(--space-3);">
                            Optional. With a Daraja <em>initiator</em> set up, a code typed in by hand can be confirmed directly with Safaricom.
                            Enter the initiator name and its <strong>security credential</strong> (the initiator password already encrypted with
                            Safaricom's certificate &mdash; the Daraja portal generates it). It is stored encrypted.
                        </p>
                        <label class="form-label" for="mpesa_initiator_name">Initiator name</label>
                        <input type="text" id="mpesa_initiator_name" name="mpesa_initiator_name" class="form-control" maxlength="100"
                               value="{{ old('mpesa_initiator_name', $business->mpesa_initiator_name ?? '') }}" placeholder="e.g. testapi">
                        <label class="form-label" for="mpesa_security_credential" style="margin-top:var(--space-3);">Security credential</label>
                        <input type="password" id="mpesa_security_credential" name="mpesa_security_credential" class="form-control" autocomplete="off"
                               placeholder="{{ !empty($business->mpesa_security_credential ?? null) ? '(saved — enter a new one to replace it)' : 'Paste the encrypted credential' }}">
                    </div>

                    {{-- Shortcode --}}
                    <div class="form-group">
                        <label class="form-label" for="mpesa_shortcode">
                            Business Short Code
                        </label>
                        <input
                            type="text"
                            id="mpesa_shortcode"
                            name="mpesa_shortcode"
                            class="form-control {{ $errors->has('mpesa_shortcode') ? 'is-invalid' : '' }}"
                            value="{{ old('mpesa_shortcode', $business->mpesa_shortcode) }}"
                            placeholder="e.g. 174379 (Paybill) or 3140209 (Till)">
                        @error('mpesa_shortcode')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                        <span class="form-hint">Enter your Paybill number or Till number — whichever customers will pay to.</span>
                    </div>

                    <div class="form-grid-2">
                        {{-- Consumer Key --}}
                        <div class="form-group">
                            <label class="form-label" for="mpesa_consumer_key">Consumer Key</label>
                            <input
                                type="password"
                                id="mpesa_consumer_key"
                                name="mpesa_consumer_key"
                                class="form-control {{ $errors->has('mpesa_consumer_key') ? 'is-invalid' : '' }}"
                                value="{{ old('mpesa_consumer_key') }}"
                                placeholder="{{ $business->mpesa_consumer_key ? '••••••••••••  (saved — leave blank to keep)' : 'From Daraja portal' }}"
                                autocomplete="new-password">
                            @error('mpesa_consumer_key')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Consumer Secret --}}
                        <div class="form-group">
                            <label class="form-label" for="mpesa_consumer_secret">Consumer Secret</label>
                            <input
                                type="password"
                                id="mpesa_consumer_secret"
                                name="mpesa_consumer_secret"
                                class="form-control {{ $errors->has('mpesa_consumer_secret') ? 'is-invalid' : '' }}"
                                value="{{ old('mpesa_consumer_secret') }}"
                                placeholder="{{ $business->mpesa_consumer_secret ? '••••••••••••  (saved — leave blank to keep)' : 'From Daraja portal' }}"
                                autocomplete="new-password">
                            @error('mpesa_consumer_secret')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    {{-- Passkey --}}
                    <div class="form-group">
                        <label class="form-label" for="mpesa_passkey">Lipa Na M-Pesa Passkey</label>
                        <input
                            type="password"
                            id="mpesa_passkey"
                            name="mpesa_passkey"
                            class="form-control {{ $errors->has('mpesa_passkey') ? 'is-invalid' : '' }}"
                            value="{{ old('mpesa_passkey') }}"
                            placeholder="{{ $business->mpesa_passkey ? '••••••••••••  (saved — leave blank to keep)' : 'From Daraja portal under your app' }}"
                            autocomplete="new-password">
                        @error('mpesa_passkey')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            Save M-Pesa Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- C2B URL Registration --}}
        <div class="settings-card" style="margin-top:1.5rem;">
            <div class="settings-card-header">
                <h2>Paybill / Till Auto-Detection (C2B)</h2>
                <p>
                    Register your Paybill/Till with Safaricom so the system automatically detects when a customer pays directly — without needing an STK Push.
                    Customers must enter the <strong>invoice number</strong> as the account reference (e.g. <code>INV-00017</code>).
                </p>
            </div>
            <div class="settings-card-body">
                @if(!$business->hasMpesaConfigured())
                    <div class="alert alert-warning">Save your Daraja credentials above before registering C2B URLs.</div>
                @else
                    <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
                        <div>
                            @if($business->mpesa_c2b_registered)
                                <span class="alert alert-success" style="display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border-radius:20px; font-size:0.85rem; font-weight:600;">
                                    C2B URLs registered
                                </span>
                            @else
                                <span class="alert alert-warning" style="display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border-radius:20px; font-size:0.85rem; font-weight:600;">
                                    Not registered yet
                                </span>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('settings.mpesa.register-c2b') }}" style="margin:0;">
                            @csrf
                            <button type="submit" class="btn btn--outline" style="font-size:0.9rem;">
                                {{ $business->mpesa_c2b_registered ? 'Re-register URLs' : 'Register C2B URLs with Safaricom' }}
                            </button>
                        </form>
                    </div>
                    <div style="margin-top:14px; background:var(--color-surface-2); border:1px solid var(--color-border); border-radius:8px; padding:14px; font-size:0.85rem; color:var(--color-text-muted);">
                        <strong>How it works:</strong><br>
                        After registering, when a customer dials *147# or uses the M-Pesa app and pays to your shortcode <strong>{{ $business->mpesa_shortcode }}</strong>,
                        Safaricom notifies this system instantly. If the customer entered a valid invoice number as the reference,
                        the sale is marked paid automatically and any open payment page redirects in real time.
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
</div>{{-- end .page --}}
@endsection

@push('scripts')
<script>
function updateEnvLabels() {
    const sandbox    = document.querySelector('input[value="sandbox"]');
    const production = document.querySelector('input[value="production"]');
    const sandboxLabel    = document.getElementById('env-sandbox-label');
    const productionLabel = document.getElementById('env-production-label');

    sandboxLabel.style.borderColor    = sandbox.checked
        ? 'var(--color-primary)' : 'var(--color-border)';
    productionLabel.style.borderColor = production.checked
        ? 'var(--color-success)' : 'var(--color-border)';
    sandboxLabel.style.background    = sandbox.checked
        ? 'var(--color-primary-light)' : '';
    productionLabel.style.background = production.checked
        ? 'var(--color-success-light)' : '';
}
// Run on page load to highlight the saved selection
document.addEventListener('DOMContentLoaded', updateEnvLabels);
</script>
@endpush
