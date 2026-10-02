@extends('layouts.app')
@section('title', 'New Appointment')
@push('styles')
<style>
/* .form-grid-2's own mobile rule has no !important, so it never actually
   overrode this element's own inline grid-template-columns — inline always
   wins over an external rule for the same property, media query or not. */
@media (max-width: 768px) {
    .form-grid-2 { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <h1 class="page-title">New Appointment</h1>
        <a href="{{ route('appointments.index') }}" class="btn btn-secondary">Back</a>
    </div>

    <div class="table-card" style="padding:1.5rem;max-width:680px;">
        @if($errors->any())
            <div class="alert alert-danger" style="margin-bottom:1rem;">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('appointments.store') }}">
            @csrf
            <div class="form-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div style="grid-column:1/-1;">
                    <label style="font-weight:600;font-size:0.85rem;display:block;margin-bottom:0.25rem;">Service *</label>
                    <select name="service_id" id="service_id" class="form-control" required>
                        <option value="">Select service…</option>
                        @foreach($services as $svc)
                            <option value="{{ $svc->id }}"
                                data-duration="{{ $svc->duration_minutes }}"
                                data-price="{{ $svc->price }}"
                                @selected(old('service_id') == $svc->id)>
                                {{ $svc->name }} — {{ $svc->duration_minutes }}min — KES {{ number_format($svc->price,2) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label style="font-weight:600;font-size:0.85rem;display:block;margin-bottom:0.25rem;">Date *</label>
                    <input type="date" name="appointment_date" class="form-control" required value="{{ old('appointment_date', now()->toDateString()) }}">
                </div>

                <div>
                    <label style="font-weight:600;font-size:0.85rem;display:block;margin-bottom:0.25rem;">Start Time *</label>
                    <input type="time" name="start_time" id="start_time" class="form-control" required value="{{ old('start_time') }}">
                </div>

                <div>
                    <label style="font-weight:600;font-size:0.85rem;display:block;margin-bottom:0.25rem;">End Time *</label>
                    <input type="time" name="end_time" id="end_time" class="form-control" required value="{{ old('end_time') }}">
                </div>

                <div>
                    <label style="font-weight:600;font-size:0.85rem;display:block;margin-bottom:0.25rem;">Price (KES) *</label>
                    <input type="number" name="total_price" id="total_price" class="form-control" step="0.01" min="0" required value="{{ old('total_price') }}">
                </div>

                <div>
                    <label style="font-weight:600;font-size:0.85rem;display:block;margin-bottom:0.25rem;">Customer</label>
                    <select name="customer_id" class="form-control">
                        <option value="">Walk-in / No customer</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" @selected(old('customer_id') == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="grid-column:1/-1;">
                    <label style="font-weight:600;font-size:0.85rem;display:block;margin-bottom:0.25rem;">Assign Staff</label>
                    <select name="user_id" class="form-control">
                        <option value="">Unassigned</option>
                        @foreach($staff as $s)
                            <option value="{{ $s->id }}" @selected(old('user_id') == $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="grid-column:1/-1;">
                    <label style="font-weight:600;font-size:0.85rem;display:block;margin-bottom:0.25rem;">Notes</label>
                    <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div style="margin-top:1.25rem;display:flex;gap:0.5rem;">
                <button type="submit" class="btn btn-primary">Book Appointment</button>
                <a href="{{ route('appointments.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const serviceSelect = document.getElementById('service_id');
    const startInput    = document.getElementById('start_time');
    const endInput      = document.getElementById('end_time');
    const priceInput    = document.getElementById('total_price');

    function recalcEnd() {
        const opt = serviceSelect.options[serviceSelect.selectedIndex];
        const duration = parseInt(opt?.dataset?.duration || 0);
        const price    = opt?.dataset?.price;

        if (price) priceInput.value = parseFloat(price).toFixed(2);

        if (startInput.value && duration) {
            const [h, m] = startInput.value.split(':').map(Number);
            const total  = h * 60 + m + duration;
            const eh = String(Math.floor(total / 60) % 24).padStart(2,'0');
            const em = String(total % 60).padStart(2,'0');
            endInput.value = `${eh}:${em}`;
        }
    }

    serviceSelect.addEventListener('change', recalcEnd);
    startInput.addEventListener('change', recalcEnd);
});
</script>
@endsection
