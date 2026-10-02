@extends('layouts.app')
@section('title', 'Digital Float')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}?v={{ @filemtime(public_path('css/settings.css')) ?: '1' }}">
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Digital Float</h1>
            <p class="page-subtitle">Give each cashier a cash allowance and track what they owe until they deposit it</p>
        </div>
    </div>
<div class="settings-layout">
@include('settings._nav')
<div>

    @if(session('success')) <div class="alert alert--success">{{ session('success') }}</div> @endif
    @if($errors->any())
        <div class="alert alert--error">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
    @endif

    <div class="settings-card" style="max-width:600px;">
        <div class="settings-card-body">
            <form method="POST" action="{{ route('settings.cash-float.update') }}">
                @csrf

                <div class="form-group">
                    <label class="form-label" style="display:flex;align-items:center;gap:0.75rem;cursor:pointer;">
                        <input type="hidden" name="enable_digital_float" value="0">
                        <input type="checkbox" name="enable_digital_float" value="1"
                               {{ $business->enable_digital_float ? 'checked' : '' }}
                               id="floatToggle" onchange="toggleFloatFields()">
                        <span>Enable Digital Float</span>
                    </label>
                    <span class="form-hint">
                        When on, each cashier's cash sales count against a running allowance instead of going
                        untracked. Once it runs out they're blocked from taking more cash sales until a manager
                        records a deposit (cash they've handed over) or a refloat (a temporary top-up). M-Pesa and
                        bank transfer sales are never affected — only cash. Owners and managers are never blocked.
                    </span>
                </div>

                <div id="floatFields" style="{{ $business->enable_digital_float ? '' : 'display:none;' }}">
                    <div class="form-group">
                        <label class="form-label">Default Credit Limit (KSh)</label>
                        <input type="number" name="default_credit_limit" class="form-control"
                               value="{{ old('default_credit_limit', $business->default_credit_limit ?? 0) }}"
                               min="0" step="0.01">
                        <span class="form-hint">
                            Applies to every cashier unless you set a different limit for them individually
                            on their <a href="{{ route('settings.team') }}">Team Members</a> profile.
                        </span>
                    </div>
                </div>

                <button type="submit" class="btn btn--primary">Save Digital Float Settings</button>
            </form>
        </div>
    </div>

    @if($business->enable_digital_float)
    <p style="margin-top:1rem;font-size:0.85rem;color:var(--color-text-muted);max-width:600px;">
        To record a deposit or refloat for a cashier during an open shift, go to
        <a href="{{ route('cash-deposits.index') }}">Finance &gt; Cash Deposits</a>. Each cashier's running total
        also shows up automatically when a shift is closed under Sales &gt; Shifts.
    </p>
    @endif
</div>
</div>
</div>

<script>
function toggleFloatFields() {
    const show = document.getElementById('floatToggle').checked;
    document.getElementById('floatFields').style.display = show ? '' : 'none';
}
</script>
@endsection
