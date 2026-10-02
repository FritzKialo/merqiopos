@extends('layouts.app')
@section('title', 'Appointments')
@push('styles')
<style>
@media (max-width: 768px) {
    .form-grid-3 { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Appointments</h1>
            <p class="page-subtitle">{{ \Carbon\Carbon::parse($date)->format('l, d M Y') }}</p>
        </div>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            <a href="{{ route('appointments.create') }}" class="btn btn-primary">+ New Appointment</a>
            <a href="{{ route('services.index') }}" class="btn btn-secondary">Manage Services</a>
        </div>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif

    {{-- Filters --}}
    <form method="GET" style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;align-items:flex-end;">
        <div>
            <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:0.25rem;">Date</label>
            <input type="date" name="date" value="{{ $date }}" class="form-control" style="height:36px;">
        </div>
        <div>
            <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:0.25rem;">Staff</label>
            <select name="staff_id" class="form-control" style="height:36px;">
                <option value="">All Staff</option>
                @foreach($staff as $s)
                    <option value="{{ $s->id }}" @selected($staffId == $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div style="display:flex;gap:0.5rem;">
            <button type="submit" class="btn btn-primary" style="height:36px;">Filter</button>
            <a href="{{ route('appointments.index', ['date' => \Carbon\Carbon::parse($date)->subDay()->toDateString()]) }}" class="btn btn-secondary" style="height:36px;">&#8249; Prev</a>
            <a href="{{ route('appointments.index', ['date' => \Carbon\Carbon::parse($date)->addDay()->toDateString()]) }}" class="btn btn-secondary" style="height:36px;">Next &#8250;</a>
        </div>
    </form>

    {{-- Summary --}}
    <div class="form-grid-3" style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem;">
        <div class="table-card" style="padding:1rem;text-align:center;">
            <div style="font-size:1.75rem;font-weight:700;">{{ $scheduled }}</div>
            <div style="font-size:0.8rem;color:var(--color-text-muted);">Scheduled</div>
        </div>
        <div class="table-card" style="padding:1rem;text-align:center;">
            <div style="font-size:1.75rem;font-weight:700;color:var(--color-warning);">{{ $confirmed }}</div>
            <div style="font-size:0.8rem;color:var(--color-text-muted);">Confirmed</div>
        </div>
        <div class="table-card" style="padding:1rem;text-align:center;">
            <div style="font-size:1.75rem;font-weight:700;color:var(--color-success);">{{ $completed }}</div>
            <div style="font-size:0.8rem;color:var(--color-text-muted);">Completed Today</div>
        </div>
    </div>

    {{-- Appointment timeline --}}
    @forelse($appointments as $appt)
    <div class="table-card" style="padding:1rem 1.25rem;margin-bottom:0.75rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.75rem;">
        <div style="display:flex;align-items:center;gap:1rem;">
            <div style="text-align:center;min-width:60px;">
                <div style="font-weight:700;font-size:1rem;">{{ \Carbon\Carbon::parse($appt->start_time)->format('H:i') }}</div>
                <div style="font-size:0.75rem;color:var(--color-text-muted);">{{ \Carbon\Carbon::parse($appt->end_time)->format('H:i') }}</div>
            </div>
            <div>
                <div style="font-weight:600;">{{ $appt->customer?->name ?? 'Walk-in' }}</div>
                <div style="font-size:0.85rem;color:var(--color-text-muted);">{{ $appt->service->name }}</div>
                @if($appt->user)
                    <div style="font-size:0.8rem;color:var(--color-text-muted);">Staff: {{ $appt->user->name }}</div>
                @endif
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
            <div style="text-align:right;">
                <div style="font-weight:600;">KES {{ number_format($appt->total_price, 2) }}</div>
                @php
                    $badgeClass = match($appt->status) {
                        'scheduled'  => 'badge-secondary',
                        'confirmed'  => 'badge-warning',
                        'completed'  => 'badge-success',
                        'cancelled'  => 'badge-danger',
                        'no_show'    => 'badge-danger',
                        default      => 'badge-secondary',
                    };
                @endphp
                <span class="badge {{ $badgeClass }}">{{ ucfirst(str_replace('_',' ',$appt->status)) }}</span>
            </div>
            <a href="{{ route('appointments.show', $appt) }}" class="btn btn-secondary" style="padding:0.35rem 0.75rem;font-size:0.82rem;">View</a>
        </div>
    </div>
    @empty
    <div class="table-card" style="padding:2rem;text-align:center;color:var(--color-text-muted);">
        No appointments for this day.
    </div>
    @endforelse
</div>
@endsection
