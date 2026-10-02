<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveController extends Controller {

    private function businessId(): int {
        return Auth::user()->currentBusiness()->id;
    }

    // ── List leave requests ───────────────────────────────────────────────
    public function index(Request $request) {
        $user       = Auth::user();
        $businessId = $this->businessId();

        // "mine" = self-service view: always scope to the current user, even for managers.
        $mine      = $request->boolean('mine');
        $isManager = $user->hasAnyRole('owner', 'manager') && !$mine;

        $query = LeaveRequest::with(['user', 'leaveType', 'approver'])
            ->forBusiness($businessId);

        if (!$isManager) {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->latest()->paginate(20)->withQueryString();

        $employees = null;
        if ($isManager) {
            $employees = User::whereHas('businesses', fn($q) => $q->where('businesses.id', $businessId))
                ->orWhere(function($q) use ($businessId) {
                    $q->whereHas('organization.businesses', fn($q2) => $q2->where('businesses.id', $businessId))
                      ->where('role', 'owner');
                })
                ->orderBy('name')->get(['id','name']);
        }

        return view('staff.leave.index', compact('requests', 'employees', 'mine'));
    }

    // ── Create form ───────────────────────────────────────────────────────
    public function create() {
        $businessId  = $this->businessId();
        $leaveTypes  = LeaveType::forBusiness($businessId)->get();
        $currentYear = now()->year;

        // Get balances
        $balances = LeaveBalance::where('user_id', Auth::id())
            ->where('year', $currentYear)
            ->with('leaveType')
            ->get()
            ->keyBy('leave_type_id');

        return view('staff.leave.create', compact('leaveTypes', 'balances', 'currentYear'));
    }

    // ── Store a new leave request ─────────────────────────────────────────
    public function store(Request $request) {
        $request->validate([
            'leave_type_id' => 'required|integer',
            'start_date'    => 'required|date|after_or_equal:today',
            'end_date'      => 'required|date|after_or_equal:start_date',
            'reason'        => 'nullable|string|max:1000',
        ]);

        $businessId = $this->businessId();
        $userId     = Auth::id();
        $leaveType  = LeaveType::findOrFail($request->leave_type_id);

        // Verify leave type belongs to this business
        abort_if($leaveType->business_id !== $businessId, 403);

        $start = Carbon::parse($request->start_date);
        $end   = Carbon::parse($request->end_date);

        // Count weekdays
        $daysRequested = $this->countWeekdays($start, $end);

        // Check for overlapping requests
        $overlap = LeaveRequest::where('user_id', $userId)
            ->where('status', '!=', 'rejected')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_date', [$start, $end])
                  ->orWhereBetween('end_date', [$start, $end])
                  ->orWhere(fn($q2) => $q2->where('start_date', '<=', $start)->where('end_date', '>=', $end));
            })->exists();

        if ($overlap) {
            return back()->withInput()->with('error', 'You already have a leave request overlapping these dates.');
        }

        DB::transaction(function () use ($businessId, $userId, $leaveType, $request, $start, $end, $daysRequested) {
            $leaveRequest = LeaveRequest::create([
                'business_id'    => $businessId,
                'user_id'        => $userId,
                'leave_type_id'  => $leaveType->id,
                'start_date'     => $start,
                'end_date'       => $end,
                'days_requested' => $daysRequested,
                'reason'         => $request->reason,
                'status'         => $leaveType->requires_approval ? 'pending' : 'approved',
                'approved_at'    => $leaveType->requires_approval ? null : now(),
                'approved_by'    => $leaveType->requires_approval ? null : $userId,
            ]);

            // If auto-approved, deduct from balance
            if (!$leaveType->requires_approval) {
                $balance = LeaveBalance::getOrCreate($businessId, $userId, $leaveType, now()->year);
                $balance->used_days      += $daysRequested;
                $balance->remaining_days  = max(0, $balance->entitled_days - $balance->used_days);
                $balance->save();
            }
        });

        if ($leaveType->requires_approval) {
            \App\Models\AppNotification::notifyApprovers(
                \App\Models\Business::find($businessId), $userId, 'leave_requested', 'New Leave Request',
                Auth::user()->name . ' requested ' . $daysRequested . ' day(s) of ' . $leaveType->name . ' (' . $start->format('d M') . ' – ' . $end->format('d M') . ').',
                route('staff.leave.index'), 'calendar'
            );
        }

        return redirect()->route('staff.leave.index')->with('success', 'Leave request submitted successfully.');
    }

    // ── Approve ───────────────────────────────────────────────────────────
    public function approve(LeaveRequest $leave) {
        $this->authorizeManage($leave);
        if ($leave->user_id === Auth::id() && !Auth::user()->hasRole('owner')) return back()->with('error', 'You cannot approve or reject your own request — ask another manager or the owner.');
        abort_if(!$leave->isPending(), 422, 'Leave request is not pending.');

        DB::transaction(function () use ($leave) {
            $leave->update([
                'status'      => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            $balance = LeaveBalance::getOrCreate(
                $leave->business_id,
                $leave->user_id,
                $leave->leaveType,
                $leave->start_date->year
            );
            $balance->used_days      += $leave->days_requested;
            $balance->remaining_days  = max(0, $balance->entitled_days - $balance->used_days);
            $balance->save();
        });

        \App\Models\AppNotification::notifyUser($leave->business_id, $leave->user_id, 'leave_approved', 'Leave Approved',
            'Your leave request from ' . $leave->start_date->format('d M') . ' to ' . $leave->end_date->format('d M') . ' was approved by ' . Auth::user()->name . '.',
            route('staff.leave.index', ['mine' => 1]), 'check-circle');

        return back()->with('success', 'Leave request approved.');
    }

    // ── Reject ────────────────────────────────────────────────────────────
    public function reject(LeaveRequest $leave, Request $request) {
        $this->authorizeManage($leave);
        if ($leave->user_id === Auth::id() && !Auth::user()->hasRole('owner')) return back()->with('error', 'You cannot approve or reject your own request — ask another manager or the owner.');
        abort_if(!$leave->isPending(), 422, 'Leave request is not pending.');

        $request->validate(['rejection_reason' => 'nullable|string|max:500']);

        $leave->update([
            'status'           => 'rejected',
            'approved_by'      => Auth::id(),
            'approved_at'      => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        \App\Models\AppNotification::notifyUser($leave->business_id, $leave->user_id, 'leave_rejected', 'Leave Rejected',
            'Your leave request from ' . $leave->start_date->format('d M') . ' to ' . $leave->end_date->format('d M') . ' was rejected.'
            . ($request->rejection_reason ? ' Reason: ' . $request->rejection_reason : ''),
            route('staff.leave.index', ['mine' => 1]), 'x-circle');

        return back()->with('success', 'Leave request rejected.');
    }

    // ── Delete ────────────────────────────────────────────────────────────
    public function destroy(LeaveRequest $leave) {
        // The employee who made the request, or a manager/owner of the business.
        // The route used to be manager-only, so an employee was shown a Delete
        // button for their own pending request that always answered 403.
        abort_if($leave->business_id !== $this->businessId(), 403);
        abort_if($leave->user_id !== Auth::id() && !Auth::user()->hasAnyRole('owner', 'manager'), 403);
        abort_if(!$leave->isPending(), 422, 'Only pending requests can be deleted.');

        $leave->delete();

        return redirect()->route('staff.leave.index')->with('success', 'Leave request deleted.');
    }

    // ── Cancel an approved leave ──────────────────────────────────────────
    // Approved leave used to be permanent: no way to undo it, and its days
    // stayed deducted from the balance. The employee can cancel their own leave,
    // and an owner/manager anyone's, as long as it has not started yet — once
    // someone is on leave, part of it has been taken and a manager should
    // decide what that means, so it is refused here.
    public function cancel(LeaveRequest $leave) {
        abort_if($leave->business_id !== $this->businessId(), 403);
        $isSelf    = $leave->user_id === Auth::id();
        $isManager = Auth::user()->hasAnyRole('owner', 'manager');
        abort_if(!$isSelf && !$isManager, 403);

        if ($leave->status !== 'approved') {
            return back()->with('error', 'Only approved leave can be cancelled.');
        }
        if (!$leave->start_date->isFuture()) {
            return back()->with('error', 'This leave has already started, so it can no longer be cancelled. Ask the owner or a manager to adjust it.');
        }

        DB::transaction(function () use ($leave) {
            $leave = LeaveRequest::whereKey($leave->id)->lockForUpdate()->first();
            if ($leave->status !== 'approved') return; // cancelled by someone else a moment ago

            $leave->update(['status' => 'cancelled']);

            // Give the days back to the balance they were taken from.
            $balance = LeaveBalance::getOrCreate($leave->business_id, $leave->user_id, $leave->leaveType, $leave->start_date->year);
            $balance->used_days     = max(0, (float) $balance->used_days - (float) $leave->days_requested);
            $balance->remaining_days = max(0, (float) $balance->entitled_days - (float) $balance->used_days);
            $balance->save();
        });

        $range = $leave->start_date->format('d M') . ' – ' . $leave->end_date->format('d M');
        if ($isSelf) {
            \App\Models\AppNotification::notifyApprovers(
                \App\Models\Business::find($leave->business_id), $leave->user_id, 'leave_cancelled', 'Leave Cancelled',
                Auth::user()->name . ' cancelled their approved leave (' . $range . ').',
                route('staff.leave.index'), 'calendar'
            );
        } else {
            \App\Models\AppNotification::notifyUser($leave->business_id, $leave->user_id, 'leave_cancelled', 'Leave Cancelled',
                'Your approved leave (' . $range . ') was cancelled by ' . Auth::user()->name . '. The days were returned to your balance.',
                route('staff.leave.index', ['mine' => 1]), 'x-circle');
        }

        return back()->with('success', 'Leave cancelled and the days returned to the balance.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────
    private function authorizeManage(LeaveRequest $leave): void {
        $user = Auth::user();
        abort_if($leave->business_id !== $this->businessId(), 403);
        abort_if(!$user->hasAnyRole('owner', 'manager'), 403);
    }

    private function countWeekdays(Carbon $start, Carbon $end): float {
        $days = 0;
        $current = $start->copy();
        while ($current->lte($end)) {
            if (!$current->isWeekend()) {
                $days++;
            }
            $current->addDay();
        }
        return (float) $days;
    }
}
