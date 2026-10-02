@extends('layouts.app')
@section('title', 'Payroll')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}?v={{ @filemtime(public_path('css/settings.css')) ?: '1' }}">
@endpush

@section('content')
<div class="page">

<div class="page-header">
    <div>
        <h1 class="page-title">Payroll</h1>
        <p class="page-subtitle">
            Manage pay periods for <strong>{{ $business->name }}</strong>
            @if($pendingDraft > 0)
                &mdash; <span style="color:var(--color-warning);">{{ $pendingDraft }} draft period(s) awaiting approval</span>
            @endif
        </p>
    </div>
    <a href="{{ route('payroll.create') }}" class="btn btn-primary">
         New Period
    </a>
</div>

{{-- Quick links --}}
<div style="display:flex; gap:var(--space-3); margin-bottom:var(--space-5); flex-wrap:wrap;">
    <a href="{{ route('staff.index') }}" class="btn btn-secondary btn-sm">
         Staff Profiles
    </a>
    <a href="{{ route('settings.payroll') }}" class="btn btn-secondary btn-sm">
         Payroll Settings
    </a>
    {{-- P9 certificates and the 4 statutory remittance reports all had
    working routes and controllers but no link anywhere in the app pointing
    to any of them — reachable only by typing the exact URL. --}}
    {{-- p9_forms/payroll_statutory are org-level features (config/plans.php
    'org' => [...]) — they don't exist as keys anywhere in the store-level
    plan configs Business::hasFeature() actually reads, so $business->
    hasFeature() was silently always false here regardless of plan, hiding
    both buttons for every business including paying Growth+ orgs. Check
    the organization first, same resolution order CheckPlanFeature (the
    route middleware that actually gates these pages) already uses. --}}
    @if($business->organization?->hasFeature('p9_forms') ?? $business->hasFeature('p9_forms'))
    <a href="{{ route('payroll.p9.index') }}" class="btn btn-secondary btn-sm">
         P9 Certificates
    </a>
    @endif
    {{-- The 4 remittance reports are Growth+ ('payroll_statutory') like P9,
    but weren't wrapped like it — the routes now enforce the plan/role gate
    server-side, but without this the buttons stayed visible to Solo owners
    who'd just get redirected on click. --}}
    @if($business->organization?->hasFeature('payroll_statutory') ?? $business->hasFeature('payroll_statutory'))
    <a href="{{ route('payroll.paye-remittance') }}" class="btn btn-secondary btn-sm">
         PAYE Remittance
    </a>
    <a href="{{ route('payroll.nssf-remittance') }}" class="btn btn-secondary btn-sm">
         NSSF Remittance
    </a>
    <a href="{{ route('payroll.shif-remittance') }}" class="btn btn-secondary btn-sm">
         SHIF Remittance
    </a>
    <a href="{{ route('payroll.housing-levy-remittance') }}" class="btn btn-secondary btn-sm">
         Housing Levy Remittance
    </a>
    <a href="{{ route('payroll.helb-remittance') }}" class="btn btn-secondary btn-sm">
         HELB Remittance
    </a>
    @endif
</div>

@if($periods->isEmpty())
    <div class="empty-state">
        
        <p>No payroll periods yet. Create your first period to get started.</p>
        <a href="{{ route('payroll.create') }}" class="btn btn-primary">
             Create First Period
        </a>
    </div>
@else
    <div class="table-card">
        <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Period</th>
                    <th>Pay Cycle</th>
                    <th>Staff</th>
                    <th>Total Gross</th>
                    <th>Total Net</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($periods as $period)
                <tr>
                    <td data-label="Period">
                        <div style="font-weight:600;">
                            {{ $period->period_start->format('d M Y') }}
                            &ndash;
                            {{ $period->period_end->format('d M Y') }}
                        </div>
                        @if($period->paid_at)
                            <div style="font-size:0.75rem; color:var(--color-text-muted);">
                                Paid {{ $period->paid_at->format('d M Y') }}
                            </div>
                        @endif
                    </td>
                    <td data-label="Pay Cycle" style="color:var(--color-text-muted); font-size:0.82rem;">
                        {{ ucfirst(str_replace('_', '-', $period->pay_cycle)) }}
                    </td>
                    <td data-label="Staff" style="text-align:center;">
                        <span class="badge badge-secondary">{{ $period->items()->count() }}</span>
                    </td>
                    <td data-label="Total Gross" style="font-weight:600;">
                        @if($period->total_gross > 0)
                            KSh {{ number_format($period->total_gross, 0) }}
                        @else
                            <span style="color:var(--color-text-muted);">—</span>
                        @endif
                    </td>
                    <td data-label="Total Net" style="font-weight:600; color:var(--color-success);">
                        @if($period->total_net > 0)
                            KSh {{ number_format($period->total_net, 0) }}
                        @else
                            <span style="color:var(--color-text-muted);">—</span>
                        @endif
                    </td>
                    <td data-label="Status">
                        <span class="badge badge-{{ $period->statusBadgeClass() }}">
                            {{ $period->statusLabel() }}
                        </span>
                    </td>
                    <td data-label="Actions" style="text-align:right;">
                        <div style="display:flex; gap:var(--space-2); justify-content:flex-end;">
                            <a href="{{ route('payroll.show', $period) }}"
                               class="btn btn-secondary btn-sm">
                                 View
                            </a>
                            @if($period->isDraft())
                            <form method="POST" action="{{ route('payroll.destroy', $period) }}"
                                  onsubmit="return confirm('Delete this draft period?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">
                                    Delete
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>

    @if($periods->hasPages())
    <div style="margin-top:var(--space-4);">
        {{ $periods->links() }}
    </div>
    @endif
@endif

</div>
@endsection
