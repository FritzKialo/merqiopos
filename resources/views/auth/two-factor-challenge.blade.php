@extends('layouts.auth')
@section('title', $intent === 'sudo' ? 'Confirm Identity' : 'Two-Factor Authentication')

@section('content')
<div class="auth-card">

    <div class="auth-logo">
        <div class="auth-logo-icon">
            
        </div>
        <h1>Merqio<span>POS</span></h1>
        @if($intent === 'sudo')
            <p>Identity Verification Required</p>
        @else
            <p>Two-Factor Authentication</p>
        @endif
    </div>

    <h2 class="auth-title">
        @if($intent === 'sudo')
            Confirm Your Identity
        @else
            Verify Your Identity
        @endif
    </h2>
    <p class="auth-subtitle">
        @if($intent === 'sudo')
            You're accessing a sensitive area. Please confirm your identity with your authenticator app.
        @else
            Enter the 6-digit code from your authenticator app to continue.
        @endif
    </p>

    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    @if(session('info'))
        <div class="alert alert-info">
            {{ session('info') }}
        </div>
    @endif

    <form method="POST" action="{{ route('2fa.verify') }}" id="twoFactorForm">
        @csrf

        <input type="hidden" name="intent" value="{{ $intent }}">

        <div class="form-group" style="margin-bottom: var(--space-4);">
            <label class="form-label" for="code" id="codeLabel">
                Authenticator Code
            </label>
            <input
                type="text"
                id="code"
                name="code"
                class="form-control {{ $errors->has('code') ? 'is-invalid' : '' }}"
                placeholder="000000"
                inputmode="numeric"
                pattern="[0-9]{6}"
                maxlength="6"
                autocomplete="one-time-code"
                autofocus
                style="
                    text-align: center;
                    font-size: 1.75rem;
                    font-weight: 700;
                    letter-spacing: 0.5rem;
                    padding: var(--space-4);
                ">
            @error('code')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
            <p style="font-size: var(--text-xs); color: var(--auth-ink-soft); margin-top: var(--space-2); text-align: center;" id="codeHelp">
                Open your authenticator app (e.g. Google Authenticator) and enter the current 6-digit code.
            </p>
        </div>

        <div style="text-align: center; margin-bottom: var(--space-6);">
            <button type="button" id="toggleRecovery" style="background:none; border:none; color: var(--auth-accent); font-weight:600; cursor:pointer; font-size: var(--text-sm);">
                Use a recovery code instead
            </button>
        </div>

        <button type="submit" class="btn btn-primary">
            @if($intent === 'sudo') Confirm Identity @else Verify & Continue @endif
        </button>
    </form>

    @if($intent !== 'sudo')
        <div class="auth-footer" style="margin-top: var(--space-4);">
            <form method="POST" action="{{ route('logout') }}" style="display:inline;">
                @csrf
                <button type="submit" style="background:none; border:none; color: var(--auth-accent); font-weight:600; cursor:pointer; font-size: var(--text-sm);">
                    Sign in with a different account
                </button>
            </form>
        </div>
    @endif

</div>

@push('scripts')
<script>
var codeInput = document.getElementById('code');
var recoveryMode = false;

// Auto-submit when 6 digits entered (authenticator mode only).
codeInput.addEventListener('input', function () {
    if (!recoveryMode && this.value.replace(/\D/g, '').length === 6) {
        this.value = this.value.replace(/\D/g, '');
        document.getElementById('twoFactorForm').submit();
    }
});
// Only allow numeric input while in authenticator mode.
codeInput.addEventListener('keypress', function (e) {
    if (!recoveryMode && !/[0-9]/.test(e.key) && !['Backspace','Delete','Tab','Enter'].includes(e.key)) {
        e.preventDefault();
    }
});

document.getElementById('toggleRecovery').addEventListener('click', function () {
    recoveryMode = !recoveryMode;
    codeInput.value = '';

    if (recoveryMode) {
        document.getElementById('codeLabel').textContent = 'Recovery Code';
        document.getElementById('codeHelp').textContent = "Enter one of the recovery codes you saved when you set up two-factor authentication. Each code works once.";
        codeInput.placeholder = 'XXXXX-XXXXX';
        codeInput.removeAttribute('inputmode');
        codeInput.removeAttribute('pattern');
        codeInput.removeAttribute('maxlength');
        codeInput.style.letterSpacing = '0.15rem';
        codeInput.style.fontSize = '1.2rem';
        this.textContent = 'Use authenticator app instead';
    } else {
        document.getElementById('codeLabel').textContent = 'Authenticator Code';
        document.getElementById('codeHelp').textContent = 'Open your authenticator app (e.g. Google Authenticator) and enter the current 6-digit code.';
        codeInput.placeholder = '000000';
        codeInput.setAttribute('inputmode', 'numeric');
        codeInput.setAttribute('pattern', '[0-9]{6}');
        codeInput.setAttribute('maxlength', '6');
        codeInput.style.letterSpacing = '0.5rem';
        codeInput.style.fontSize = '1.75rem';
        this.textContent = 'Use a recovery code instead';
    }

    codeInput.focus();
});
</script>
@endpush
@endsection
