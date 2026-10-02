@extends('layouts.app')
@section('title', 'Apply for Leave')
@push('styles')
<style>
@media (max-width: 420px) {
    .leave-dates-grid { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page" style="max-width: 640px;">
    <div class="page-header">
        <div>
            <h1 class="page-title">Apply for Leave</h1>
        </div>
        <a href="{{ route('staff.leave.index') }}" class="btn btn--outline">Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul style="margin:0; padding-left:1.2rem;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="table-card" style="padding: 1.5rem;">
        <form method="POST" action="{{ route('staff.leave.store') }}" id="leaveForm">
            @csrf

            <div class="form-group" style="margin-bottom:1.25rem;">
                <label class="form-label">Leave Type</label>
                <select name="leave_type_id" class="form-control" required onchange="updateBalance(this.value)">
                    <option value="">Select leave type...</option>
                    @foreach($leaveTypes as $type)
                        <option value="{{ $type->id }}" {{ old('leave_type_id') == $type->id ? 'selected' : '' }}
                            data-balance="{{ $balances[$type->id]->remaining_days ?? $type->days_per_year }}"
                            data-paid="{{ $type->is_paid ? 'Paid' : 'Unpaid' }}">
                            {{ $type->name }} ({{ $type->days_per_year }} days/yr)
                        </option>
                    @endforeach
                </select>
                <div id="balanceInfo" style="margin-top: 6px; font-size: 0.85rem; color: var(--color-text-muted);"></div>
            </div>

            <div class="leave-dates-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                <div class="form-group">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="{{ old('start_date') }}"
                           required min="{{ date('Y-m-d') }}" onchange="calcDays()">
                </div>
                <div class="form-group">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="{{ old('end_date') }}"
                           required min="{{ date('Y-m-d') }}" onchange="calcDays()">
                </div>
            </div>

            <div id="daysInfo" style="margin-bottom: 1.25rem; padding: 0.75rem; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 6px; font-size: 0.9rem; display: none;">
                Weekdays requested: <strong id="daysCount">0</strong>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label class="form-label">Reason <span style="color:var(--color-text-muted);">(optional)</span></label>
                <textarea name="reason" class="form-control" rows="3" placeholder="Briefly describe the reason for leave...">{{ old('reason') }}</textarea>
            </div>

            <button type="submit" class="btn btn--primary">Submit Request</button>
        </form>
    </div>
</div>

@push('scripts')
<script>
function updateBalance(typeId) {
    const select = document.querySelector('[name=leave_type_id]');
    const option = select.querySelector(`option[value="${typeId}"]`);
    const info   = document.getElementById('balanceInfo');
    if (option && typeId) {
        const bal  = option.dataset.balance;
        const paid = option.dataset.paid;
        info.textContent = `Remaining balance: ${bal} days | ${paid}`;
    } else {
        info.textContent = '';
    }
}

function calcDays() {
    const start = document.querySelector('[name=start_date]').value;
    const end   = document.querySelector('[name=end_date]').value;
    if (!start || !end) return;
    const s = new Date(start), e = new Date(end);
    if (s > e) return;
    let days = 0, cur = new Date(s);
    while (cur <= e) {
        const dow = cur.getDay();
        if (dow !== 0 && dow !== 6) days++;
        cur.setDate(cur.getDate() + 1);
    }
    document.getElementById('daysCount').textContent = days;
    document.getElementById('daysInfo').style.display = 'block';
}
</script>
@endpush
@endsection
