@extends('layouts.app')
@section('title', 'Staff')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}?v={{ @filemtime(public_path('css/settings.css')) ?: '1' }}">
@endpush

@section('content')
<div class="page">

<div class="page-header">
    <div>
        <h1 class="page-title">Staff</h1>
        <p class="page-subtitle">
            {{ $staff->count() }} staff member(s) at <strong>{{ $business->name }}</strong>
        </p>
    </div>
    <a href="{{ route('settings.team') }}" class="btn btn-secondary">
         Add Member
    </a>
</div>

@if($staff->isEmpty())
    <div class="empty-state">
        
        <p>No staff assigned to this store yet.</p>
        <a href="{{ route('settings.team') }}" class="btn btn-primary">
             Add Team Member
        </a>
    </div>
@else
    <div class="table-card">
        <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Staff Member</th>
                    <th>Role</th>
                    <th>Pay Type</th>
                    <th>Gross Pay</th>
                    <th>HR Profile</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($staff as $member)
                @php $profile = $member->hr_profile; @endphp
                <tr>
                    <td data-label="Staff Member">
                        <div style="display:flex; align-items:center; gap:var(--space-3);">
                            <div style="
                                width:36px; height:36px; border-radius:50%;
                                background:var(--color-surface-3);
                                color:var(--color-text);
                                display:flex; align-items:center; justify-content:center;
                                font-weight:700; font-size:0.9rem; flex-shrink:0;">
                                {{ strtoupper(substr($member->name, 0, 1)) }}
                            </div>
                            <div>
                                <div style="font-weight:600;">{{ $member->name }}</div>
                                <div style="font-size:0.78rem; color:var(--color-text-muted);">
                                    {{ $member->email }}
                                </div>
                            </div>
                        </div>
                    </td>
                    <td data-label="Role">
                        <span class="badge badge-{{ $member->role === 'manager' ? 'primary' : 'secondary' }}">
                            {{ $member->roleLabel() }}
                        </span>
                    </td>
                    <td data-label="Pay Type">
                        @if($profile)
                            <span class="badge badge-secondary">
                                {{ \App\Models\StaffProfile::payTypes()[$profile->pay_type] ?? ucfirst($profile->pay_type) }}
                            </span>
                        @else
                            <span style="color:var(--color-text-muted); font-size:0.82rem;">Not set</span>
                        @endif
                    </td>
                    <td data-label="Gross Pay" style="font-weight:600;">
                        @if($profile)
                            @if($profile->pay_type === 'retainer')
                                KSh {{ number_format($profile->retainer_amount, 0) }}/mo
                            @elseif($profile->pay_type === 'commission')
                                {{ $profile->commission_rate }}% of sales
                            @else
                                KSh {{ number_format($profile->retainer_amount, 0) }} + {{ $profile->commission_rate }}%
                            @endif
                        @else
                            <span style="color:var(--color-text-muted);">—</span>
                        @endif
                    </td>
                    <td data-label="HR Profile">
                        @if($profile)
                            <span class="badge badge-success">
                                 Set up
                            </span>
                        @else
                            <span class="badge badge-warning">
                                 Missing
                            </span>
                        @endif
                    </td>
                    <td data-label="Actions" style="text-align:right;">
                        <div style="display:flex; gap:var(--space-2); justify-content:flex-end;">
                            <a href="{{ route('staff.show', $member) }}" class="btn btn-secondary btn-sm">
                                 View
                            </a>
                            {{-- Payroll profile setup is owner / overall-manager only --}}
                            @if(auth()->user()->canActAsOwner())
                            <a href="{{ route('staff.profile', $member) }}" class="btn btn-primary btn-sm">
                                 {{ $profile ? 'Edit' : 'Set up' }}
                            </a>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>

    {{-- Warning: staff missing profiles --}}
    @php $missingProfiles = $staff->whereNull('hr_profile')->count(); @endphp
    @if($missingProfiles > 0)
    <div class="alert alert-warning" style="margin-top:var(--space-4);">
        
        <strong>{{ $missingProfiles }}</strong> staff member(s) don't have a payroll profile yet.
        They will be skipped when calculating payroll. Set up their profiles before running payroll.
    </div>
    @endif
@endif

</div>
@endsection
