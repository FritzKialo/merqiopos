<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller {

    /** Minimum hours that must pass after a clock-out before clocking in again. */
    private const COOLDOWN_HOURS = 6;

    private function businessId(): int {
        return Auth::user()->currentBusiness()->id;
    }

    // ── Grid view ─────────────────────────────────────────────────────────
    public function index(Request $request) {
        $user       = Auth::user();
        $businessId = $this->businessId();

        // "mine" = self-service view: show only the current user, even for managers.
        $mine      = $request->boolean('mine');
        $isManager = $user->hasAnyRole('owner', 'manager') && !$mine;

        $month = $request->filled('month') ? Carbon::parse($request->month . '-01') : now()->startOfMonth();
        $startDate = $month->copy()->startOfMonth();
        $endDate   = $month->copy()->endOfMonth();

        // Build dates array (only weekdays)
        $dates = [];
        $current = $startDate->copy();
        while ($current->lte($endDate)) {
            if (!$current->isWeekend()) {
                $dates[] = $current->copy();
            }
            $current->addDay();
        }

        // Get employees
        if ($isManager) {
            $filterUserId = $request->filled('user_id') ? $request->user_id : null;
            $employeesQuery = User::whereHas('staffProfiles', fn($q) => $q->where('business_id', $businessId));
            $employees = $employeesQuery->orderBy('name')->get(['id','name']);
            if ($filterUserId) {
                $employees = $employees->where('id', $filterUserId);
            }
        } else {
            $employees = collect([Auth::user()]);
        }

        // Fetch attendance records
        $records = AttendanceRecord::forBusiness($businessId)
            ->whereIn('user_id', $employees->pluck('id'))
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->groupBy(fn($r) => $r->user_id . '_' . $r->date->format('Y-m-d'));

        $allEmployees = null;
        if ($isManager) {
            $allEmployees = User::whereHas('staffProfiles', fn($q) => $q->where('business_id', $businessId))
                ->orderBy('name')->get(['id','name']);
        }

        // Current user's clock state — drives the Clock In/Out button UI.
        $myOpen = $this->openSession($businessId, $user->id);

        $myCooldownUntil = null;
        if (!$myOpen) {
            $lastOut = $this->lastClockOut($businessId, $user->id);
            if ($lastOut) {
                $eligibleAt = $lastOut->copy()->addHours(self::COOLDOWN_HOURS);
                if (now()->lt($eligibleAt)) {
                    $myCooldownUntil = $eligibleAt;
                }
            }
        }

        // Today's sessions for the current user, shown as a punch list.
        $mySessionsToday = AttendanceSession::forBusiness($businessId)
            ->where('user_id', $user->id)
            ->whereDate('clock_in', now()->toDateString())
            ->orderBy('clock_in')
            ->get();

        return view('staff.attendance.index', compact(
            'employees', 'dates', 'records', 'month', 'allEmployees',
            'myOpen', 'myCooldownUntil', 'mySessionsToday', 'mine'
        ));
    }

    // ── Record / update attendance ────────────────────────────────────────
    public function store(Request $request) {
        $businessId = $this->businessId();

        $request->validate([
            // user_id was previously unscoped — could record attendance
            // against a user from another business. Scope it the same way
            // the employees list above is built: a staff profile at this
            // business.
            'user_id'   => ['required', 'integer', \Illuminate\Validation\Rule::exists('staff_profiles', 'user_id')->where('business_id', $businessId)],
            'date'      => 'required|date',
            'status'    => 'required|in:present,absent,half_day,leave',
            'clock_in'  => 'nullable|date_format:H:i',
            'clock_out' => 'nullable|date_format:H:i',
            'notes'     => 'nullable|string|max:500',
        ]);

        abort_if(!Auth::user()->hasAnyRole('owner', 'manager'), 403);

        AttendanceRecord::updateOrCreate(
            ['business_id' => $businessId, 'user_id' => $request->user_id, 'date' => $request->date],
            [
                'clock_in'  => $request->clock_in,
                'clock_out' => $request->clock_out,
                'status'    => $request->status,
                'notes'     => $request->notes,
            ]
        );

        return back()->with('success', 'Attendance recorded.');
    }

    // ── Clock in (current user) ───────────────────────────────────────────
    // Multiple sessions per day are allowed, but a new clock-in is blocked
    // until COOLDOWN_HOURS have passed since the most recent clock-out.
    public function clockIn() {
        $businessId = $this->businessId();
        $userId     = Auth::id();
        $now        = now();

        // Already in an open session — must clock out first.
        $open = $this->openSession($businessId, $userId);
        if ($open) {
            return back()->with('warning', "You're already clocked in since {$open->clock_in->format('H:i')}. Clock out first.");
        }

        // Enforce the cooldown since the last completed session.
        $lastOut = $this->lastClockOut($businessId, $userId);
        if ($lastOut) {
            $eligibleAt = $lastOut->copy()->addHours(self::COOLDOWN_HOURS);
            if ($now->lt($eligibleAt)) {
                $when = $eligibleAt->isToday() ? $eligibleAt->format('H:i') : $eligibleAt->format('H:i \o\n D, M j');
                return back()->with('warning',
                    "You clocked out at {$lastOut->format('H:i')}. You can clock in again after {$when} ("
                    . self::COOLDOWN_HOURS . "h rest).");
            }
        }

        AttendanceSession::create([
            'business_id' => $businessId,
            'user_id'     => $userId,
            'clock_in'    => $now,
        ]);

        $this->syncDailyRecord($businessId, $userId, $now->toDateString());

        return back()->with('success', "Clocked in at {$now->format('H:i')}.");
    }

    // ── Clock out (current user) ──────────────────────────────────────────
    public function clockOut() {
        $businessId = $this->businessId();
        $userId     = Auth::id();
        $now        = now();

        $open = $this->openSession($businessId, $userId);
        if (!$open) {
            return back()->with('warning', 'You need to clock in before clocking out.');
        }

        $open->update(['clock_out' => $now]);

        $this->syncDailyRecord($businessId, $userId, $open->clock_in->toDateString());

        return back()->with('success', "Clocked out at {$now->format('H:i')}.");
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /** The user's currently open (not-yet-clocked-out) session, if any. */
    private function openSession(int $businessId, int $userId): ?AttendanceSession {
        return AttendanceSession::forBusiness($businessId)
            ->where('user_id', $userId)
            ->whereNull('clock_out')
            ->latest('clock_in')
            ->first();
    }

    /** Timestamp of the user's most recent clock-out, or null. */
    private function lastClockOut(int $businessId, int $userId): ?Carbon {
        $ts = AttendanceSession::forBusiness($businessId)
            ->where('user_id', $userId)
            ->whereNotNull('clock_out')
            ->max('clock_out');

        return $ts ? Carbon::parse($ts) : null;
    }

    /**
     * Roll up the day's sessions into the single AttendanceRecord summary
     * (first clock-in, last clock-out) so the monthly grid and dashboard
     * keep working unchanged.
     */
    private function syncDailyRecord(int $businessId, int $userId, string $date): void {
        $firstIn = AttendanceSession::forBusiness($businessId)
            ->where('user_id', $userId)
            ->whereDate('clock_in', $date)
            ->min('clock_in');

        $lastOut = AttendanceSession::forBusiness($businessId)
            ->where('user_id', $userId)
            ->whereDate('clock_in', $date)
            ->max('clock_out');

        AttendanceRecord::updateOrCreate(
            ['business_id' => $businessId, 'user_id' => $userId, 'date' => $date],
            [
                'status'    => 'present',
                'clock_in'  => $firstIn ? Carbon::parse($firstIn)->format('H:i:s') : null,
                'clock_out' => $lastOut ? Carbon::parse($lastOut)->format('H:i:s') : null,
            ]
        );
    }
}
