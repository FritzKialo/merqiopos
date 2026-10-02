@extends('admin.layouts.app')
@section('title', 'Security')

@push('styles')
<style>
.admin-security-hero {
    display: flex;
    align-items: flex-start;
    gap: 18px;
    padding: 22px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.admin-security-hero-avatar {
    flex-shrink: 0;
    width: 60px; height: 60px;
    border-radius: 50%;
    background: var(--admin-accent-grad);
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; font-weight: 700;
}
.admin-security-hero-info { flex: 1; min-width: 220px; }
.admin-security-hero-name { font-size: 20px; font-weight: 700; color: #0a0a0a; margin: 0 0 4px; }
.admin-security-hero-email { font-size: 13px; color: #666; margin: 0 0 10px; }
.admin-security-hero-badges { display: flex; gap: 8px; flex-wrap: wrap; }

.admin-password-toggle {
    position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer; color: #888; font-size: 16px;
    padding: 4px;
}
.admin-password-field { position: relative; }
.admin-password-hint { font-size: 11.5px; color: #888; margin-top: 5px; }
.admin-password-hint.ok { color: #15803d; }

.admin-other-admin-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 18px; border-bottom: 1px solid #f0f0f0; gap: 12px; flex-wrap: wrap;
}
.admin-other-admin-row:last-child { border-bottom: none; }
</style>
@endpush

@section('content')

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Security</h1>
        <p class="admin-page-subtitle">Manage your admin account password and two-factor authentication</p>
    </div>
</div>

{{-- ── Account hero ── --}}
<div class="admin-panel admin-security-hero">
    <div class="admin-security-hero-avatar">{{ strtoupper(substr(auth()->user()->name ?: '?', 0, 1)) }}</div>
    <div class="admin-security-hero-info">
        <h2 class="admin-security-hero-name">{{ auth()->user()->name }}</h2>
        <p class="admin-security-hero-email">{{ auth()->user()->email }}</p>
        <div class="admin-security-hero-badges">
            <span class="admin-badge admin-badge-indigo"><i class="ph-bold ph-shield-star"></i> Super Admin</span>
            @if(auth()->user()->hasTwoFactorEnabled())
                <span class="admin-badge admin-badge-green"><i class="ph-bold ph-shield-check"></i> 2FA Enabled</span>
            @else
                <span class="admin-badge admin-badge-red"><i class="ph-bold ph-warning"></i> 2FA Not Enabled</span>
            @endif
            @if(auth()->user()->last_login_at)
                <span class="admin-badge admin-badge-gray"><i class="ph-bold ph-clock-counter-clockwise"></i> Last login {{ auth()->user()->last_login_at->diffForHumans() }}</span>
            @endif
        </div>
    </div>
</div>

<div style="max-width:560px; display:flex; flex-direction:column; gap:20px;">

    {{-- Two-Factor Authentication --}}
    <div class="admin-panel">
        <div class="admin-panel-header">
            <h3 class="admin-panel-title">Two-Factor Authentication</h3>
        </div>
        <div style="padding:20px; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
            @if(auth()->user()->hasTwoFactorEnabled())
                <div>
                    <span class="admin-badge admin-badge-green">
                        <i class="ph-bold ph-shield-check"></i> Enabled — required at every login
                    </span>
                    @if(auth()->user()->two_factor_confirmed_at)
                    <div style="font-size:11.5px; color:#888; margin-top:6px;">
                        Enabled since {{ auth()->user()->two_factor_confirmed_at->format('d M Y') }}
                    </div>
                    @endif
                </div>
            @else
                <span class="admin-badge admin-badge-red">
                    <i class="ph-bold ph-warning"></i> Not enabled — required for admin accounts
                </span>
            @endif
            <a href="{{ route('settings.2fa.setup') }}" class="admin-btn admin-btn-gray admin-btn-sm">Manage 2FA</a>
        </div>
    </div>

    {{-- Change Password --}}
    <div class="admin-panel">
        <div class="admin-panel-header">
            <h3 class="admin-panel-title">Change Password</h3>
        </div>
        <form method="POST" action="{{ route('admin.password.update') }}" style="padding:20px;" id="adminPasswordForm">
            @csrf
            <div class="admin-form-group">
                <label class="admin-form-label">Current Password</label>
                <div class="admin-password-field">
                    <input type="password" name="current_password" class="admin-form-input @error('current_password') is-invalid @enderror" required>
                    <button type="button" class="admin-password-toggle" data-toggle-for="current_password"><i class="ph-bold ph-eye"></i></button>
                </div>
                @error('current_password')<div class="admin-form-error">{{ $message }}</div>@enderror
            </div>
            <div class="admin-form-group">
                <label class="admin-form-label">New Password</label>
                <div class="admin-password-field">
                    <input type="password" name="password" id="adminNewPassword" class="admin-form-input @error('password') is-invalid @enderror" required minlength="8">
                    <button type="button" class="admin-password-toggle" data-toggle-for="password"><i class="ph-bold ph-eye"></i></button>
                </div>
                @error('password')<div class="admin-form-error">{{ $message }}</div>@enderror
            </div>
            <div class="admin-form-group" style="margin-bottom:8px;">
                <label class="admin-form-label">Confirm New Password</label>
                <div class="admin-password-field">
                    <input type="password" name="password_confirmation" id="adminNewPasswordConfirm" class="admin-form-input" required minlength="8">
                    <button type="button" class="admin-password-toggle" data-toggle-for="password_confirmation"><i class="ph-bold ph-eye"></i></button>
                </div>
            </div>
            <div class="admin-password-hint" id="adminPasswordMatchHint" style="margin-bottom:20px;">&nbsp;</div>
            <button type="submit" class="admin-btn admin-btn-primary" style="width:100%; justify-content:center;">
                Update Password
            </button>
        </form>
    </div>

    {{-- Other Super Admins — "who else has the keys" visibility, previously
         nowhere on this page (or anywhere in the admin panel). --}}
    <div class="admin-panel">
        <div class="admin-panel-header">
            <h3 class="admin-panel-title">Other Super Admins ({{ $otherAdmins->count() }})</h3>
        </div>
        @if($otherAdmins->isEmpty())
            <p style="padding:20px; font-size:13px; color: var(--color-text-secondary); margin:0;">
                No other super admin accounts exist on the platform.
            </p>
        @else
            <div>
                @foreach($otherAdmins as $admin)
                <div class="admin-other-admin-row">
                    <div>
                        <div style="font-weight:600; font-size:13.5px;">{{ $admin->name }}</div>
                        <div style="font-size:12px; color: var(--color-text-secondary);">{{ $admin->email }}</div>
                    </div>
                    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                        @if($admin->two_factor_enabled && $admin->two_factor_confirmed_at)
                            <span class="admin-badge admin-badge-green"><i class="ph-bold ph-shield-check"></i> 2FA on</span>
                        @else
                            <span class="admin-badge admin-badge-red"><i class="ph-bold ph-warning"></i> 2FA off</span>
                        @endif
                        <span style="font-size:11.5px; color: var(--color-text-secondary);">
                            {{ $admin->last_login_at ? 'Login ' . $admin->last_login_at->diffForHumans() : 'Never logged in' }}
                        </span>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    document.querySelectorAll('.admin-password-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.querySelector('[name="' + btn.dataset.toggleFor + '"]');
            var isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            btn.innerHTML = isPassword ? '<i class="ph-bold ph-eye-slash"></i>' : '<i class="ph-bold ph-eye"></i>';
        });
    });

    var newPw = document.getElementById('adminNewPassword');
    var confirmPw = document.getElementById('adminNewPasswordConfirm');
    var hint = document.getElementById('adminPasswordMatchHint');

    function checkMatch() {
        if (! confirmPw.value) { hint.textContent = ' '; hint.className = 'admin-password-hint'; return; }
        if (newPw.value === confirmPw.value) {
            hint.textContent = 'Passwords match';
            hint.className = 'admin-password-hint ok';
        } else {
            hint.textContent = 'Passwords do not match yet';
            hint.className = 'admin-password-hint';
        }
    }
    newPw.addEventListener('input', checkMatch);
    confirmPw.addEventListener('input', checkMatch);
}());
</script>
@endpush
