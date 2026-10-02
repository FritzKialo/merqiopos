@extends('layouts.app')
@section('title', 'Payslip — ' . $payrollItem->user->name)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}?v={{ @filemtime(public_path('css/settings.css')) ?: '1' }}">
    <style>
        @media print {
            .sidebar, .topbar, .mobile-header, .page-header .btn,
            .sidebar-backdrop { display: none !important; }
            .main-content { margin: 0 !important; }
            .payslip-card { box-shadow: none !important; border: 1px solid #ccc !important; }
        }
        @media (max-width: 640px) {
            .payslip-employee-grid { grid-template-columns: 1fr !important; }
        }
    </style>
@endpush

@section('content')
<div class="page">

<div class="page-header">
    <div>
        <h1 class="page-title">Payslip</h1>
        <p class="page-subtitle">
            {{ $payrollPeriod->period_start->format('d M Y') }}
            &ndash;
            {{ $payrollPeriod->period_end->format('d M Y') }}
        </p>
    </div>
    <div style="display:flex; gap:var(--space-2);">
        <a href="{{ route('payroll.show', $payrollPeriod) }}" class="btn btn-secondary">
             Back
        </a>
        <button onclick="window.print()" class="btn btn-primary">
             Print
        </button>
    </div>
</div>

<div class="payslip-card settings-card" style="max-width:680px; margin:0 auto;">

    {{-- Business header — flat solid (was a gradient that, since
    --color-primary-dark and --color-primary are both black in this app's
    token system, rendered as a pointless black-to-black "gradient"
    anyway) --}}
    <div style="
        background:var(--color-primary);
        padding:var(--space-6) var(--space-8);
        color:#fff;
        border-radius:var(--radius-lg) var(--radius-lg) 0 0;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:var(--space-3);">
            <div>
                <div style="font-size:1.2rem; font-weight:800; font-family:var(--font-heading);">
                    {{ $payrollPeriod->business->name }}
                </div>
                <div style="opacity:.8; font-size:0.82rem; margin-top:2px;">
                    {{ $payrollPeriod->business->address ?? '' }}
                    @if($payrollPeriod->business->city) · {{ $payrollPeriod->business->city }} @endif
                </div>
                @if($payrollPeriod->business->kra_pin)
                <div style="opacity:.7; font-size:0.75rem; margin-top:2px;">
                    KRA PIN: {{ $payrollPeriod->business->kra_pin }}
                </div>
                @endif
            </div>
            <div style="text-align:right;">
                <div style="font-size:0.7rem; font-weight:700; opacity:.7; text-transform:uppercase; letter-spacing:.06em;">
                    Payslip
                </div>
                <div style="font-size:1rem; font-weight:700;">
                    {{ $payrollPeriod->period_start->format('M Y') }}
                </div>
                <div style="font-size:0.75rem; opacity:.7; margin-top:2px;">
                    {{ $payrollPeriod->period_start->format('d M') }}
                    – {{ $payrollPeriod->period_end->format('d M Y') }}
                </div>
            </div>
        </div>
    </div>

    <div style="padding:var(--space-6) var(--space-8);">

        {{-- Employee details --}}
        <div class="payslip-employee-grid" style="
            display:grid; grid-template-columns:1fr 1fr; gap:var(--space-5);
            padding-bottom:var(--space-5); border-bottom:1px solid var(--color-border);
            margin-bottom:var(--space-5);">
            <div>
                <div style="font-size:0.7rem; font-weight:600; color:var(--color-text-muted); text-transform:uppercase; letter-spacing:.04em; margin-bottom:4px;">
                    Employee
                </div>
                <div style="font-weight:700; font-size:1rem;">{{ $payrollItem->user->name }}</div>
                <div style="color:var(--color-text-muted); font-size:0.82rem;">{{ $payrollItem->user->email }}</div>
                @php $profile = $payrollItem->user->staffProfileFor($payrollPeriod->business_id); @endphp
                @if($profile?->job_title)
                <div style="color:var(--color-text-muted); font-size:0.82rem;">{{ $profile->job_title }}</div>
                @endif
            </div>
            <div>
                @if($profile?->id_number)
                <div style="margin-bottom:4px;">
                    <span style="font-size:0.75rem; color:var(--color-text-muted);">National ID:</span>
                    <span style="font-size:0.82rem; font-weight:600; margin-left:4px;">{{ $profile->id_number }}</span>
                </div>
                @endif
                @if($profile?->kra_pin)
                <div style="margin-bottom:4px;">
                    <span style="font-size:0.75rem; color:var(--color-text-muted);">KRA PIN:</span>
                    <span style="font-size:0.82rem; font-weight:600; margin-left:4px;">{{ $profile->kra_pin }}</span>
                </div>
                @endif
                @if($profile?->nssf_no)
                <div style="margin-bottom:4px;">
                    <span style="font-size:0.75rem; color:var(--color-text-muted);">NSSF No:</span>
                    <span style="font-size:0.82rem; font-weight:600; margin-left:4px;">{{ $profile->nssf_no }}</span>
                </div>
                @endif
                @if($profile?->shif_no)
                <div>
                    <span style="font-size:0.75rem; color:var(--color-text-muted);">SHIF No:</span>
                    <span style="font-size:0.82rem; font-weight:600; margin-left:4px;">{{ $profile->shif_no }}</span>
                </div>
                @endif
            </div>
        </div>

        {{-- Earnings --}}
        <div style="margin-bottom:var(--space-5);">
            <div style="font-size:0.7rem; font-weight:700; color:var(--color-text-muted); text-transform:uppercase; letter-spacing:.05em; margin-bottom:var(--space-3);">
                Earnings
            </div>
            <table class="table-plain" style="width:100%; font-size:0.875rem; border-collapse:collapse;">
                @if(in_array($payrollItem->pay_type, ['retainer','hybrid']))
                <tr>
                    <td style="padding:6px 0; color:var(--color-text);">Basic Salary (Retainer)</td>
                    <td style="text-align:right; font-weight:600;">KSh {{ number_format($payrollItem->retainer_amount, 2) }}</td>
                </tr>
                @endif
                @if(in_array($payrollItem->pay_type, ['commission','hybrid']))
                <tr>
                    <td style="padding:6px 0; color:var(--color-text);">
                        Commission ({{ $payrollItem->commission_rate }}% × KSh {{ number_format($payrollItem->commission_sales, 0) }} sales)
                    </td>
                    <td style="text-align:right; font-weight:600;">
                        KSh {{ number_format($payrollItem->gross_pay - $payrollItem->retainer_amount, 2) }}
                    </td>
                </tr>
                @endif
                <tr style="border-top:1px solid var(--color-border); font-weight:700;">
                    <td style="padding:8px 0; color:var(--color-text);">Gross Pay</td>
                    <td style="text-align:right; font-size:1rem;">KSh {{ number_format($payrollItem->gross_pay, 2) }}</td>
                </tr>
            </table>
        </div>

        {{-- Deductions --}}
        <div style="margin-bottom:var(--space-5);">
            <div style="font-size:0.7rem; font-weight:700; color:var(--color-text-muted); text-transform:uppercase; letter-spacing:.05em; margin-bottom:var(--space-3);">
                Deductions (Employee)
            </div>
            <table class="table-plain" style="width:100%; font-size:0.875rem; border-collapse:collapse;">
                @php $nssfDet = $payrollItem->deduction_details['nssf_details'] ?? null; @endphp
                @if($payrollItem->nssf_employee > 0)
                <tr>
                    <td style="padding:6px 0; color:var(--color-text);">
                        NSSF — Tier I
                        @if($nssfDet) <span style="color:var(--color-text-muted); font-size:0.78rem;">(KSh {{ number_format($nssfDet['tier1_base'],0) }} × 6%)</span> @endif
                    </td>
                    <td style="text-align:right;">KSh {{ number_format($nssfDet['tier1_ee'] ?? 0, 2) }}</td>
                </tr>
                @if(($nssfDet['tier2_ee'] ?? 0) > 0)
                <tr>
                    <td style="padding:6px 0; color:var(--color-text);">
                        NSSF — Tier II
                        <span style="color:var(--color-text-muted); font-size:0.78rem;">(KSh {{ number_format($nssfDet['tier2_base'],0) }} × 6%)</span>
                    </td>
                    <td style="text-align:right;">KSh {{ number_format($nssfDet['tier2_ee'], 2) }}</td>
                </tr>
                @endif
                @endif
                @if($payrollItem->shif_employee > 0)
                <tr>
                    <td style="padding:6px 0; color:var(--color-text);">
                        SHIF (2.75% × KSh {{ number_format($payrollItem->gross_pay, 0) }})
                    </td>
                    <td style="text-align:right;">KSh {{ number_format($payrollItem->shif_employee, 2) }}</td>
                </tr>
                @endif
                @if(($payrollItem->housing_levy_employee ?? 0) > 0)
                <tr>
                    <td style="padding:6px 0; color:var(--color-text);">
                        Affordable Housing Levy (1.5%)
                    </td>
                    <td style="text-align:right;">KSh {{ number_format($payrollItem->housing_levy_employee, 2) }}</td>
                </tr>
                @endif
                @if($payrollItem->paye > 0)
                <tr>
                    <td style="padding:6px 0; color:var(--color-text);">
                        PAYE
                        @php $taxable = $payrollItem->deduction_details['taxable_income'] ?? 0; @endphp
                        <span style="color:var(--color-text-muted); font-size:0.78rem;">
                            (taxable KSh {{ number_format($taxable,0) }}, less KSh 2,400 relief)
                        </span>
                    </td>
                    <td style="text-align:right;">KSh {{ number_format($payrollItem->paye, 2) }}</td>
                </tr>
                @if($breakdown)
                @foreach($breakdown as $band)
                <tr style="font-size:0.75rem; color:var(--color-text-muted);">
                    <td style="padding:2px 0 2px 16px;">
                        KSh {{ number_format($band['from'],0) }} – {{ is_numeric($band['to']) ? 'KSh '.number_format($band['to'],0) : $band['to'] }}
                        @ {{ $band['rate'] }}
                    </td>
                    <td style="text-align:right;">KSh {{ number_format($band['tax'],2) }}</td>
                </tr>
                @endforeach
                @endif
                @endif
                <tr class="text-danger" style="border-top:1px solid var(--color-border); font-weight:700;">
                    <td style="padding:8px 0;">Total Deductions</td>
                    <td style="text-align:right;">KSh {{ number_format($payrollItem->total_deductions, 2) }}</td>
                </tr>
            </table>
        </div>

        {{-- Net Pay --}}
        <div style="
            background:var(--color-primary);
            border-radius:var(--radius-md);
            padding:var(--space-5) var(--space-6);
            display:flex; justify-content:space-between; align-items:center;
            color:#fff; margin-bottom:var(--space-5);">
            <div>
                <div style="font-size:0.78rem; opacity:.8;">Net Pay</div>
                <div style="font-size:0.82rem; opacity:.7; margin-top:2px;">
                    {{ $payrollPeriod->period_start->format('d M') }}
                    – {{ $payrollPeriod->period_end->format('d M Y') }}
                </div>
            </div>
            <div style="font-size:1.8rem; font-weight:800; font-family:var(--font-heading);">
                KSh {{ number_format($payrollItem->net_pay, 2) }}
            </div>
        </div>

        {{-- Employer contributions (informational) --}}
        @if($payrollItem->nssf_employer > 0 || $payrollItem->shif_employer > 0 || ($payrollItem->housing_levy_employer ?? 0) > 0)
        <div style="
            background:var(--color-surface-2);
            border:1px solid var(--color-border);
            border-radius:var(--radius-md);
            padding:var(--space-4); font-size:0.78rem;
            color:var(--color-text-muted); margin-bottom:var(--space-4);">
            <div style="font-weight:700; color:var(--color-text); margin-bottom:var(--space-2);">
                Employer Contributions (not deducted from employee)
            </div>
            @if($payrollItem->nssf_employer > 0)
            <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                <span>NSSF Employer</span>
                <span>KSh {{ number_format($payrollItem->nssf_employer, 2) }}</span>
            </div>
            @endif
            @if($payrollItem->shif_employer > 0)
            <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                <span>SHIF Employer</span>
                <span>KSh {{ number_format($payrollItem->shif_employer, 2) }}</span>
            </div>
            @endif
            @if(($payrollItem->housing_levy_employer ?? 0) > 0)
            <div style="display:flex; justify-content:space-between;">
                <span>Housing Levy Employer (1.5%)</span>
                <span>KSh {{ number_format($payrollItem->housing_levy_employer, 2) }}</span>
            </div>
            @endif
        </div>
        @endif

        {{-- Footer --}}
        <div style="text-align:center; font-size:0.72rem; color:var(--color-text-muted); border-top:1px solid var(--color-border); padding-top:var(--space-4);">
            Generated by Merqio POS on {{ now()->format('d M Y H:i') }} &middot;
            Status:
            <span class="badge badge-{{ $payrollItem->isPaid() ? 'success' : 'secondary' }}" style="font-size:0.65rem;">
                {{ $payrollItem->isPaid() ? 'Paid' : 'Pending' }}
            </span>
        </div>
    </div>
</div>

</div>
@endsection
