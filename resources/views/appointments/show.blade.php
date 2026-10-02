@extends('layouts.app')
@section('title', 'Appointment #' . $appointment->id)
@push('styles')
<style>
@media (max-width: 900px) {
    .apt-show-layout { grid-template-columns: 1fr !important; }
}
@media (max-width: 500px) {
    .apt-show-details-grid { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Appointment #{{ $appointment->id }}</h1>
            @php
                $badgeClass = match($appointment->status) {
                    'scheduled' => 'badge-secondary',
                    'confirmed' => 'badge-warning',
                    'completed' => 'badge-success',
                    'cancelled','no_show' => 'badge-danger',
                    default     => 'badge-secondary',
                };
            @endphp
            <span class="badge {{ $badgeClass }}">{{ ucfirst(str_replace('_',' ',$appointment->status)) }}</span>
        </div>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            <a href="{{ route('appointments.edit', $appointment) }}" class="btn btn-secondary">Edit</a>
            <a href="{{ route('appointments.index', ['date' => $appointment->appointment_date->toDateString()]) }}" class="btn btn-secondary">Back</a>
        </div>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif

    <div class="apt-show-layout" style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;">
        <div>
            <div class="table-card" style="padding:1.25rem;margin-bottom:1rem;">
                <h3 style="font-weight:700;margin-bottom:1rem;">Details</h3>
                <div class="apt-show-details-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;font-size:0.88rem;">
                    <div><span style="color:var(--color-text-muted);">Service</span><br><strong>{{ $appointment->service->name }}</strong></div>
                    <div><span style="color:var(--color-text-muted);">Price</span><br><strong>KES {{ number_format($appointment->total_price, 2) }}</strong></div>
                    <div><span style="color:var(--color-text-muted);">Date</span><br><strong>{{ $appointment->appointment_date->format('d M Y') }}</strong></div>
                    <div><span style="color:var(--color-text-muted);">Time</span><br><strong>{{ \Carbon\Carbon::parse($appointment->start_time)->format('H:i') }} – {{ \Carbon\Carbon::parse($appointment->end_time)->format('H:i') }}</strong></div>
                    <div><span style="color:var(--color-text-muted);">Customer</span><br><strong>{{ $appointment->customer?->name ?? 'Walk-in' }}</strong></div>
                    <div><span style="color:var(--color-text-muted);">Staff</span><br><strong>{{ $appointment->user?->name ?? 'Unassigned' }}</strong></div>
                    @if($appointment->notes)
                    <div style="grid-column:1/-1;"><span style="color:var(--color-text-muted);">Notes</span><br>{{ $appointment->notes }}</div>
                    @endif
                    <div><span style="color:var(--color-text-muted);">Booked By</span><br><strong>{{ $appointment->bookedBy?->name }}</strong></div>
                    <div><span style="color:var(--color-text-muted);">Booked At</span><br><strong>{{ $appointment->created_at->format('d M Y H:i') }}</strong></div>
                </div>
            </div>

            {{-- Status timeline --}}
            <div class="table-card" style="padding:1.25rem;">
                <h3 style="font-weight:700;margin-bottom:1rem;">Status Timeline</h3>
                @php
                    $statuses = ['scheduled','confirmed','completed'];
                    $current  = $appointment->status;
                @endphp
                <div style="display:flex;flex-direction:column;gap:0.75rem;">
                    @foreach($statuses as $s)
                    @php
                        $done  = in_array($current, array_slice($statuses, array_search($s,$statuses)));
                        $color = $done ? 'var(--color-success)' : 'var(--color-border)';
                    @endphp
                    <div style="display:flex;align-items:center;gap:1rem;">
                        <div style="width:12px;height:12px;border-radius:50%;background:{{ $color }};flex-shrink:0;"></div>
                        <div style="font-weight:600;font-size:0.9rem;color:{{ $done ? 'inherit' : 'var(--color-text-muted)' }};">{{ ucfirst($s) }}</div>
                    </div>
                    @endforeach
                    @if(in_array($current, ['cancelled','no_show']))
                    <div style="display:flex;align-items:center;gap:1rem;">
                        <div style="width:12px;height:12px;border-radius:50%;background:var(--color-danger);flex-shrink:0;"></div>
                        <div style="font-weight:600;font-size:0.9rem;color:var(--color-danger);">{{ ucfirst(str_replace('_',' ',$current)) }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div>
            <div class="table-card" style="padding:1.25rem;">
                <h3 style="font-weight:700;margin-bottom:1rem;">Actions</h3>
                <div style="display:flex;flex-direction:column;gap:0.5rem;">
                    @if($appointment->status === 'scheduled')
                    <form method="POST" action="{{ route('appointments.status', $appointment) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="confirmed">
                        <button class="btn btn-warning" style="width:100%;">Confirm</button>
                    </form>
                    @endif
                    @if(in_array($appointment->status, ['scheduled','confirmed']))
                    <form method="POST" action="{{ route('appointments.status', $appointment) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="completed">
                        <button class="btn btn-success" style="width:100%;">Mark Completed</button>
                    </form>
                    <form method="POST" action="{{ route('appointments.status', $appointment) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="no_show">
                        <button class="btn btn-secondary" style="width:100%;">No-show</button>
                    </form>
                    <form method="POST" action="{{ route('appointments.status', $appointment) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="cancelled">
                        <button class="btn btn-danger" style="width:100%;">Cancel</button>
                    </form>
                    @endif
                    @if(!in_array($appointment->status, ['completed','cancelled','no_show']))
                    <hr style="border:none;border-top:1px solid var(--color-border);margin:0.25rem 0;">
                    @endif
                    <form method="POST" action="{{ route('appointments.destroy', $appointment) }}" onsubmit="return confirm('Delete this appointment?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger" style="width:100%;background:transparent;color:var(--color-danger);border:1px solid var(--color-danger);">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
