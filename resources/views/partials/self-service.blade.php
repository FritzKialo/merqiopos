{{-- Compact self-service widget — clock in/out + my leave/advances/payslips.
     Expects: $myOpen, $myCooldownUntil, $myPendingLeave, $myAdvancesTotal, $business --}}
<div class="dash-panel" style="margin-bottom:18px;">
    <div class="dash-panel-header">
        <h2 class="dash-panel-title">My Self-Service</h2>
        @if($myOpen)
            <form method="POST" action="{{ route('staff.attendance.clock-out') }}" style="display:inline;">
                @csrf
                <button type="submit" class="quick-action-btn quick-action-primary" style="padding:5px 13px;font-size:12px;">Clock Out</button>
            </form>
        @elseif($myCooldownUntil)
            <button type="button" class="quick-action-btn quick-action-secondary" style="padding:5px 13px;font-size:12px;opacity:.6;cursor:not-allowed;" disabled
                    title="Resting after your last shift">
                Clock In ({{ $myCooldownUntil->isToday() ? $myCooldownUntil->format('H:i') : $myCooldownUntil->format('H:i, M j') }})
            </button>
        @else
            <form method="POST" action="{{ route('staff.attendance.clock-in') }}" style="display:inline;">
                @csrf
                <button type="submit" class="quick-action-btn quick-action-primary" style="padding:5px 13px;font-size:12px;">Clock In</button>
            </form>
        @endif
    </div>
    <div style="display:flex;gap:20px;flex-wrap:wrap;align-items:center;padding:12px 18px;font-size:13px;">
        <div>Pending leave: <strong>{{ $myPendingLeave }}</strong></div>
        <div>My advances: <strong>KES {{ number_format($myAdvancesTotal, 2) }}</strong></div>
        @if($myOpen)
            <div style="color:var(--color-text-muted);">Clocked in since {{ $myOpen->clock_in->format('H:i') }}</div>
        @endif
        <div style="margin-left:auto;display:flex;gap:16px;">
            <a href="{{ route('staff.leave.index', ['mine' => 1]) }}" style="font-weight:600;color:#0a0a0a;">My Leave</a>
            <a href="{{ route('staff.advances.index', ['mine' => 1]) }}" style="font-weight:600;color:#0a0a0a;">My Advances</a>
            @if($business?->organization?->hasFeature('payroll'))
            <a href="{{ route('payroll.payslips.mine') }}" style="font-weight:600;color:#0a0a0a;">My Payslips</a>
            @endif
        </div>
    </div>
</div>
