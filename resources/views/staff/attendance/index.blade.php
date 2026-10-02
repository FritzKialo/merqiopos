@extends('layouts.app')
@section('title', 'Attendance')

@push('styles')
<style>
.att-grid { overflow-x: auto; }
.att-table { border-collapse: collapse; font-size: 0.8rem; min-width: 600px; }
.att-table th, .att-table td { border: 1px solid var(--color-border); padding: 6px 8px; white-space: nowrap; }
.att-table thead th { background: var(--color-surface); font-weight: 600; text-align: center; }
.att-table .emp-col { text-align: left; min-width: 140px; }
.att-cell { text-align: center; }
.att-badge { display: inline-block; font-size: 0.7rem; padding: 2px 6px; border-radius: 3px; }
.att-badge.present  { background:#d1fae5; color:#065f46; }
.att-badge.absent   { background:#fee2e2; color:#991b1b; }
.att-badge.half_day { background:#fef3c7; color:#92400e; }
.att-badge.leave    { background:#dbeafe; color:#1e40af; }
.att-time { font-size: 0.65rem; color: var(--color-text-muted); display: block; }
.clock-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 1rem; }

/* This is a genuine calendar grid — one column per day of the month, up
   to 31 columns. The app-wide table→card-stack rule (public/css/
   responsive.css, @media max-width:768px) would turn every employee's row
   into 30+ vertically-stacked mini-fields, which is far worse on mobile
   than the horizontal scroll this table already implements correctly via
   .att-grid. Explicitly opt out of the global stacking (not .table-plain —
   its padding/text-align resets would fight this table's own cell
   styling above; a plain display:revert scoped to just this table avoids
   that entirely). */
@media (max-width: 768px) {
    .att-table, .att-table thead, .att-table tbody,
    .att-table tr, .att-table th, .att-table td {
        display: revert;
    }
    .att-table td::before { content: none; }
}
</style>
@endpush

@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ ($mine ?? false) ? 'My Attendance' : 'Attendance' }}</h1>
            <p class="page-subtitle">{{ ($mine ?? false) ? 'Your clock-in / clock-out and daily status.' : 'Track staff clock-in / clock-out and daily status.' }}</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif

    {{-- Clock In / Out for current user. Multiple sessions per day are allowed,
         but a new clock-in is blocked until 6h after the last clock-out. --}}
    <div class="clock-actions">
        <form method="POST" action="{{ route('staff.attendance.clock-in') }}">
            @csrf
            <button class="btn btn--primary" {{ ($myOpen || $myCooldownUntil) ? 'disabled' : '' }}>
                @if($myOpen)
                    Clocked In at {{ $myOpen->clock_in->format('H:i') }}
                @elseif($myCooldownUntil)
                    Clock In (available {{ $myCooldownUntil->isToday() ? $myCooldownUntil->format('H:i') : $myCooldownUntil->format('H:i, M j') }})
                @else
                    Clock In Now
                @endif
            </button>
        </form>
        <form method="POST" action="{{ route('staff.attendance.clock-out') }}">
            @csrf
            <button class="btn btn--outline" {{ $myOpen ? '' : 'disabled' }}>Clock Out Now</button>
        </form>
    </div>

    @if($myCooldownUntil)
        <p class="att-time" style="margin:-0.5rem 0 1rem;">
            Resting after your last shift — you can clock in again at
            <strong>{{ $myCooldownUntil->isToday() ? $myCooldownUntil->format('H:i') : $myCooldownUntil->format('H:i \o\n D, M j') }}</strong>.
        </p>
    @endif

    {{-- Today's sessions for the current user --}}
    @if($mySessionsToday->isNotEmpty())
        <div style="margin-bottom:1rem;">
            <strong style="font-size:.85rem;">Your sessions today:</strong>
            <span style="font-size:.85rem;">
                @foreach($mySessionsToday as $s)
                    <span class="att-badge present" style="margin-left:6px;">
                        {{ $s->clock_in->format('H:i') }} – {{ $s->clock_out ? $s->clock_out->format('H:i') : 'now' }}
                    </span>
                @endforeach
            </span>
        </div>
    @endif

    {{-- Filters --}}
    <form method="GET" action="{{ route('staff.attendance.index') }}">
        <div class="toolbar" style="margin-bottom: 1rem;">
            <input type="month" name="month" class="toolbar-select" value="{{ $month->format('Y-m') }}" onchange="this.form.submit()">
            @if($allEmployees)
            <select name="user_id" class="toolbar-select" onchange="this.form.submit()">
                <option value="">All Employees</option>
                @foreach($allEmployees as $emp)
                    <option value="{{ $emp->id }}" {{ request('user_id') == $emp->id ? 'selected' : '' }}>{{ $emp->name }}</option>
                @endforeach
            </select>
            @endif
        </div>
    </form>

    <div class="att-grid">
        @if($employees->isEmpty())
            <div class="empty-state"><h3>No employees found.</h3></div>
        @else
        <table class="att-table">
            <thead>
                <tr>
                    <th class="emp-col">Employee</th>
                    @foreach($dates as $date)
                        <th style="text-align:center;">
                            {{ $date->format('D') }}<br>
                            <span style="font-weight:400;">{{ $date->format('d') }}</span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($employees as $emp)
                <tr>
                    <td class="emp-col">{{ $emp->name }}</td>
                    @foreach($dates as $date)
                        @php $key = $emp->id . '_' . $date->format('Y-m-d'); $rec = ($records[$key] ?? collect())->first(); @endphp
                        <td class="att-cell">
                            @if($rec)
                                <span class="att-badge {{ $rec->status }}">{{ ucfirst(str_replace('_',' ',$rec->status)) }}</span>
                                @if($rec->clock_in)
                                    <span class="att-time">In: {{ $rec->clock_in }}</span>
                                @endif
                                @if($rec->clock_out)
                                    <span class="att-time">Out: {{ $rec->clock_out }}</span>
                                @endif
                            @else
                                <span style="color:var(--color-text-muted);">—</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endsection
