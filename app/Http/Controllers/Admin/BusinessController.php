<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BusinessController extends Controller
{
    private function mpesaConfiguredScope($query, bool $configured)
    {
        return $query->where(function ($q) use ($configured) {
            foreach (['mpesa_shortcode', 'mpesa_consumer_key', 'mpesa_consumer_secret', 'mpesa_passkey'] as $col) {
                $configured
                    ? $q->whereNotNull($col)->where($col, '!=', '')
                    : $q->orWhere(fn ($q2) => $q2->whereNull($col)->orWhere($col, ''));
            }
        });
    }

    public function index(Request $request)
    {
        $query = Business::with(['organization.owner', 'organization.subscriptions' => function ($q) {
            $q->where('status', 'active')
              ->where('end_date', '>=', now()->toDateString())
              ->latest();
        }]);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name',  'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('city',  'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($status = $request->input('status')) {
            if ($status === 'expired') {
                $query->where(function ($q) {
                    $q->where('status', 'suspended')
                      ->orWhere(function ($q2) {
                          $q2->where('status', 'trial')
                             ->where('trial_ends_at', '<', now());
                      });
                });
            } else {
                $query->where('status', $status);
            }
        }

        // M-Pesa configuration filter — a genuinely common support/sales
        // task ("which stores haven't set up M-Pesa yet") that previously
        // required opening every business individually to check.
        if ($mpesa = $request->input('mpesa')) {
            $this->mpesaConfiguredScope($query, $mpesa === 'configured');
        }

        match ($request->input('sort')) {
            'oldest' => $query->oldest(),
            'name'   => $query->orderBy('name'),
            default  => $query->latest(),
        };

        $businesses = $query->paginate(20)->withQueryString();

        // Platform-wide KPI strip — independent of the current filters,
        // same reasoning as admin/organizations.index.
        $stats = [
            'total'          => Business::count(),
            'active'         => Business::where('status', 'active')->count(),
            'trial'          => Business::where('status', 'trial')->count(),
            'suspended'      => Business::where('status', 'suspended')->count(),
            'mpesa_on'       => $this->mpesaConfiguredScope(Business::query(), true)->count(),
            'mpesa_off'      => $this->mpesaConfiguredScope(Business::query(), false)->count(),
            // Staff-per-store is a business_user pivot relation, not a
            // business_id column on users (a user can staff more than one
            // store) — distinct so someone on two stores counts once.
            'total_staff'    => DB::table('business_user')->distinct('user_id')->count('user_id'),
        ];

        return view('admin.businesses.index', compact('businesses', 'stats'));
    }

    public function show(Business $business)
    {
        $business->load(['organization.owner', 'users', 'organization.subscriptions' => function ($q) {
            $q->latest();
        }]);

        $activeSubscription = $business->organization?->subscriptions
            ->where('status', 'active')
            ->where('end_date', '>=', now()->toDateString())
            ->first();

        // This store's own sales trend, last 6 months — the org detail
        // page charts all stores combined, this charts just this one, so
        // an admin drilling into a single store sees its own trajectory.
        $perMonthRows = Sale::selectRaw(\App\Support\PortableSql::yearMonth('created_at') . ', SUM(total_amount) as total')
            ->where('business_id', $business->id)
            ->where('sale_status', 'completed')
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->groupBy('year', 'month')
            ->get()
            ->mapWithKeys(fn ($row) => [sprintf('%04d-%02d', $row->year, $row->month) => (float) $row->total]);

        $salesTrend = [];
        $salesTrendLabels = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $key  = $date->format('Y-m');
            $salesTrend[] = round($perMonthRows[$key] ?? 0, 2);
            $salesTrendLabels[] = $date->format('M Y');
        }

        $lifetimeSales = Sale::where('business_id', $business->id)
            ->where('sale_status', 'completed')
            ->sum('total_amount');

        return view('admin.businesses.show', compact(
            'business', 'activeSubscription', 'salesTrend', 'salesTrendLabels', 'lifetimeSales'
        ));
    }

    // Suspends this ONE store — independent of the organization's own
    // status/billing. Actually enforced now (see CheckSubscription
    // middleware); previously business.status existed and was displayed
    // throughout the admin panel but nothing ever checked it, so there
    // was no suspend/activate action here at all.
    public function suspend(Business $business)
    {
        $business->update(['status' => 'suspended']);

        AuditLog::record('admin.business.suspended', $business);

        return back()->with('success', "\"{$business->name}\" has been suspended.");
    }

    public function activate(Business $business)
    {
        $business->update(['status' => 'active']);

        AuditLog::record('admin.business.activated', $business);

        return back()->with('success', "\"{$business->name}\" has been activated.");
    }

    // Deactivate/reactivate one staff member from the support view — the
    // same is_active flag Settings → Team already toggles for business
    // owners, now actually enforced (see LoginController/CheckSubscription),
    // and previously reachable here only by asking the owner to do it.
    public function toggleMember(Business $business, \App\Models\User $user)
    {
        // business_user pivot, not a business_id column — a user can staff
        // more than one store, so this must check the specific pivot row
        // rather than trust a bare $user->business_id that may not even
        // exist. Guards against toggling a user from a crafted URL who
        // isn't actually staff at this business at all.
        if (! $business->users()->where('users.id', $user->id)->exists()) {
            abort(404);
        }

        // Mirrors SettingsController::authorizeMember()'s own guard —
        // an owner's account must never be deactivated this way, from
        // either the self-service or the admin path.
        if ($user->isOwner()) {
            abort(403, 'Cannot modify owner account.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        $status = $user->is_active ? 'activated' : 'deactivated';

        AuditLog::record("admin.team_member.{$status}", $user, [
            'business' => $business->name,
        ]);

        return back()->with('success', "{$user->name} has been {$status}.");
    }
}
