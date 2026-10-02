<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\LeaveRequest;
use App\Models\Sale;
use App\Models\PayrollItem;
use App\Models\SalaryAdvance;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller {

    public function index(ReportService $reportService) {
        $user     = Auth::user();

        if ($user->isSuperAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        // Staff get their own portal instead of the business dashboard
        if ($user->hasRole('staff')) {
            return $this->staffPortal($user);
        }

        $business = $user->currentBusiness();

        if (! $business) {
            return redirect()->route('org.dashboard');
        }

        $data = $reportService->getDashboardData($business->id);
        $data['myRevenueToday'] = 0;

        // Cashiers see THEIR OWN figures, not the whole store's. Sales record the
        // cashier in `user_id`, so scope the personal cards and recent list to them.
        if ($user->isCashier()) {
            $myToday = Sale::forBusiness($business->id)
                ->where('user_id', $user->id)
                ->where('sale_status', 'completed')
                ->whereDate('created_at', today());

            $data['todayTransactions'] = (clone $myToday)->count();
            $data['myRevenueToday']    = (clone $myToday)->sum('total_amount');

            $data['recentSales'] = Sale::with('customer')
                ->forBusiness($business->id)
                ->where('user_id', $user->id)
                ->where('sale_status', 'completed')
                ->latest()
                ->take(10)
                ->get();
        }

        // Personal self-service widget data (clock state, my leave/advances).
        $data = array_merge($data, $this->selfServiceData($business, $user));

        return view('dashboard.index', array_merge($data, [
            'user'     => $user,
            'business' => $business,
        ]));
    }

    /**
     * AJAX: revenue/expenses/profit series for the dashboard's date-range
     * picker + "compare to previous period" toggle. Powers the chart without
     * a full page reload.
     */
    public function chartData(Request $request, ReportService $reportService) {
        $business = Auth::user()->currentBusiness();
        abort_unless($business, 404);

        [$start, $end] = $this->resolveChartRange($request);
        $response = ['current' => $reportService->revenueExpenseSeries($business->id, $start, $end)];

        if ($request->boolean('compare')) {
            $lengthDays = $start->diffInDays($end) + 1;
            $prevEnd    = $start->copy()->subDay()->endOfDay();
            $prevStart  = $prevEnd->copy()->subDays($lengthDays - 1)->startOfDay();
            $response['previous'] = $reportService->revenueExpenseSeries($business->id, $prevStart, $prevEnd);
        }

        return response()->json($response);
    }

    /**
     * AJAX: lightweight KPI snapshot polled periodically by the dashboard so
     * the "Today's revenue/expenses/profit" cards stay live without a reload.
     */
    public function kpiSnapshot(ReportService $reportService) {
        $business = Auth::user()->currentBusiness();
        abort_unless($business, 404);

        return response()->json($reportService->kpiSnapshot($business->id));
    }

    /**
     * Resolve the chart's requested date range: either an explicit
     * start/end pair (the "Custom" picker) or a `days` preset (7/30/90/180/365,
     * defaulting to 180 for anything else — including a missing/tampered value).
     */
    private function resolveChartRange(Request $request): array {
        if ($request->filled('start') && $request->filled('end')) {
            $start = Carbon::parse($request->input('start'))->startOfDay();
            $end   = Carbon::parse($request->input('end'))->endOfDay();

            if ($end->lt($start)) {
                [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
            }
            // Guard against an absurdly wide custom range dragging in the
            // whole sales table.
            if ($start->diffInDays($end) > 730) {
                $start = $end->copy()->subDays(730)->startOfDay();
            }

            return [$start, $end];
        }

        $days = (int) $request->input('days', 180);
        $days = in_array($days, [7, 30, 90, 180, 365], true) ? $days : 180;

        return [now()->subDays($days - 1)->startOfDay(), now()->endOfDay()];
    }

    private function staffPortal($user) {
        $business = $user->currentBusiness();

        $payslips = PayrollItem::where('user_id', $user->id)
            ->with('period')
            ->orderByDesc('created_at')
            ->take(6)
            ->get();

        $leaves = LeaveRequest::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->take(10)
            ->get();

        $attendance = AttendanceRecord::where('user_id', $user->id)
            ->orderByDesc('date')
            ->take(14)
            ->get();

        $advances = SalaryAdvance::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        $pendingLeave   = $leaves->where('status', 'pending')->count();
        $approvedLeave  = $leaves->where('status', 'approved')->count();
        $totalAdvances  = $advances->where('status', 'approved')->sum('amount');

        // Clock In/Out state for the portal button (mirrors AttendanceController:
        // multiple sessions per day, 6h cooldown after clock-out).
        $myOpen = null;
        $myCooldownUntil = null;
        if ($business) {
            $myOpen = AttendanceSession::where('business_id', $business->id)
                ->where('user_id', $user->id)
                ->whereNull('clock_out')
                ->latest('clock_in')
                ->first();

            if (!$myOpen) {
                $lastOut = AttendanceSession::where('business_id', $business->id)
                    ->where('user_id', $user->id)
                    ->whereNotNull('clock_out')
                    ->max('clock_out');
                if ($lastOut) {
                    $eligibleAt = \Carbon\Carbon::parse($lastOut)->addHours(6);
                    if (now()->lt($eligibleAt)) {
                        $myCooldownUntil = $eligibleAt;
                    }
                }
            }
        }

        return view('dashboard.staff', compact(
            'user', 'business', 'payslips', 'leaves',
            'attendance', 'advances', 'pendingLeave', 'approvedLeave', 'totalAdvances',
            'myOpen', 'myCooldownUntil'
        ));
    }

    /**
     * Personal self-service data for the compact dashboard widget shown to
     * cashiers, managers and owners: clock state (6h cooldown) plus the user's
     * own pending-leave count and approved-advance total.
     */
    private function selfServiceData($business, $user): array {
        $myOpen = null;
        $myCooldownUntil = null;

        if ($business) {
            $myOpen = AttendanceSession::where('business_id', $business->id)
                ->where('user_id', $user->id)
                ->whereNull('clock_out')
                ->latest('clock_in')
                ->first();

            if (!$myOpen) {
                $lastOut = AttendanceSession::where('business_id', $business->id)
                    ->where('user_id', $user->id)
                    ->whereNotNull('clock_out')
                    ->max('clock_out');
                if ($lastOut) {
                    $eligibleAt = \Carbon\Carbon::parse($lastOut)->addHours(6);
                    if (now()->lt($eligibleAt)) {
                        $myCooldownUntil = $eligibleAt;
                    }
                }
            }
        }

        $myPendingLeave  = LeaveRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->count();

        $myAdvancesTotal = SalaryAdvance::where('user_id', $user->id)
            ->where('status', 'approved')
            ->sum('amount');

        return compact('myOpen', 'myCooldownUntil', 'myPendingLeave', 'myAdvancesTotal');
    }
}
