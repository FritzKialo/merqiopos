@extends('layouts.app')
@section('title', 'My Portal')
@push('styles')
{{-- This page reuses the owner-dashboard's card/panel classes (.kpi-grid,
     .stat-card, .dash-panel, .welcome-bar, .alert-list) but dashboard.css
     only auto-loads on dashboard/index and org/dashboard — without it the
     page rendered as plain unstyled stacked text. Load it here too. --}}
<link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v={{ @filemtime(public_path('css/dashboard.css')) ?: '1' }}">
<style>
@media (max-width: 800px) {
    .staff-dash-layout { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page">

@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:14px;">{{ session('success') }}</div>
@endif
@if(session('warning'))
    <div class="alert alert-warning" style="margin-bottom:14px;">{{ session('warning') }}</div>
@endif

{{-- Welcome bar --}}
<div class="welcome-bar">
    <div class="welcome-content">
        <h1>Welcome, {{ $user->name }}</h1>
        <p>{{ $business?->name ?? 'Merqio POS' }} &mdash; Staff Portal &mdash; {{ now()->format('l, d M Y') }}</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="{{ route('staff.leave.create') }}" class="btn btn-primary btn--sm">+ Request Leave</a>
        <a href="{{ route('settings.password') }}" class="btn btn-outline btn--sm">Settings</a>
    </div>
</div>

{{-- KPI strip --}}
<div class="kpi-grid" style="margin-bottom:18px;">
    <div class="stat-card">
        <span class="stat-card__label">Payslips</span>
        <span class="stat-card__value">{{ $payslips->count() }}</span>
    </div>
    <div class="stat-card">
        <span class="stat-card__label">Pending Leave</span>
        <span class="stat-card__value {{ $pendingLeave > 0 ? 'text-warn' : '' }}">{{ $pendingLeave }}</span>
    </div>
    <div class="stat-card">
        <span class="stat-card__label">Approved Leave</span>
        <span class="stat-card__value text-success">{{ $approvedLeave }}</span>
    </div>
    <div class="stat-card">
        <span class="stat-card__label">Salary Advances</span>
        <span class="stat-card__value">KES {{ number_format($totalAdvances, 2) }}</span>
    </div>
    <div class="stat-card">
        <span class="stat-card__label">Days Tracked</span>
        <span class="stat-card__value">{{ $attendance->count() }}</span>
    </div>
</div>

<div class="staff-dash-layout" style="display:grid;grid-template-columns:1.2fr 1fr;gap:18px;align-items:start;">

    {{-- Left column --}}
    <div>

        {{-- Payslips --}}
        <div class="dash-panel">
            <div class="dash-panel-header">
                <h2 class="dash-panel-title">My Payslips</h2>
            </div>
            @if($payslips->isEmpty())
            <div class="dash-panel-body text-muted">No payslips yet.</div>
            @else
            <table>
                <thead>
                    <tr>
                        <th>Period</th>
                        <th style="text-align:right;">Net Pay</th>
                        <th style="text-align:center;">Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payslips as $item)
                    <tr>
                        <td data-label="Period">{{ $item->period?->name ?? '—' }}</td>
                        <td data-label="Net Pay" style="text-align:right;"><strong>KES {{ number_format($item->net_pay, 2) }}</strong></td>
                        <td data-label="Status" style="text-align:center;">
                            @if($item->isPaid())
                                <span class="badge-success">Paid</span>
                            @else
                                <span class="badge-warning">{{ ucfirst($item->status) }}</span>
                            @endif
                        </td>
                        <td data-label="Actions">
                            @if($item->period)
                            <a href="{{ route('payroll.payslip', [$item->period, $item]) }}" style="font-weight:600;">View</a>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>

        {{-- Attendance (last 14 days) --}}
        <div class="dash-panel">
            <div class="dash-panel-header">
                <h2 class="dash-panel-title">Recent Attendance</h2>
                @if($myOpen)
                <form method="POST" action="{{ route('staff.attendance.clock-out') }}" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn btn-primary btn--sm">Clock Out</button>
                </form>
                @elseif($myCooldownUntil)
                <button type="button" class="btn btn-primary btn--sm" style="opacity:.55;cursor:not-allowed;" disabled
                        title="Resting after your last shift">
                    Clock In ({{ $myCooldownUntil->isToday() ? $myCooldownUntil->format('H:i') : $myCooldownUntil->format('H:i, M j') }})
                </button>
                @else
                <form method="POST" action="{{ route('staff.attendance.clock-in') }}" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn btn-primary btn--sm">Clock In</button>
                </form>
                @endif
            </div>
            @if($attendance->isEmpty())
            <div class="dash-panel-body text-muted">No attendance records yet.</div>
            @else
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th style="text-align:center;">Clock In</th>
                        <th style="text-align:center;">Clock Out</th>
                        <th style="text-align:center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($attendance as $rec)
                    @php
                        $s = $rec->status ?? 'present';
                        $sBadge = $s === 'present' ? 'badge-success' : ($s === 'absent' ? 'badge-danger' : 'badge-warning');
                    @endphp
                    <tr>
                        <td data-label="Date">{{ $rec->date->format('d M Y') }}</td>
                        <td data-label="Clock In" style="text-align:center;">{{ $rec->clock_in ? \Carbon\Carbon::parse($rec->clock_in)->format('H:i') : '—' }}</td>
                        <td data-label="Clock Out" style="text-align:center;">{{ $rec->clock_out ? \Carbon\Carbon::parse($rec->clock_out)->format('H:i') : '—' }}</td>
                        <td data-label="Status" style="text-align:center;"><span class="{{ $sBadge }}">{{ ucfirst($s) }}</span></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>

    </div>

    {{-- Right column --}}
    <div>

        {{-- Leave requests --}}
        <div class="dash-panel">
            <div class="dash-panel-header">
                <h2 class="dash-panel-title">Leave Requests</h2>
                <a href="{{ route('staff.leave.create') }}" style="font-size:12px;font-weight:600;">+ New Request</a>
            </div>
            @if($leaves->isEmpty())
            <div class="dash-panel-body text-muted">No leave requests yet.</div>
            @else
            <ul class="alert-list">
                @foreach($leaves as $leave)
                @php
                    $st = $leave->status;
                    $stBadge = $st === 'approved' ? 'badge-success' : ($st === 'rejected' ? 'badge-danger' : 'badge-warning');
                @endphp
                <li>
                    <div>
                        <span class="al-text">
                            {{ $leave->start_date->format('d M') }} – {{ $leave->end_date->format('d M Y') }}
                            <span class="text-muted" style="font-weight:400;">({{ $leave->days_requested }} day{{ $leave->days_requested != 1 ? 's' : '' }})</span>
                        </span>
                        <span class="al-sub">{{ $leave->reason ?? 'No reason given' }}</span>
                    </div>
                    <span class="{{ $stBadge }}">{{ ucfirst($st) }}</span>
                </li>
                @endforeach
            </ul>
            @endif
        </div>

        {{-- Salary advances --}}
        <div class="dash-panel">
            <div class="dash-panel-header">
                <h2 class="dash-panel-title">Salary Advances</h2>
                <a href="{{ route('staff.advances.index') }}" style="font-size:12px;font-weight:600;">View all</a>
            </div>
            @if($advances->isEmpty())
            <div class="dash-panel-body text-muted">No salary advances recorded.</div>
            @else
            <ul class="alert-list">
                @foreach($advances as $adv)
                @php
                    $as = $adv->status;
                    $asBadge = $as === 'approved' ? 'badge-success' : ($as === 'rejected' ? 'badge-danger' : 'badge-warning');
                @endphp
                <li>
                    <div>
                        <span class="al-text">KES {{ number_format($adv->amount, 2) }}</span>
                        <span class="al-sub">{{ $adv->created_at->format('d M Y') }}</span>
                    </div>
                    <span class="{{ $asBadge }}">{{ ucfirst($as) }}</span>
                </li>
                @endforeach
            </ul>
            @endif
        </div>

    </div>
</div>

</div>
@endsection
