@extends('layouts.app')
@section('title', $user->name)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}?v={{ @filemtime(public_path('css/settings.css')) ?: '1' }}">
    <style>
    @media (max-width: 700px) {
        .staff-show-grid { grid-template-columns: 1fr !important; }
    }
    </style>
@endpush

@section('content')
<div class="page">

<div class="page-header">
    <div style="display:flex; align-items:center; gap:var(--space-4);">
        <div style="
            width:56px; height:56px; border-radius:50%;
            background:var(--color-surface-3);
            color:var(--color-text);
            display:flex; align-items:center; justify-content:center;
            font-weight:700; font-size:1.4rem; flex-shrink:0;">
            {{ strtoupper(substr($user->name, 0, 1)) }}
        </div>
        <div>
            <h1 class="page-title" style="margin:0;">{{ $user->name }}</h1>
            <p class="page-subtitle" style="margin:0;">
                {{ $profile?->job_title ?? $user->roleLabel() }}
                @if($profile?->department) · {{ $profile->department }} @endif
            </p>
        </div>
    </div>
    <div style="display:flex; gap:var(--space-2);">
        <a href="{{ route('staff.index') }}" class="btn btn-secondary">
             Back
        </a>
        @if(auth()->user()->canActAsOwner())
        <a href="{{ route('staff.profile', $user) }}" class="btn btn-primary">
             Edit Profile
        </a>
        @endif
    </div>
</div>

