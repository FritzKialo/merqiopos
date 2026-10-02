@extends('layouts.app')
@section('title', 'Two-Factor Authentication')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}?v={{ @filemtime(public_path('css/settings.css')) ?: '1' }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/qrcode.min.js') }}"></script>
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

        @if($enabled)
        {{-- ── 2FA is ENABLED ── --}}

        @if($recoveryCodes)
        <div class="settings-card" style="margin-bottom: var(--space-5); border: 1.5px solid var(--color-warning, #b45309);">
            <div class="settings-card-header">
                <h2>Save your recovery codes now</h2>
                <p>Each code works once, in place of your authenticator app, if you ever lose access to it. This is the only time they're shown — write them down or save them in a password manager before leaving this page.</p>
            </div>
            <div class="settings-card-body">
                <div style="display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: var(--space-2) var(--space-5); background: var(--color-surface-2); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-4) var(--space-5); margin-bottom: var(--space-4); font-family: monospace; font-size: var(--text-sm);">
                    @foreach($recoveryCodes as $rc)
                        <div>{{ $rc }}</div>
                    @endforeach
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="copyRecoveryCodes">Copy all codes</button>
                </div>
            </div>
        </div>
        @push('scripts')
        <script>
            document.getElementById('copyRecoveryCodes')?.addEventListener('click', function () {
                const codes = @json($recoveryCodes).join('\n');
                navigator.clipboard.writeText(codes).then(() => {
                    this.textContent = 'Copied!';
                    setTimeout(() => { this.textContent = 'Copy all codes'; }, 1500);
                });
            });
        </script>
        @endpush
        @endif

        <div class="settings-card" style="margin-bottom: var(--space-5);">
            <div class="settings-card-header">
                <h2>Two-Factor Authentication</h2>
                <p>Your account is protected with an authenticator app.</p>
            </div>
            <div class="settings-card-body">
                <div class="alert alert-success" style="margin-bottom: var(--space-5);">
                    <div>
                        <div style="font-weight: 600; color: var(--color-text);">Two-factor authentication is active</div>
                        <div style="font-size: var(--text-sm); color: var(--color-text-muted);">
                            Enabled on {{ auth()->user()->two_factor_confirmed_at?->format('d M Y') }}.
                            Your account requires an authenticator code at login.
                        </div>
                    </div>
                </div>

                <p style="font-size: var(--text-sm); color: var(--color-text-muted); margin-bottom: var(--space-4);">
                    To disable two-factor authentication, enter your password and a current code from your authenticator app.
                    This will remove the extra login protection from your account.
                </p>

                <form method="POST" action="{{ route('settings.2fa.disable') }}" id="disableForm">
                    @csrf

                    <div class="form-grid-2" style="margin-bottom: var(--space-4);">
                        <div class="form-group">
                            <label class="form-label" for="password">Current Password</label>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-control {{ $errors->has('password') ? 'is-invalid' : '' }}"
                                placeholder="Your account password"
                                required>
                            @error('password')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="code">Authenticator Code</label>
                            <input
                                type="text"
                                id="code"
                                name="code"
                                class="form-control {{ $errors->has('code') ? 'is-invalid' : '' }}"
                                placeholder="000000"
                                inputmode="numeric"
                                maxlength="6"
                                autocomplete="one-time-code"
                                required>
                            @error('code')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-danger"
                            onclick="return confirm('Are you sure you want to disable two-factor authentication? Your account will be less secure.')">
                            Disable Two-Factor Authentication
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="settings-card" style="margin-bottom: var(--space-5);">
            <div class="settings-card-header">
                <h2>Recovery Codes</h2>
                <p>Use one of these if you ever lose access to your authenticator app — each works once.</p>
            </div>
            <div class="settings-card-body">
                <p style="font-size: var(--text-sm); color: var(--color-text-muted); margin-bottom: var(--space-4);">
                    @if($recoveryCodesRemaining > 0)
                        <strong style="color: var(--color-text);">{{ $recoveryCodesRemaining }}</strong> of 8 recovery codes remaining.
                    @else
                        <strong style="color: var(--color-danger, #b91c1c);">No recovery codes left.</strong> Generate a new set below so you're not locked out if you lose your authenticator.
                    @endif
                    Regenerating replaces your current codes — the old ones stop working immediately.
                </p>

                <form method="POST" action="{{ route('settings.2fa.recovery-codes.regenerate') }}">
                    @csrf
                    <div class="form-grid-2" style="margin-bottom: var(--space-4);">
                        <div class="form-group">
                            <label class="form-label" for="rc-password">Current Password</label>
                            <input
                                type="password"
                                id="rc-password"
                                name="password"
                                class="form-control"
                                placeholder="Your account password"
                                required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="rc-code">Authenticator Code</label>
                            <input
                                type="text"
                                id="rc-code"
                                name="code"
                                class="form-control"
                                placeholder="000000"
                                inputmode="numeric"
                                maxlength="6"
                                autocomplete="one-time-code"
                                required>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-secondary"
                            onclick="return confirm('Generate new recovery codes? Your current codes will stop working immediately.')">
                            Regenerate Recovery Codes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @else
        {{-- ── 2FA is DISABLED ── --}}
        <div class="settings-card" style="margin-bottom: var(--space-5);">
            <div class="settings-card-header">
                <h2>Two-Factor Authentication</h2>
                <p>Add an extra layer of security to your account.</p>
            </div>
            <div class="settings-card-body">

                <div class="alert alert-warning" style="margin-bottom: var(--space-5);">
                    <div>
                        <div style="font-weight: 600; color: var(--color-text);">Two-factor authentication is not enabled</div>
                        <div style="font-size: var(--text-sm); color: var(--color-text-muted);">
                            We strongly recommend enabling 2FA to protect your business account and sensitive settings.
                        </div>
                    </div>
                </div>

                <div class="settings-grid-2" style="display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: var(--space-5); margin-bottom: var(--space-6);">
                    <div>
                        <h3 style="font-size: var(--text-sm); font-weight: 600; color: var(--color-text); margin-bottom: var(--space-3);">
                            Setup Instructions
                        </h3>
                        <ol style="font-size: var(--text-sm); color: var(--color-text-muted); padding-left: var(--space-5); line-height: 1.8;">
                            <li>Install an authenticator app on your phone (Google Authenticator, Authy, or Microsoft Authenticator)</li>
                            <li>Scan the QR code on the right</li>
                            <li>Enter the 6-digit code shown in the app to confirm</li>
                        </ol>
                    </div>
                    <div style="display: flex; flex-direction: column; align-items: center; gap: var(--space-3);">
                        @if($qrCodeUrl)
                            <div style="
                                background: #fff;
                                padding: var(--space-3);
                                border: 1px solid var(--color-border);
                                border-radius: var(--radius-md);
                                display: inline-flex;
                                align-items: center;
                                justify-content: center;">
                                <canvas id="2fa-qr-canvas"></canvas>
                            </div>
                            <div style="text-align: center;">
                                <p style="font-size: var(--text-xs); color: var(--color-text-muted); margin-bottom: var(--space-2);">
                                    Can't scan? Enter this key manually:
                                </p>
                                <code style="
                                    background: var(--color-surface-2);
                                    padding: var(--space-2) var(--space-3);
                                    border-radius: var(--radius-sm);
                                    font-size: var(--text-sm);
                                    letter-spacing: 0.1rem;
                                    word-break: break-all;">
                                    {{ $secret }}
                                </code>
                            </div>
                            @push('scripts')
                            <script>
                                QRCode.toCanvas(
                                    document.getElementById('2fa-qr-canvas'),
                                    @json($qrCodeUrl),
                                    { width: 200, margin: 2, color: { dark: '#000000', light: '#ffffff' } },
                                    function (err) { if (err) console.error('QR error:', err); }
                                );
                            </script>
                            @endpush
                        @endif
                    </div>
                </div>

                <form method="POST" action="{{ route('settings.2fa.confirm') }}" id="confirmForm">
                    @csrf

                    <div class="form-group" style="max-width: 280px;">
                        <label class="form-label" for="code">
                            Verification Code
                        </label>
                        <input
                            type="text"
                            id="code"
                            name="code"
                            class="form-control {{ $errors->has('code') ? 'is-invalid' : '' }}"
                            placeholder="Enter the 6-digit code"
                            inputmode="numeric"
                            maxlength="6"
                            autocomplete="one-time-code"
                            autofocus>
                        @error('code')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            Enable Two-Factor Authentication
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endif

    </div>
</div>
</div>
@endsection
