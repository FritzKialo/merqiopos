@extends('layouts.app')
@section('title', 'Payroll Setup')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}?v={{ @filemtime(public_path('css/settings.css')) ?: '1' }}">
@endpush

@section('content')
<div class="page">

<div class="page-header">
    <div>
        <h1 class="page-title">Payroll Setup</h1>
        <p class="page-subtitle">Configure payroll for <strong>{{ $business->name }}</strong></p>
    </div>
</div>

<div class="settings-layout">
    @include('settings._nav')

    <div class="settings-content">

        @if(session('success'))
        <div class="alert alert-success" style="margin-bottom:var(--space-4);">
            {{ session('success') }}
        </div>
        @endif

        @if($errors->any())
        <div class="alert alert-danger" style="margin-bottom:var(--space-4);">
            <ul style="margin:0; padding-left:1.2em;">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
        @endif

        @php
            $ps              = $business->payroll_settings ?? [];
            $enabled         = $ps['enabled']           ?? false;
            $payCycle        = $ps['pay_cycle']         ?? 'monthly';
            $employerPin     = $ps['employer_pin']      ?? '';
            // Must mirror Business::isDeductionEnabled()'s real default (false):
            // these previously defaulted to true, so an unconfigured business saw
            // every deduction as "Enabled" while payroll withheld nothing.
            $deductions     = $ps['deductions']        ?? [];
            $deductPaye      = $deductions['paye']      ?? false;
            $deductNssf      = $deductions['nssf']      ?? false;
            $deductShif      = $deductions['shif']      ?? false;
            $deductHousing   = $deductions['housing_levy'] ?? false;
            $autoPayroll     = $ps['auto_payroll']      ?? false;
            $autoMode        = $ps['auto_payroll_mode'] ?? 'calculate_only';
            $payDay          = $ps['pay_day']           ?? 28;
        @endphp

        <form method="POST" action="{{ route('settings.payroll.update') }}">
            @csrf

            {{-- Section 1: Enable / Disable --}}
            <div class="settings-card" style="margin-bottom:var(--space-5);">
                <div class="settings-card-header">
                    <h2>Payroll Status</h2>
                    <p>Enable payroll processing for this store.</p>
                </div>
                <div class="settings-card-body">
                    <label class="toggle-label" style="display:flex; align-items:center; gap:var(--space-3); cursor:pointer;">
                        <input type="hidden" name="enabled" value="0">
                        <input type="checkbox" name="enabled" value="1" id="enabled"
                            {{ $enabled ? 'checked' : '' }}
                            style="width:20px; height:20px; accent-color:var(--color-primary); cursor:pointer;">
                        <span style="font-weight:600;">
                            Enable payroll for {{ $business->name }}
                        </span>
                    </label>
                    <p style="margin-top:var(--space-2); font-size:0.82rem; color:var(--color-text-muted);">
                        When enabled, you can create pay periods, calculate salaries, and generate payslips for staff assigned to this store.
                    </p>
                </div>
            </div>

            {{-- Section 2: Pay Cycle --}}
            <div class="settings-card" style="margin-bottom:var(--space-5);">
                <div class="settings-card-header">
                    <h2>Pay Cycle</h2>
                    <p>How often do you run payroll? This determines the default period dates when creating a new pay run.</p>
                </div>
                <div class="settings-card-body">
                    <div style="display:flex; flex-direction:column; gap:var(--space-3);">

                        @foreach([
                            ['value' => 'monthly',   'label' => 'Monthly',            'desc' => 'One pay run per month, e.g. 1st – 30th.'],
                            ['value' => 'bi_weekly', 'label' => 'Bi-Weekly',          'desc' => 'Every two weeks — 26 pay runs per year.'],
                            ['value' => 'weekly',    'label' => 'Weekly',             'desc' => 'Every week — 52 pay runs per year.'],
                        ] as $option)
                        {{-- Selected state used an undefined CSS variable
                        (--color-primary-subtle) that silently fell back to a
                        pale indigo tint — the one spot on this page that
                        didn't match the app's flat monochrome look. Now uses
                        a plain neutral surface + the real primary color. --}}
                        <label style="
                            display:flex; align-items:flex-start; gap:var(--space-3);
                            padding:var(--space-3) var(--space-4);
                            border:2px solid {{ $payCycle === $option['value'] ? 'var(--color-primary)' : 'var(--color-border)' }};
                            border-radius:var(--radius-md);
                            cursor:pointer;
                            background:{{ $payCycle === $option['value'] ? 'var(--color-surface-2)' : 'var(--color-surface)' }};"
                            onclick="this.querySelector('input').checked=true; document.querySelectorAll('[data-cycle-label]').forEach(el => { el.parentElement.style.borderColor='var(--color-border)'; el.parentElement.style.background='var(--color-surface)'; }); this.style.borderColor='var(--color-primary)'; this.style.background='var(--color-surface-2)';">
                            <input type="radio" name="pay_cycle" value="{{ $option['value'] }}"
                                {{ $payCycle === $option['value'] ? 'checked' : '' }}
                                data-cycle-label
                                style="margin-top:3px; accent-color:var(--color-primary);">
                            <div>
                                <div style="font-weight:600; font-size:0.9rem;">{{ $option['label'] }}</div>
                                <div style="font-size:0.8rem; color:var(--color-text-muted); margin-top:2px;">{{ $option['desc'] }}</div>
                            </div>
                        </label>
                        @endforeach

                    </div>
                </div>
            </div>

            {{-- Section 3: Statutory Deductions --}}
            <div class="settings-card" style="margin-bottom:var(--space-5);">
                <div class="settings-card-header">
                    <h2>Statutory Deductions</h2>
                    <p>
                        Select which deductions apply to this business. Not all businesses are registered for every deduction.
                        Individual employees can also override these settings in their staff profiles.
                    </p>
                </div>
                <div class="settings-card-body">

                    {{-- PAYE --}}
                    <div style="
                        display:flex; align-items:flex-start; justify-content:space-between; gap:var(--space-4);
                        padding:var(--space-4) 0; border-bottom:1px solid var(--color-border);">
                        <div>
                            <div style="font-weight:600;">PAYE — Pay As You Earn</div>
                            <div style="font-size:0.8rem; color:var(--color-text-muted); margin-top:3px; max-width:480px;">
                                KRA income tax withheld from employee salaries using the 2024/25 progressive bands
                                (10% → 35%). KSh 2,400/month personal relief applied automatically.
                                NSSF contributions are PAYE-exempt.
                            </div>
                        </div>
                        <label style="display:flex; align-items:center; gap:var(--space-2); cursor:pointer; white-space:nowrap; flex-shrink:0;">
                            <input type="hidden" name="deduct_paye" value="0">
                            <input type="checkbox" name="deduct_paye" value="1" id="deduct_paye"
                                {{ $deductPaye ? 'checked' : '' }}
                                style="width:18px; height:18px; accent-color:var(--color-primary); cursor:pointer;">
                            <span style="font-size:0.85rem; font-weight:500;">Enabled</span>
                        </label>
                    </div>

                    {{-- NSSF --}}
                    <div style="
                        display:flex; align-items:flex-start; justify-content:space-between; gap:var(--space-4);
                        padding:var(--space-4) 0; border-bottom:1px solid var(--color-border);">
                        <div>
                            <div style="font-weight:600;">NSSF — National Social Security Fund</div>
                            <div style="font-size:0.8rem; color:var(--color-text-muted); margin-top:3px; max-width:480px;">
                                NSSF Act 2013: 6% employee + 6% employer.
                                Tier I: 6% of first KSh 9,000 (max KSh 540 each side).
                                Tier II: 6% of next KSh 99,000 (max KSh 5,940 each side).
                                Total cap: KSh 6,480/month per side (from Feb 2026).
                            </div>
                        </div>
                        <label style="display:flex; align-items:center; gap:var(--space-2); cursor:pointer; white-space:nowrap; flex-shrink:0;">
                            <input type="hidden" name="deduct_nssf" value="0">
                            <input type="checkbox" name="deduct_nssf" value="1" id="deduct_nssf"
                                {{ $deductNssf ? 'checked' : '' }}
                                style="width:18px; height:18px; accent-color:var(--color-primary); cursor:pointer;">
                            <span style="font-size:0.85rem; font-weight:500;">Enabled</span>
                        </label>
                    </div>

                    {{-- SHIF --}}
                    <div style="
                        display:flex; align-items:flex-start; justify-content:space-between; gap:var(--space-4);
                        padding:var(--space-4) 0;">
                        <div>
                            <div style="font-weight:600;">SHIF — Social Health Insurance Fund</div>
                            <div style="font-size:0.8rem; color:var(--color-text-muted); margin-top:3px; max-width:480px;">
                                2.75% of gross pay — employee side and employer side each.
                                Replaced NHIF from October 2023 under the Social Health Insurance Act 2023. Employee-only: 2.75% of gross pay (minimum KSh 300).
                            </div>
                        </div>
                        <label style="display:flex; align-items:center; gap:var(--space-2); cursor:pointer; white-space:nowrap; flex-shrink:0;">
                            <input type="hidden" name="deduct_shif" value="0">
                            <input type="checkbox" name="deduct_shif" value="1" id="deduct_shif"
                                {{ $deductShif ? 'checked' : '' }}
                                style="width:18px; height:18px; accent-color:var(--color-primary); cursor:pointer;">
                            <span style="font-size:0.85rem; font-weight:500;">Enabled</span>
                        </label>
                    </div>

                    {{-- Affordable Housing Levy --}}
                    <div style="
                        display:flex; align-items:flex-start; justify-content:space-between; gap:var(--space-4);
                        padding:var(--space-4) 0; border-top:1px solid var(--color-border);">
                        <div>
                            <div style="font-weight:600;">Affordable Housing Levy</div>
                            <div style="font-size:0.8rem; color:var(--color-text-muted); margin-top:3px; max-width:480px;">
                                1.5% of gross pay from the employee plus 1.5% from the employer.
                                The employee's share is deducted before PAYE is calculated.
                            </div>
                        </div>
                        <label style="display:flex; align-items:center; gap:var(--space-2); cursor:pointer; white-space:nowrap; flex-shrink:0;">
                            <input type="hidden" name="deduct_housing_levy" value="0">
                            <input type="checkbox" name="deduct_housing_levy" value="1" id="deduct_housing_levy"
                                {{ $deductHousing ? 'checked' : '' }}
                                style="width:18px; height:18px; accent-color:var(--color-primary); cursor:pointer;">
                            <span style="font-size:0.85rem; font-weight:500;">Enabled</span>
                        </label>
                    </div>

                </div>
            </div>

            {{-- Section 4: Automatic Payroll --}}
            <div class="settings-card" style="margin-bottom:var(--space-5);" id="autoPayrollCard">
                <div class="settings-card-header">
                    <h2>Automatic Payroll</h2>
                    <p>
                        Let the system create and calculate your pay runs automatically each cycle.
                        You choose how much control to keep — you can always review before any money moves.
                    </p>
                </div>
                <div class="settings-card-body">

                    {{-- Enable toggle --}}
                    <label class="toggle-label" style="display:flex; align-items:center; gap:var(--space-3); cursor:pointer; margin-bottom:var(--space-4);">
                        <input type="hidden" name="auto_payroll" value="0">
                        <input type="checkbox" name="auto_payroll" value="1" id="autoPayrollToggle"
                            {{ $autoPayroll ? 'checked' : '' }}
                            style="width:20px; height:20px; accent-color:var(--color-primary); cursor:pointer;"
                            onchange="document.getElementById('autoPayrollOptions').style.display = this.checked ? '' : 'none';">
                        <span style="font-weight:600;">Enable automatic payroll for {{ $business->name }}</span>
                    </label>

                    <div id="autoPayrollOptions" style="{{ $autoPayroll ? '' : 'display:none;' }}">

                        {{-- Pay day --}}
                        <div class="form-group" style="max-width:260px; margin-bottom:var(--space-5);">
                            <label class="form-label" for="pay_day">Pay day (day of month)</label>
                            <input type="number" name="pay_day" id="pay_day"
                                class="form-control"
                                value="{{ old('pay_day', $payDay) }}"
                                min="1" max="28" style="width:100px;">
                            <p style="margin-top:var(--space-1); font-size:0.78rem; color:var(--color-text-muted);">
                                1–28. The system fires on this day each cycle. Maximum 28 to avoid month-length issues.
                            </p>
                        </div>

                        {{-- Mode --}}
                        <div style="display:flex; flex-direction:column; gap:var(--space-3);">
                            @foreach([
                                [
                                    'value' => 'calculate_only',
                                    'label' => 'Calculate only',
                                    'desc'  => 'System creates the period and calculates all figures. You review, approve, then choose who to pay.',
                                    'badge' => 'Recommended',
                                    'badgeClass' => 'badge-success',
                                ],
                                [
                                    'value' => 'auto_approve',
                                    'label' => 'Auto-approve',
                                    'desc'  => 'System creates, calculates, and approves. Period is ready to pay immediately — you just click Pay.',
                                    'badge' => null,
                                    'badgeClass' => null,
                                ],
                                [
                                    'value' => 'fully_auto',
                                    'label' => 'Fully automatic',
                                    'desc'  => 'System creates, calculates, approves, and pays all employees. An expense is posted automatically. No action needed.',
                                    'badge' => 'Use with care',
                                    'badgeClass' => 'badge-danger',
                                ],
                            ] as $opt)
                            <label style="
                                display:flex; align-items:flex-start; gap:var(--space-3);
                                padding:var(--space-3) var(--space-4);
                                border:2px solid {{ $autoMode === $opt['value'] ? 'var(--color-primary)' : 'var(--color-border)' }};
                                border-radius:var(--radius-md);
                                cursor:pointer;"
                                onclick="
                                    this.querySelector('input').checked=true;
                                    document.querySelectorAll('[data-mode-label]').forEach(el => {
                                        el.parentElement.style.borderColor='var(--color-border)';
                                    });
                                    this.style.borderColor='var(--color-primary)';">
                                <input type="radio" name="auto_payroll_mode" value="{{ $opt['value'] }}"
                                    {{ $autoMode === $opt['value'] ? 'checked' : '' }}
                                    data-mode-label
                                    style="margin-top:3px; accent-color:var(--color-primary);">
                                <div>
                                    <div style="font-weight:600; font-size:0.9rem; display:flex; align-items:center; gap:8px;">
                                        {{ $opt['label'] }}
                                        @if($opt['badge'])
                                        <span class="{{ $opt['badgeClass'] }}" style="font-size:0.7rem;">
                                            {{ $opt['badge'] }}
                                        </span>
                                        @endif
                                    </div>
                                    <div style="font-size:0.8rem; color:var(--color-text-muted); margin-top:2px;">{{ $opt['desc'] }}</div>
                                </div>
                            </label>
                            @endforeach
                        </div>

                        <p style="margin-top:var(--space-4); font-size:0.8rem; color:var(--color-text-muted); border-left:3px solid var(--color-border); padding-left:var(--space-3);">
                            Auto-payroll only runs if payroll is enabled and there are active staff with payroll profiles.
                            It will not create a period if one already exists for that date range.
                        </p>

                    </div>

                </div>
            </div>

            {{-- Section 5: Employer Details --}}
            <div class="settings-card" style="margin-bottom:var(--space-5);">
                <div class="settings-card-header">
                    <h2>Employer Details</h2>
                    <p>Used on payslips and statutory returns.</p>
                </div>
                <div class="settings-card-body">
                    <div class="form-group" style="max-width:360px;">
                        <label class="form-label" for="employer_pin">Employer KRA PIN</label>
                        <input type="text" name="employer_pin" id="employer_pin"
                            class="form-control"
                            value="{{ old('employer_pin', $employerPin) }}"
                            placeholder="A001234567T"
                            maxlength="20">
                        <p style="margin-top:var(--space-1); font-size:0.78rem; color:var(--color-text-muted);">
                            Your company's KRA tax PIN — printed on P9 certificates and remittance forms.
                        </p>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    Save Payroll Settings
                </button>
                <a href="{{ route('payroll.index') }}" class="btn btn-secondary">
                    Go to Payroll
                </a>
            </div>

        </form>

    </div>{{-- /.settings-content --}}
</div>{{-- /.settings-layout --}}

</div>
@endsection
