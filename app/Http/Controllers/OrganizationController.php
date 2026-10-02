<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Expense;
use App\Models\PayrollPeriod;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrganizationController extends Controller
{
    // ── Org Dashboard ─────────────────────────────────────────────────────────

    public function dashboard()
    {
        $user = Auth::user();

        if (!$user->canActAsOwner()) {
            return redirect()->route('dashboard');
        }

        $organization = $user->organization;

        if (!$organization) {
            return redirect()->route('login')
                ->with('error', 'No organization found. Please contact support.');
        }

        $businesses = $organization->businesses()->get();
        $businessIds = $businesses->pluck('id')->toArray();

        // ── Aggregate stats across all stores ────────────────────────────────

        $todayRevenue = Sale::whereIn('business_id', $businessIds)
            ->where('sale_status', 'completed')
            ->whereDate('created_at', today())
            ->sum('total_amount');

        $mtdRevenue = Sale::whereIn('business_id', $businessIds)
            ->where('sale_status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total_amount');

        // Invoices billed outside the POS are revenue too — the store dashboard
        // and P&L already count them (non-draft/non-cancelled, not linked to a
        // POS sale); this org rollup previously summed Sales only, so it read
        // lower than the same stores' own dashboards.
        $mtdRevenue += (float) \App\Models\Invoice::whereIn('business_id', $businessIds)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereNull('sale_id')
            ->whereMonth('issue_date', now()->month)
            ->whereYear('issue_date', now()->year)
            ->sum('total');

        $mtdExpenses = Expense::whereIn('business_id', $businessIds)
            ->whereMonth('expense_date', now()->month)
            ->whereYear('expense_date', now()->year)
            ->sum('amount');

        $mtdProfit = $mtdRevenue - $mtdExpenses;

        // ── Org-level payroll aggregates (payroll feature only) ──────────────

        $hasPayroll = $organization->hasFeature('payroll');

        $mtdPayroll = 0;
        $ytdPayroll = 0;
        $pendingPayrollCount = 0;

        $ytdPayrollByStore = [];

        if ($hasPayroll && !empty($businessIds)) {
            $mtdPayroll = PayrollPeriod::whereIn('business_id', $businessIds)
                ->where('status', 'paid')
                ->whereMonth('period_start', now()->month)
                ->whereYear('period_start', now()->year)
                ->sum('total_net');

            $ytdPayroll = PayrollPeriod::whereIn('business_id', $businessIds)
                ->where('status', 'paid')
                ->whereYear('period_start', now()->year)
                ->sum('total_net');

            $pendingPayrollCount = PayrollPeriod::whereIn('business_id', $businessIds)
                ->whereIn('status', ['draft', 'approved'])
                ->count();

            // Per-store YTD for chart
            $ytdRows = PayrollPeriod::selectRaw('business_id, SUM(total_net) as ytd')
                ->whereIn('business_id', $businessIds)
                ->where('status', 'paid')
                ->whereYear('period_start', now()->year)
                ->groupBy('business_id')
                ->pluck('ytd', 'business_id');

            foreach ($businesses as $b) {
                $ytdPayrollByStore[$b->id] = [
                    'name' => $b->name,
                    'ytd'  => (float) ($ytdRows[$b->id] ?? 0),
                ];
            }
        }

        // ── Per-store stats for store cards ──────────────────────────────────

        $storeStats = [];

        foreach ($businesses as $business) {
            $todaySales = Sale::forBusiness($business->id)
                ->where('sale_status', 'completed')
                ->whereDate('created_at', today())
                ->sum('total_amount');

            $mtdSales = Sale::forBusiness($business->id)
                ->where('sale_status', 'completed')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('total_amount');

            $mtdSales += (float) \App\Models\Invoice::forBusiness($business->id)
                ->whereNotIn('status', ['draft', 'cancelled'])
                ->whereNull('sale_id')
                ->whereMonth('issue_date', now()->month)
                ->whereYear('issue_date', now()->year)
                ->sum('total');

            $staffCount = $business->users()->count();

            // Sum of net for any draft/approved periods (payroll due but not yet paid)
            $pendingPayroll = 0;
            $payrollMtd     = 0;
            if ($hasPayroll) {
                $pendingPayroll = PayrollPeriod::where('business_id', $business->id)
                    ->whereIn('status', ['draft', 'approved'])
                    ->sum('total_net');

                $payrollMtd = PayrollPeriod::where('business_id', $business->id)
                    ->where('status', 'paid')
                    ->whereMonth('period_start', now()->month)
                    ->whereYear('period_start', now()->year)
                    ->sum('total_net');
            }

            $storeStats[$business->id] = [
                'today_revenue'   => $todaySales,
                'mtd_revenue'     => $mtdSales,
                'staff_count'     => $staffCount,
                'pending_payroll' => $pendingPayroll,
                'payroll_mtd'     => $payrollMtd,
            ];
        }

        // ── 6-month revenue trend across all stores ───────────────────────────

        $sixMonthTrend = Sale::selectRaw(
                \App\Support\PortableSql::yearMonth('created_at') . ', SUM(total_amount) as total'
            )
            ->whereIn('business_id', $businessIds)
            ->where('sale_status', 'completed')
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->mapWithKeys(fn ($row) => [
                sprintf('%04d-%02d', $row->year, $row->month) => (float) $row->total
            ]);

        // Fill missing months with 0
        $trendLabels = [];
        $trendData   = [];
        for ($i = 5; $i >= 0; $i--) {
            $key           = now()->subMonths($i)->format('Y-m');
            $trendLabels[] = now()->subMonths($i)->format('M Y');
            $trendData[]   = $sixMonthTrend[$key] ?? 0;
        }

        // ── Per-store revenue trend (same 6-month window as above) — powers
        // the multi-line "revenue by store" chart so an org owner can see
        // which store is driving the aggregate trend rather than just one
        // flat combined line. ──────────────────────────────────────────────
        $perStoreRows = Sale::selectRaw(
                'business_id, ' . \App\Support\PortableSql::yearMonth('created_at') . ', SUM(total_amount) as total'
            )
            ->whereIn('business_id', $businessIds)
            ->where('sale_status', 'completed')
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->groupBy('business_id', 'year', 'month')
            ->get()
            ->groupBy('business_id');

        $storeTrend = [];
        foreach ($businesses as $business) {
            $byMonth = ($perStoreRows[$business->id] ?? collect())
                ->mapWithKeys(fn ($row) => [
                    sprintf('%04d-%02d', $row->year, $row->month) => (float) $row->total
                ]);

            $series = [];
            for ($i = 5; $i >= 0; $i--) {
                $key      = now()->subMonths($i)->format('Y-m');
                $series[] = round($byMonth[$key] ?? 0, 2);
            }

            $storeTrend[] = ['name' => $business->name, 'data' => $series];
        }

        // ── Store comparison (today / MTD revenue side by side) and store
        // revenue share — both reuse $storeStats already computed above,
        // no extra queries needed. ─────────────────────────────────────────
        $storeNames        = $businesses->pluck('name')->values()->all();
        $storeTodayRevenue = $businesses->pluck('id')->map(fn ($id) => round($storeStats[$id]['today_revenue'] ?? 0, 2))->values()->all();
        $storeMtdRevenue   = $businesses->pluck('id')->map(fn ($id) => round($storeStats[$id]['mtd_revenue'] ?? 0, 2))->values()->all();

        // ── Subscription info ─────────────────────────────────────────────────

        $activeSubscription = $organization->subscriptions()
            ->where('status', 'active')
            ->where('end_date', '>=', now()->toDateString())
            ->latest('end_date')
            ->first();

        return view('org.dashboard', [
            'organization'        => $organization,
            'businesses'          => $businesses,
            'storeStats'          => $storeStats,
            'todayRevenue'        => $todayRevenue,
            'mtdRevenue'          => $mtdRevenue,
            'mtdExpenses'         => $mtdExpenses,
            'mtdProfit'           => $mtdProfit,
            'trendLabels'         => $trendLabels,
            'trendData'           => $trendData,
            'storeTrend'          => $storeTrend,
            'storeNames'          => $storeNames,
            'storeTodayRevenue'   => $storeTodayRevenue,
            'storeMtdRevenue'     => $storeMtdRevenue,
            'activeSubscription'  => $activeSubscription,
            'storeLimit'          => $organization->storeLimit(),
            'canAddStore'         => $organization->canAddStore(),
            'hasPayroll'          => $hasPayroll,
            'mtdPayroll'          => $mtdPayroll,
            'ytdPayroll'          => $ytdPayroll,
            'pendingPayrollCount' => $pendingPayrollCount,
            'ytdPayrollByStore'   => $ytdPayrollByStore,
        ]);
    }

    // ── Store listing ─────────────────────────────────────────────────────────

    public function stores()
    {
        $user         = Auth::user();
        $organization = $user->organization;
        $businesses   = $organization?->businesses()->withCount('users')->get() ?? collect();

        return view('org.stores', compact('organization', 'businesses'));
    }

    // ── Store selection (after a downgrade puts the org over its store limit) ──
    // See Organization::hasUnresolvedStoreOverage() / EnforceStoreSelection.

    public function selectActiveStoresForm()
    {
        $organization = Auth::user()->organization;

        if (!$organization || !$organization->hasUnresolvedStoreOverage()) {
            return redirect()->route('org.dashboard');
        }

        $businesses = $organization->businesses()->withCount('users')->get();

        return view('org.select-stores', [
            'organization' => $organization,
            'businesses'   => $businesses,
            'limit'        => $organization->storeLimit(),
        ]);
    }

    public function selectActiveStores(Request $request)
    {
        $organization = Auth::user()->organization;

        if (!$organization) {
            return redirect()->route('org.dashboard');
        }

        $limit = $organization->storeLimit();
        $validIds = $organization->businesses()->pluck('id')->toArray();

        $validated = $request->validate([
            'business_ids'   => 'required|array|size:' . max($limit, 1),
            'business_ids.*' => 'integer|in:' . implode(',', $validIds),
        ], [
            'business_ids.size' => "Select exactly {$limit} store(s) to keep active.",
        ]);

        $organization->businesses()->update(['is_default' => false]);
        $organization->businesses()
            ->whereIn('id', $validated['business_ids'])
            ->update(['is_default' => true]);

        return redirect()->route('org.dashboard')
            ->with('success', 'Active stores updated. The rest are now read-only until you upgrade or remove them.');
    }

    // ── Add store ─────────────────────────────────────────────────────────────

    public function createStore()
    {
        $user         = Auth::user();
        $organization = $user->organization;

        if (!$organization->canAddStore()) {
            return redirect()->route('org.dashboard')
                ->with('error', 'You have reached your store limit. Please upgrade your plan to add more stores.');
        }

        return view('org.stores-create', [
            'organization'  => $organization,
            'businessTypes' => Business::businessTypes(),
        ]);
    }

    public function storeCreate(Request $request)
    {
        $user         = Auth::user();
        $organization = $user->organization;

        if (!$organization->canAddStore()) {
            return redirect()->route('org.dashboard')
                ->with('error', 'Store limit reached. Please upgrade your plan.');
        }

        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:businesses,email',
            'phone'         => 'required|string|max:20',
            'business_type' => 'nullable|string|max:50',
            'address'       => 'nullable|string|max:255',
            'city'          => 'nullable|string|max:100',
        ]);

        $business = Business::create([
            'organization_id'   => $organization->id,
            'subscription_plan' => 'starter',
            'status'            => $organization->isOnTrial() ? 'trial' : 'active',
            'trial_ends_at'     => $organization->trial_ends_at,
        ] + $validated);

        // Attach owner to the new store
        $business->users()->attach($user->id, ['role' => 'owner']);

        return redirect()->route('org.dashboard')
            ->with('success', "Store \"{$business->name}\" created successfully.");
    }

    // ── Edit store ────────────────────────────────────────────────────────────

    public function editStore(Business $business)
    {
        $this->authorizeStore($business);

        return view('org.stores-edit', [
            'organization'  => Auth::user()->organization,
            'business'      => $business,
            'businessTypes' => Business::businessTypes(),
        ]);
    }

    public function updateStore(Request $request, Business $business)
    {
        $this->authorizeStore($business);

        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:businesses,email,' . $business->id,
            'phone'         => 'required|string|max:20',
            'business_type' => 'nullable|string|max:50',
            'address'       => 'nullable|string|max:255',
            'city'          => 'nullable|string|max:100',
        ]);

        $business->update($validated);

        return redirect()->route('org.stores')
            ->with('success', 'Store updated successfully.');
    }

    // ── Store switcher ────────────────────────────────────────────────────────

    public function switchStore(Request $request, Business $business)
    {
        $this->authorizeStore($business);

        session(['active_business_id' => $business->id]);

        return redirect()->route('dashboard')
            ->with('success', "Switched to {$business->name}.");
    }

    // ── Authorization helper ──────────────────────────────────────────────────

    private function authorizeStore(Business $business): void
    {
        $org = Auth::user()->organization;

        abort_unless(
            $org && $business->organization_id === $org->id,
            403,
            'This store does not belong to your organization.'
        );
    }
}