<div class="staff-show-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-5);">

    {{-- Identity & HR --}}
    <div class="settings-card">
        <div class="settings-card-header">
            <h2> Identity</h2>
        </div>
        <div class="settings-card-body" style="display:flex; flex-direction:column; gap:14px;">
            <div class="detail-row">
                <span class="detail-label">Email</span>
                <span>{{ $user->email }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">System Role</span>
                <span class="badge badge-{{ $user->role === 'manager' ? 'primary' : 'secondary' }}">
                    {{ $user->roleLabel() }}
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Status</span>
                <span class="badge badge-{{ $user->is_active ? 'success' : 'danger' }}">
                    {{ $user->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>
            @if($profile)
            <div class="detail-row">
                <span class="detail-label">National ID</span>
                <span>{{ $profile->id_number ?? '—' }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">KRA PIN</span>
                <span>{{ $profile->kra_pin ?? '—' }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">NSSF No.</span>
                <span>{{ $profile->nssf_no ?? '—' }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">SHIF No.</span>
                <span>{{ $profile->shif_no ?? '—' }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Employment Date</span>
                <span>{{ $profile->employment_date?->format('d M Y') ?? '—' }}</span>
            </div>
            @if($profile->termination_date)
            <div class="detail-row">
                <span class="detail-label">Termination Date</span>
                <span class="text-danger">{{ $profile->termination_date->format('d M Y') }}</span>
            </div>
            @endif
            @endif
        </div>
    </div>

    {{-- Pay Config --}}
    <div class="settings-card">
        <div class="settings-card-header">
            <h2> Compensation</h2>
        </div>
        @if($profile)
        <div class="settings-card-body" style="display:flex; flex-direction:column; gap:14px;">
            <div class="detail-row">
                <span class="detail-label">Pay Type</span>
                <span class="badge badge-secondary">
                    {{ \App\Models\StaffProfile::payTypes()[$profile->pay_type] ?? ucfirst($profile->pay_type) }}
                </span>
            </div>
            @if(in_array($profile->pay_type, ['retainer','hybrid']))
            <div class="detail-row">
                <span class="detail-label">Monthly Retainer</span>
                <strong>KSh {{ number_format($profile->retainer_amount, 2) }}</strong>
            </div>
            @endif
            @if(in_array($profile->pay_type, ['commission','hybrid']))
            <div class="detail-row">
                <span class="detail-label">Commission Rate</span>
                <strong>{{ $profile->commission_rate }}% of sales</strong>
            </div>
            @endif

            <div class="text-muted" style="border-top:1px solid var(--color-border); padding-top:14px; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:.04em;">
                Deductions
            </div>

            @php
                $overrides = $profile->deduction_overrides;
                $business  = $profile->business;
            @endphp

            @foreach(['paye' => 'PAYE', 'nssf' => 'NSSF', 'shif' => 'SHIF', 'housing_levy' => 'Housing Levy'] as $key => $label)
            <div class="detail-row">
                <span class="detail-label">{{ $label }}</span>
                @php $enabled = $profile->deductionEnabled($key); @endphp
                <div style="display:flex; align-items:center; gap:6px;">
                    <span class="badge badge-{{ $enabled ? 'success' : 'secondary' }}">
                        {{ $enabled ? 'Enabled' : 'Disabled' }}
                    </span>
                    @if($overrides && array_key_exists($key, $overrides))
                        <span style="font-size:10px;">override</span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="settings-card-body text-muted" style="text-align:center; padding:var(--space-6);">

            <p style="margin-top:var(--space-2);">No payroll profile configured yet.</p>
            @if(auth()->user()->canActAsOwner())
            <a href="{{ route('staff.profile', $user) }}" class="btn btn-primary" style="margin-top:var(--space-3);">
                 Set Up Profile
            </a>
            @endif
        </div>
        @endif
    </div>

</div>

{{-- P9 Certificate download — Growth+ only --}}
@if(auth()->user()->currentBusiness()?->organization?->hasFeature('p9_forms') && $profile)
@php
    $currentYear = (int) now()->format('Y');
    $startYear   = max(2020, (int) ($profile->employment_date?->format('Y') ?? $currentYear));
@endphp
<div class="settings-card" style="margin-top:var(--space-5);">
    <div class="settings-card-header">
        <h2> P9 Annual Tax Certificate</h2>
        <p>Download the KRA P9 deduction card for any completed tax year.</p>
    </div>
    <div class="settings-card-body">
        @if($startYear > $currentYear)
            <p class="text-muted" style="font-size:0.85rem;">No completed tax years yet for this employee.</p>
        @else
        <div style="display:flex; flex-wrap:wrap; gap:var(--space-2);">
            @for($y = $currentYear; $y >= $startYear; $y--)
            <a href="{{ route('payroll.p9', [$user, $y]) }}"
               class="btn btn-secondary btn-sm"
               title="Download P9 for {{ $y }}">
                 P9 — {{ $y }}
            </a>
            @endfor
        </div>
        <p class="text-muted" style="margin-top:var(--space-3); font-size:0.78rem;">
            Only months with paid payroll periods appear in the certificate.
            If a year has no paid periods, the certificate will have blank monthly rows.
        </p>
        @endif
    </div>
</div>
@endif

{{-- Recent payroll history --}}
@if($recentItems->isNotEmpty())
<div class="settings-card" style="margin-top:var(--space-5);">
    <div class="settings-card-header">
        <h2> Recent Payroll</h2>
        <a href="{{ route('payroll.index') }}" class="btn btn-secondary btn-sm">View All Periods</a>
    </div>
    <div class="settings-card-body" style="padding:0;">
        <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Period</th>
                    <th>Gross</th>
                    <th>Deductions</th>
                    <th>Net Pay</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentItems as $item)
                <tr>
                    <td data-label="Period" class="text-muted" style="font-size:0.82rem;">
                        {{ $item->period->period_start->format('d M') }}
                        – {{ $item->period->period_end->format('d M Y') }}
                    </td>
                    <td data-label="Gross">KSh {{ number_format($item->gross_pay, 0) }}</td>
                    <td data-label="Deductions" class="text-danger">
                        – KSh {{ number_format($item->total_deductions, 0) }}
                    </td>
                    <td data-label="Net Pay" style="font-weight:700;">KSh {{ number_format($item->net_pay, 0) }}</td>
                    <td data-label="Status">
                        <span class="badge badge-{{ $item->isPaid() ? 'success' : 'secondary' }}">
                            {{ $item->isPaid() ? 'Paid' : 'Pending' }}
                        </span>
                    </td>
                    <td data-label="Actions" style="text-align:right;">
                        <a href="{{ route('payroll.payslip', [$item->period, $item]) }}"
                           class="btn btn-secondary btn-sm">
                             Payslip
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
</div>
@endif

</div>
@endsection
