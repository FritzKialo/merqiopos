@extends('layouts.app')
@section('title', ($profile->exists ? 'Edit' : 'Set Up') . ' Profile — ' . $user->name)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}?v={{ @filemtime(public_path('css/settings.css')) ?: '1' }}">
@endpush

@section('content')
<div class="page">

<div class="page-header">
    <div>
        <h1 class="page-title">{{ $profile->exists ? 'Edit' : 'Set Up' }} Payroll Profile</h1>
        <p class="page-subtitle">Configure pay and statutory details for <strong>{{ $user->name }}</strong>.</p>
    </div>
    <a href="{{ route('staff.show', $user) }}" class="btn btn-secondary">
         Back
    </a>
</div>

@if($errors->any())
<div class="alert alert-danger" style="margin-bottom:var(--space-4);">
    <ul style="margin:0; padding-left:1.2em;">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route('staff.profile.update', $user) }}">
    @csrf

    {{-- ── Compensation ── --}}
    <div class="settings-card" style="max-width:720px;">
        <div class="settings-card-header">
            <h2> Compensation</h2>
            <p>How this employee is paid each period.</p>
        </div>
        <div class="settings-card-body">

            <div class="form-group">
                <label class="form-label">Pay Type *</label>
                <select name="pay_type" id="payTypeSelect" class="form-control" onchange="updatePayFields()">
                    @foreach($payTypes as $value => $label)
                        <option value="{{ $value }}" {{ old('pay_type', $profile->pay_type ?? 'retainer') === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-grid-2">
                <div class="form-group" id="retainerField">
                    <label class="form-label">Monthly Retainer (KSh)</label>
                    <input type="number" name="retainer_amount" class="form-control"
                        min="0" step="0.01"
                        value="{{ old('retainer_amount', $profile->retainer_amount ?? '') }}"
                        placeholder="e.g. 35000">
                    @error('retainer_amount')
                        <span class="invalid-feedback" style="display:block;">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group" id="commissionField">
                    <label class="form-label">Commission Rate (%)</label>
                    <input type="number" name="commission_rate" class="form-control"
                        min="0" max="100" step="0.01"
                        value="{{ old('commission_rate', $profile->commission_rate ?? '') }}"
                        placeholder="e.g. 5">
                    <span class="form-hint">Percentage of personal sales in the pay period.</span>
                    @error('commission_rate')
                        <span class="invalid-feedback" style="display:block;">{{ $message }}</span>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- ── HR Details ── --}}
    <div class="settings-card" style="max-width:720px; margin-top:var(--space-4);">
        <div class="settings-card-header">
            <h2> HR Details</h2>
            <p>Job information and employment dates.</p>
        </div>
        <div class="settings-card-body">
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">Job Title</label>
                    <input type="text" name="job_title" class="form-control"
                        value="{{ old('job_title', $profile->job_title) }}"
                        placeholder="e.g. Sales Associate">
                </div>
                <div class="form-group">
                    <label class="form-label">Department</label>
                    <input type="text" name="department" class="form-control"
                        value="{{ old('department', $profile->department) }}"
                        placeholder="e.g. Sales, Operations">
                </div>
                <div class="form-group">
                    <label class="form-label">Employment Date</label>
                    <input type="date" name="employment_date" class="form-control"
                        value="{{ old('employment_date', $profile->employment_date?->format('Y-m-d')) }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Termination Date</label>
                    <input type="date" name="termination_date" class="form-control"
                        value="{{ old('termination_date', $profile->termination_date?->format('Y-m-d')) }}">
                    <span class="form-hint">Leave blank if still employed.</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Statutory IDs ── --}}
    <div class="settings-card" style="max-width:720px; margin-top:var(--space-4);">
        <div class="settings-card-header">
            <h2> Statutory IDs</h2>
            <p>Required for PAYE, NSSF, SHIF compliance and P9 forms.</p>
        </div>
        <div class="settings-card-body">
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">National ID / Passport</label>
                    <input type="text" name="id_number" class="form-control"
                        value="{{ old('id_number', $profile->id_number) }}"
                        placeholder="e.g. 12345678">
                </div>
                <div class="form-group">
                    <label class="form-label">M-Pesa phone (for salary payments)</label>
                    <input type="text" name="mpesa_phone" class="form-control {{ $errors->has('mpesa_phone') ? 'is-invalid' : '' }}"
                        value="{{ old('mpesa_phone', $profile->mpesa_phone ?? '') }}"
                        placeholder="e.g. 0712345678">
                    <span class="form-hint">Needed only to pay this employee's salary by M-Pesa. Leave blank to pay another way.</span>
                    @error('mpesa_phone')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">KRA PIN</label>
                    <input type="text" name="kra_pin" class="form-control"
                        value="{{ old('kra_pin', $profile->kra_pin) }}"
                        placeholder="e.g. A001234567T">
                    <span class="form-hint">Required for PAYE and P9 annual form.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">NSSF Number</label>
                    <input type="text" name="nssf_no" class="form-control"
                        value="{{ old('nssf_no', $profile->nssf_no) }}"
                        placeholder="e.g. 1234567">
                </div>
                <div class="form-group">
                    <label class="form-label">SHIF Number</label>
                    <input type="text" name="shif_no" class="form-control"
                        value="{{ old('shif_no', $profile->shif_no) }}"
                        placeholder="e.g. 1234567">
                    <span class="form-hint">Social Health Insurance Fund (formerly NHIF).</span>
                </div>
                @if($business->enable_digital_float)
                <div class="form-group">
                    <label class="form-label">Cash Float Credit Limit (KSh)</label>
                    <input type="number" name="credit_limit" class="form-control"
                        value="{{ old('credit_limit', $profile->credit_limit) }}"
                        min="0" step="0.01"
                        placeholder="Business default: {{ number_format($business->default_credit_limit ?? 0, 2) }}">
                    <span class="form-hint">Leave blank to use the business default. Only applies if this person sells with the cashier role.</span>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Deduction Overrides ── --}}
    <div class="settings-card" style="max-width:720px; margin-top:var(--space-4);">
        <div class="settings-card-header">
            <h2> Deduction Overrides</h2>
            <p>
                By default this employee follows the store's payroll settings.
                Enable overrides only if this employee needs different deduction rules.
            </p>
        </div>
        <div class="settings-card-body">

            {{-- Business defaults summary --}}
            <div style="
                background:var(--color-surface-2);
                border:1px solid var(--color-border);
                border-radius:var(--radius-md);
                padding:var(--space-4);
                margin-bottom:var(--space-4);
                font-size:0.82rem;
                color:var(--color-text-muted);">
                <strong style="color:var(--color-text);">Store defaults:</strong>
                PAYE {{ $payrollEnabled && $business->isDeductionEnabled('paye') ? 'ON' : 'OFF' }} ·
                NSSF {{ $payrollEnabled && $business->isDeductionEnabled('nssf') ? 'ON' : 'OFF' }} ·
                SHIF {{ $payrollEnabled && $business->isDeductionEnabled('shif') ? 'ON' : 'OFF' }}
            </div>

            @php $overrides = $profile->deduction_overrides; @endphp

            <div class="form-group" style="margin-bottom:var(--space-4);">
                <label style="display:flex; align-items:center; gap:var(--space-2); cursor:pointer;">
                    <input type="checkbox" name="use_overrides" value="1" id="useOverridesCheck"
                        onchange="toggleOverrideFields()"
                        {{ old('use_overrides', $overrides !== null ? '1' : '') ? 'checked' : '' }}>
                    <span style="font-weight:600;">Use per-employee deduction overrides</span>
                </label>
            </div>

            <div id="overrideFields" style="{{ old('use_overrides', $overrides !== null ? '1' : '') ? '' : 'display:none;' }}">
                @foreach(['paye' => 'PAYE (Income Tax)', 'nssf' => 'NSSF Contributions', 'shif' => 'SHIF Contributions', 'housing_levy' => 'Affordable Housing Levy'] as $key => $label)
                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:var(--space-2); cursor:pointer;">
                        <input type="checkbox" name="override_{{ $key }}" value="1"
                            {{ old('override_' . $key, $overrides[$key] ?? ($payrollEnabled && $business->isDeductionEnabled($key)) ? '1' : '') ? 'checked' : '' }}>
                        <span>{{ $label }}</span>
                    </label>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="form-actions" style="max-width:720px; margin-top:var(--space-5);">
        <button type="submit" class="btn btn-primary">
             Save Profile
        </button>
        <a href="{{ route('staff.show', $user) }}" class="btn btn-secondary">Cancel</a>
    </div>
</form>

</div>
@endsection

@push('scripts')
<script>
function updatePayFields() {
    const type = document.getElementById('payTypeSelect').value;
    document.getElementById('retainerField').style.display =
        (type === 'retainer' || type === 'hybrid') ? '' : 'none';
    document.getElementById('commissionField').style.display =
        (type === 'commission' || type === 'hybrid') ? '' : 'none';
}

function toggleOverrideFields() {
    const show = document.getElementById('useOverridesCheck').checked;
    document.getElementById('overrideFields').style.display = show ? '' : 'none';
}

updatePayFields();
</script>
@endpush
