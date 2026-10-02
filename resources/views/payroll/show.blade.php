@extends('layouts.app')
@section('title', 'Payroll Period')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}?v={{ @filemtime(public_path('css/settings.css')) ?: '1' }}">
@endpush

@section('content')
<div class="page">

{{-- Header --}}
<div class="page-header">
    <div>
        <h1 class="page-title">
            {{ $payrollPeriod->period_start->format('d M') }}
            &ndash;
            {{ $payrollPeriod->period_end->format('d M Y') }}
        </h1>
        <p class="page-subtitle">
            {{ ucfirst(str_replace('_', '-', $payrollPeriod->pay_cycle)) }} pay run
            &mdash;
            <span class="badge badge-{{ $payrollPeriod->statusBadgeClass() }}">
                {{ $payrollPeriod->statusLabel() }}
            </span>
            @if($payrollPeriod->paid_at)
                &mdash; paid {{ $payrollPeriod->paid_at->format('d M Y') }}
            @endif
        </p>
    </div>
    <div style="display:flex; gap:var(--space-2); flex-wrap:wrap;">
        <a href="{{ route('payroll.index') }}" class="btn btn-secondary">
             Back
        </a>

        @if($payrollPeriod->isDraft())
            {{-- Calculate --}}
            <form method="POST" action="{{ route('payroll.calculate', $payrollPeriod) }}">
                @csrf
                <button type="submit" class="btn btn-primary">
                     Calculate
                </button>
            </form>

            @if($payrollPeriod->items()->count() > 0)
            {{-- Approve --}}
            <form method="POST" action="{{ route('payroll.approve', $payrollPeriod) }}">
                @csrf @method('PATCH')
                <button type="submit" class="btn btn-success"
                    onclick="return confirm('Approve this payroll period? Items can no longer be recalculated.')">
                     Approve
                </button>
            </form>

            {{-- Delete --}}
            <form method="POST" action="{{ route('payroll.destroy', $payrollPeriod) }}"
                  onsubmit="return confirm('Delete this draft period and all its items?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger">
                    Delete
                </button>
            </form>
            @endif

        @elseif($payrollPeriod->isApproved())
            @php $pendingCount = $payrollPeriod->items()->whereIn('status',['pending','failed'])->count(); @endphp
            @if($pendingCount > 0)
            {{-- Pay all remaining --}}
            <form method="POST" action="{{ route('payroll.mark-paid', $payrollPeriod) }}">
                @csrf @method('PATCH')
                <button type="submit" class="btn btn-success"
                    onclick="return confirm('Pay all {{ $pendingCount }} remaining employee(s) at once? This will post the net total as a business expense.')">
                    Pay All ({{ $pendingCount }})
                </button>
            </form>
            @endif
        @endif
    </div>
</div>

{{-- Summary cards --}}
@if($payrollPeriod->items()->count() > 0)
<div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:var(--space-4); margin-bottom:var(--space-6);">

    @php
        $totalHousingEe = (float) $payrollPeriod->items->sum('housing_levy_employee');
        $totalHousingEr = (float) $payrollPeriod->items->sum('housing_levy_employer');
        $cards = [
            ['label'=>'Total Gross',    'value'=>'KSh '.number_format($payrollPeriod->total_gross,0),  'icon'=>'ph-money',        'color'=>'var(--color-text)'],
            ['label'=>'NSSF (Employee)','value'=>'KSh '.number_format($payrollPeriod->total_nssf_ee,0),'icon'=>'ph-shield-check', 'color'=>'var(--color-warning)'],
            ['label'=>'SHIF (Employee)','value'=>'KSh '.number_format($payrollPeriod->total_shif_ee,0),'icon'=>'ph-first-aid',   'color'=>'var(--color-warning)'],
            ['label'=>'Housing Levy (Employee)','value'=>'KSh '.number_format($totalHousingEe,0),'icon'=>'ph-house', 'color'=>'var(--color-warning)'],
            ['label'=>'PAYE',           'value'=>'KSh '.number_format($payrollPeriod->total_paye,0),   'icon'=>'ph-bank',         'color'=>'var(--color-warning)'],
            ['label'=>'Net Payroll',    'value'=>'KSh '.number_format($payrollPeriod->total_net,0),    'icon'=>'ph-wallet',       'color'=>'var(--color-success)'],
            ['label'=>'Employer NSSF',  'value'=>'KSh '.number_format($payrollPeriod->total_nssf_er,0),'icon'=>'ph-buildings',   'color'=>'var(--color-text-muted)'],
            ['label'=>'Employer SHIF',  'value'=>'KSh '.number_format($payrollPeriod->total_shif_er,0),'icon'=>'ph-buildings',   'color'=>'var(--color-text-muted)'],
            ['label'=>'Employer Housing Levy','value'=>'KSh '.number_format($totalHousingEr,0),'icon'=>'ph-buildings', 'color'=>'var(--color-text-muted)'],
        ];
    @endphp

    @foreach($cards as $card)
    <div style="
        background:var(--color-surface);
        border:1px solid var(--color-border);
        border-radius:var(--radius-lg);
        padding:var(--space-4);">
        <div style="font-size:0.7rem; font-weight:600; color:var(--color-text-muted); text-transform:uppercase; letter-spacing:.04em; margin-bottom:4px;">
            {{ $card['label'] }}
        </div>
        <div style="font-size:1.1rem; font-weight:700; color:{{ $card['color'] }};">
            {{ $card['value'] }}
        </div>
    </div>
    @endforeach
