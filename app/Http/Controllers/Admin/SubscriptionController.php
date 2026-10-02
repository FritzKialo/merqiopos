<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        // Was: with('business') — every live subscription-creation path
        // (Paystack, M-Pesa, and the admin "grant subscription" action
        // above) has only ever set organization_id, never business_id
        // (billing is org-level, not per-branch). Eager-loading 'business'
        // loaded nothing useful, while the view actually renders
        // $sub->organization?->name for every row without it being eager
        // loaded — a real N+1 query on this page. 'business' is kept as a
        // real relation on the model for any legacy rows that do have it.
        $query = Subscription::with('organization')->latest();

        if ($plan = $request->input('plan')) {
            $query->where('plan', $plan);
        }

        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $query->where('status', 'active')
                      ->where('end_date', '>=', now()->toDateString());
            } elseif ($status === 'expired') {
                $query->where(function ($q) {
                    $q->where('status', '!=', 'active')
                      ->orWhere('end_date', '<', now()->toDateString());
                });
            } else {
                $query->where('status', $status);
            }
        }

        if ($search = $request->input('search')) {
            // Was: whereHas('business', ...) — searched the legacy,
            // effectively-always-null business_id column, so "search by
            // business name" (the field's own placeholder text) matched
            // zero results for every real subscription in the system.
            // Search by organization name instead, matching what this page
            // actually displays.
            $query->where(function ($q) use ($search) {
                $q->whereHas('organization', fn ($oq) => $oq->where('name', 'like', "%{$search}%"))
                  ->orWhere('payment_reference', 'like', "%{$search}%");
            });
        }

        if ($channel = $request->input('channel')) {
            $query->where('payment_channel', $channel);
        }

        match ($request->input('sort')) {
            'oldest'       => $query->reorder('created_at', 'asc'),
            'amount_desc'  => $query->reorder('amount', 'desc'),
            default        => null, // ->latest() from the base query above
        };

        $subscriptions = $query->paginate(25)->withQueryString();

        // Summary totals
        $totalRevenue = Subscription::where('status', 'active')->sum('amount');
        $monthRevenue = Subscription::where('status', 'active')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at',  now()->year)
            ->sum('amount');

        $activeCount = Subscription::where('status', 'active')
            ->where('end_date', '>=', now()->toDateString())
            ->count();
        $expiredCount = Subscription::where(function ($q) {
            $q->where('status', '!=', 'active')
              ->orWhere('end_date', '<', now()->toDateString());
        })->count();
        $totalCount = Subscription::count();

        // Grouped by whatever plan values actually exist in the data —
        // NOT a hardcoded list. The view used to hardcode
        // ['starter','business','enterprise'] for this breakdown, but
        // every subscription created since the org-level migration uses
        // 'solo'/'growth'/'scale'/'enterprise' instead — so that hardcoded
        // list was blind to every current real subscription in the system.
        $planTotals = Subscription::where('status', 'active')
            ->select('plan', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('plan')
            ->orderByDesc('total')
            ->get();

        return view('admin.subscriptions.index', compact(
            'subscriptions', 'totalRevenue', 'monthRevenue', 'planTotals',
            'activeCount', 'expiredCount', 'totalCount'
        ));
    }
}