</div>
@endif

{{-- Staff without profiles --}}
@php $missing = $staffProfiles->filter(fn($p) => !$payrollPeriod->items->firstWhere('user_id', $p->user_id))->count(); @endphp
@if($payrollPeriod->isDraft() && $staffProfiles->isEmpty())
<div class="alert alert-warning" style="margin-bottom:var(--space-4);">
    
    No staff have payroll profiles set up.
    <a href="{{ route('staff.index') }}" style="font-weight:600;">Set up staff profiles</a> before calculating.
</div>
@endif

{{-- Items table --}}
<div class="table-card">
    <div style="display:flex; align-items:center; justify-content:space-between; padding:var(--space-4) var(--space-5); border-bottom:1px solid var(--color-border);">
        <h3 style="margin:0; font-size:0.95rem; font-weight:600;">
            Pay Items ({{ $payrollPeriod->items->count() }})
        </h3>
        @if($payrollPeriod->isDraft() && $payrollPeriod->items->count() > 0)
        <form method="POST" action="{{ route('payroll.calculate', $payrollPeriod) }}">
            @csrf
            <button type="submit" class="btn btn-secondary btn-sm">
                 Recalculate
            </button>
        </form>
        @endif
    </div>

    @if($payrollPeriod->items->isEmpty())
    <div style="text-align:center; padding:var(--space-8); color:var(--color-text-muted);">
        
        No items yet. Click <strong>Calculate</strong> to run payroll for all active staff.
    </div>
    @else
    <div class="table-wrapper">
    <table class="table">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Pay Type</th>
                <th>Gross</th>
                <th>NSSF</th>
                <th>SHIF</th>
                <th>Housing Levy</th>
                <th>PAYE</th>
                <th style="font-weight:700;">Net Pay</th>
                <th>Status</th>
                <th style="text-align:right;"></th>
            </tr>
        </thead>
        <tbody>
            @foreach($payrollPeriod->items->sortBy('user.name') as $item)
            <tr>
                <td data-label="Employee">
                    <div style="font-weight:600;">{{ $item->user->name }}</div>
                    <div style="font-size:0.75rem; color:var(--color-text-muted);">
                        {{ $item->user->email }}
                    </div>
                </td>
                <td data-label="Pay Type">
                    <span class="badge badge-secondary" style="font-size:0.7rem;">
                        {{ ucfirst($item->pay_type) }}
                    </span>
                    @if($item->pay_type !== 'retainer')
                    <div style="font-size:0.72rem; color:var(--color-text-muted);">
                        Sales: KSh {{ number_format($item->commission_sales, 0) }}
                    </div>
                    @endif
                </td>
                <td data-label="Gross">KSh {{ number_format($item->gross_pay, 0) }}</td>
                <td class="text-warn" data-label="NSSF">
                    {{ $item->nssf_employee > 0 ? 'KSh '.number_format($item->nssf_employee,0) : '—' }}
                </td>
                <td class="text-warn" data-label="SHIF">
                    {{ $item->shif_employee > 0 ? 'KSh '.number_format($item->shif_employee,0) : '—' }}
                </td>
                <td class="text-warn" data-label="Housing Levy">
                    {{ ($item->housing_levy_employee ?? 0) > 0 ? 'KSh '.number_format($item->housing_levy_employee,0) : '—' }}
                </td>
                <td class="text-warn" data-label="PAYE">
                    {{ $item->paye > 0 ? 'KSh '.number_format($item->paye,0) : '—' }}
                </td>
                <td class="text-success" data-label="Net Pay" style="font-weight:700;">
                    KSh {{ number_format($item->net_pay, 0) }}
                </td>
                <td data-label="Status">
                    @if($item->isPaid())
                        <span class="badge badge-success">Paid</span>
                    @elseif($item->isPendingPayment())
                        <span class="badge badge-warning" title="M-Pesa payment sent — awaiting Safaricom confirmation">
                            Sending...
                        </span>
                    @elseif($item->isFailed())
                        <span class="badge badge-danger"
                              title="{{ $item->mpesa_result_desc ?? 'Payment failed' }}">
                            Failed
                        </span>
                    @else
                        <span class="badge badge-secondary">Pending</span>
                    @endif
                </td>
                <td data-label="Actions" style="text-align:right; white-space:nowrap;">
                    @if($payrollPeriod->isApproved() && !$item->isPaid() && !$item->isPendingPayment())
                    <form method="POST"
                          action="{{ route('payroll.item-pay', [$payrollPeriod, $item]) }}"
                          style="display:inline;"
                          onsubmit="return confirm('Pay {{ addslashes($item->user->name) }} KSh {{ number_format($item->net_pay, 0) }}?')">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn btn-{{ $item->isFailed() ? 'warning' : 'success' }} btn-sm" style="margin-right:4px;">
                            {{ $item->isFailed() ? 'Retry' : 'Pay' }}
                        </button>
                    </form>
                    @endif
                    <a href="{{ route('payroll.payslip', [$payrollPeriod, $item]) }}"
                       class="btn btn-secondary btn-sm" title="Payslip">
                        Payslip
                    </a>
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background:var(--color-surface-2); font-weight:700;">
                <td colspan="2" style="padding:var(--space-3) var(--space-4);">Totals</td>
                <td data-label="Gross">KSh {{ number_format($payrollPeriod->total_gross, 0) }}</td>
                <td class="text-warn" data-label="NSSF">KSh {{ number_format($payrollPeriod->total_nssf_ee, 0) }}</td>
                <td class="text-warn" data-label="SHIF">KSh {{ number_format($payrollPeriod->total_shif_ee, 0) }}</td>
                <td class="text-warn" data-label="Housing Levy">KSh {{ number_format($totalHousingEe, 0) }}</td>
                <td class="text-warn" data-label="PAYE">KSh {{ number_format($payrollPeriod->total_paye, 0) }}</td>
                <td class="text-success" data-label="Net Pay">KSh {{ number_format($payrollPeriod->total_net, 0) }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>
    </div>
    @endif
</div>

{{-- Employer cost summary --}}
@if($payrollPeriod->items->count() > 0)
<div style="
    background:var(--color-surface);
    border:1px solid var(--color-border);
    border-radius:var(--radius-lg);
    padding:var(--space-5);
    margin-top:var(--space-5);
    font-size:0.85rem;">
    <div style="font-weight:700; margin-bottom:var(--space-3);">
         Total Employer Cost
    </div>
    <div style="display:flex; gap:var(--space-6); flex-wrap:wrap;">
        <div>
            <span style="color:var(--color-text-muted);">Gross Pay</span>
            <strong style="display:block;">KSh {{ number_format($payrollPeriod->total_gross, 0) }}</strong>
        </div>
        <div>
            <span style="color:var(--color-text-muted);">Employer NSSF</span>
            <strong style="display:block;">KSh {{ number_format($payrollPeriod->total_nssf_er, 0) }}</strong>
        </div>
        <div>
            <span style="color:var(--color-text-muted);">Employer SHIF</span>
            <strong style="display:block;">KSh {{ number_format($payrollPeriod->total_shif_er, 0) }}</strong>
        </div>
        <div>
            <span style="color:var(--color-text-muted);">Employer Housing Levy</span>
            <strong style="display:block;">KSh {{ number_format($totalHousingEr, 0) }}</strong>
        </div>
        <div style="border-left:2px solid var(--color-border); padding-left:var(--space-6);">
            <span style="color:var(--color-text-muted);">Total Cost to Business (gross + employer contributions)</span>
            <strong style="display:block; font-size:1.1rem; color:var(--color-primary);">
                KSh {{ number_format($payrollPeriod->total_gross + $payrollPeriod->total_nssf_er + $payrollPeriod->total_shif_er + $totalHousingEr, 0) }}
            </strong>
        </div>
    </div>
</div>
@endif

@if($payrollPeriod->notes)
<div style="
    margin-top:var(--space-4);
    background:var(--color-surface);
    border:1px solid var(--color-border);
    border-radius:var(--radius-md);
    padding:var(--space-4);
    font-size:0.85rem;
    color:var(--color-text-muted);">
     {{ $payrollPeriod->notes }}
</div>
@endif

</div>
@endsection
