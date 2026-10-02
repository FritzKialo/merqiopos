<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Organization;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index(Request $request)
    {
        $query = Organization::with(['owner', 'businesses', 'activeSubscription'])
            ->withCount('businesses');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('organizations.name', 'like', "%{$search}%")
                  ->orWhereHas('owner', fn ($u) => $u->where('email', 'like', "%{$search}%")
                                                       ->orWhere('name',  'like', "%{$search}%"));
            });
        }

        if ($status = $request->input('status')) {
            if ($status === 'expired') {
                $query->where(function ($q) {
                    $q->where('status', 'suspended')
                      ->orWhere(function ($q2) {
                          $q2->where('status', 'trial')
                             ->where('trial_ends_at', '<', now());
                      });
                });
            } elseif ($status === 'expiring_soon') {
                // Trial ends within the next 7 days — not yet expired, but
                // close enough that an admin might want to reach out before
                // it lapses (upsell, or a manual extension for a good-faith
                // case). Distinct from 'expired' above, which is already past.
                $query->where('status', 'trial')
                      ->whereBetween('trial_ends_at', [now(), now()->addDays(7)]);
            } else {
                $query->where('status', $status);
            }
        }

        if ($plan = $request->input('plan')) {
            $query->where('subscription_plan', $plan);
        }

        match ($request->input('sort')) {
            'oldest'      => $query->oldest(),
            'stores_desc' => $query->orderByDesc('businesses_count'),
            'name'        => $query->orderBy('name'),
            default       => $query->latest(),
        };

        $organizations = $query->paginate(20)->withQueryString();

        // Platform-wide KPI strip — independent of the current filters, so
        // an admin always sees the whole picture even while drilled into a
        // filtered view. Active-subscription set for the MRR total is
        // pulled once here rather than per-organization to avoid an N+1.
        $activeSubs = Subscription::where('status', 'active')
            ->where('end_date', '>=', now()->toDateString())
            ->get(['amount', 'start_date', 'end_date']);

        // ->real() excludes throwaway "Try it now" demo orgs (see
        // Organization::scopeReal()) — this KPI strip previously counted
        // them the same as any paying customer, so every demo click bumped
        // Active/On Trial/Trial Ending Soon and Total Stores until the org
        // self-purged a day later.
        $stats = [
            'total'         => Organization::real()->count(),
            'active'        => Organization::real()->where('status', 'active')->count(),
            'trial'         => Organization::real()->where('status', 'trial')->where('trial_ends_at', '>=', now())->count(),
            'expiring_soon' => Organization::real()->where('status', 'trial')->whereBetween('trial_ends_at', [now(), now()->addDays(7)])->count(),
            'suspended'     => Organization::real()->where('status', 'suspended')->count(),
            'stores'        => Business::whereHas('organization', fn ($q) => $q->real())->count(),
            'users'         => User::whereNotNull('organization_id')
                ->whereHas('organization', fn ($q) => $q->real())
                ->count(),
            'mrr'           => $activeSubs->sum(function ($sub) {
                if (! $sub->start_date || ! $sub->end_date) return 0;
                $months = max(1, $sub->start_date->diffInMonths($sub->end_date));
                return $sub->amount / $months;
            }),
        ];

        return view('admin.organizations.index', compact('organizations', 'stats'));
    }

    public function show(Organization $organization)
    {
        $organization->load(['owner', 'businesses.users', 'subscriptions' => fn ($q) => $q->latest(), 'activeSubscription']);

        $activeSubscription = $organization->activeSubscription;

        $businessIds = $organization->businesses->pluck('id');

        // Lifetime revenue: every subscription ever paid for by this org,
        // active or not — a simple "how much has this customer paid us,
        // total" figure, distinct from the current MRR estimate.
        $lifetimeRevenue = $organization->subscriptions->sum('amount');

        // Sales trend across every one of the org's stores combined, last
        // 6 months — same shape/palette as org/dashboard.blade.php's own
        // revenue chart, so an admin sees the org's real trading activity
        // (not just its billing status) at a glance.
        $perMonthRows = Sale::selectRaw(\App\Support\PortableSql::yearMonth('created_at') . ', SUM(total_amount) as total')
            ->whereIn('business_id', $businessIds)
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

        return view('admin.organizations.show', compact(
            'organization', 'activeSubscription', 'lifetimeRevenue',
            'salesTrend', 'salesTrendLabels'
        ));
    }

    public function updateNotes(Request $request, Organization $organization)
    {
        $request->validate([
            'admin_notes' => 'nullable|string|max:5000',
        ]);

        $organization->update(['admin_notes' => $request->admin_notes]);

        // Note content itself isn't included — the note text can carry
        // sensitive support/CRM detail, and the fact that it was edited
        // (by whom, when) is what an audit trail needs, not a copy of it.
        AuditLog::record('admin.organization.notes_updated', $organization);

        return back()->with('success', 'Notes saved.');
    }

    // subscriptions.status has had a 'cancelled' enum value since the
    // table was created, but nothing anywhere ever wrote it — there was
    // no way to revoke a wrongly-granted or abused subscription short of
    // waiting for it to reach its own end_date. Deliberately just flips
    // status rather than special-casing anything else: CheckSubscription
    // already re-evaluates "does an active subscription exist" fresh on
    // every request and lazily downgrades the org to Free the same way
    // it already does for a subscription that expires naturally, so a
    // cancelled one is picked up automatically on the org owner's next
    // request with no extra code needed here.
    public function cancelSubscription(Organization $organization, Subscription $subscription)
    {
        if ($subscription->organization_id !== $organization->id) {
            abort(404);
        }

        if ($subscription->status !== 'active') {
            return back()->with('error', 'This subscription is not active.');
        }

        $subscription->update(['status' => 'cancelled']);

        AuditLog::record('admin.subscription.cancelled', $subscription, [
            'organization' => $organization->name,
            'plan'         => $subscription->plan,
            'amount'       => $subscription->amount,
        ]);

        return back()->with('success', 'Subscription cancelled. The organization will revert to the Free plan on its next visit.');
    }

    public function suspend(Organization $organization)
    {
        $organization->update(['status' => 'suspended']);

        AuditLog::record('admin.organization.suspended', $organization);

        return back()->with('success', "Organization \"{$organization->name}\" has been suspended.");
    }

    public function activate(Organization $organization)
    {
        $organization->update(['status' => 'active']);

        AuditLog::record('admin.organization.activated', $organization);

        return back()->with('success', "Organization \"{$organization->name}\" has been activated.");
    }

    /**
     * Last-resort recovery for an owner who has lost both their
     * authenticator device AND their recovery codes — nothing short of
     * this gets them back in otherwise. Gated behind 'sudo' at the route
     * level (the admin's own password+2FA, re-confirmed), same as every
     * other irreversible action on this page.
     */
    public function resetTwoFactor(Organization $organization)
    {
        $owner = $organization->owner;

        if (! $owner) {
            return back()->with('error', 'This organization has no owner account.');
        }

        $owner->update([
            'google2fa_secret'          => null,
            'two_factor_enabled'        => false,
            'two_factor_confirmed_at'   => null,
            'two_factor_recovery_codes' => null,
        ]);

        AuditLog::record('admin.user.2fa_reset', $owner, ['organization_id' => $organization->id]);

        return back()->with('success', "Two-factor authentication has been reset for {$owner->name}. They can sign in with just their password and set it up again from Settings.");
    }

    public function extendTrial(Request $request, Organization $organization)
    {
        $request->validate([
            'days' => 'required|integer|min:1|max:90',
        ]);

        $trialEnd = ($organization->trial_ends_at && $organization->trial_ends_at->isFuture())
            ? $organization->trial_ends_at
            : now();

        $newTrialEnd = $trialEnd->addDays((int) $request->days);

        $organization->update([
            'status'        => 'trial',
            'trial_ends_at' => $newTrialEnd,
        ]);

        AuditLog::record('admin.organization.trial_extended', $organization, [
            'days'             => (int) $request->days,
            'new_trial_ends_at' => $newTrialEnd->toDateString(),
        ]);

        return back()->with('success', "Trial extended by {$request->days} day(s) for \"{$organization->name}\".");
    }

    public function grantSubscription(Request $request, Organization $organization)
    {
        $request->validate([
            'plan'   => 'required|in:solo,growth,enterprise',
            'months' => 'required|integer|min:1|max:12',
        ]);

        $amount = config("plans.org.{$request->plan}.price", 0);

        $subscription = Subscription::create([
            'organization_id'   => $organization->id,
            'plan'              => $request->plan,
            'amount'            => $amount,
            'start_date'        => now()->toDateString(),
            'end_date'          => now()->addMonths((int) $request->months)->toDateString(),
            'status'            => 'active',
            'payment_reference' => 'ADMIN-GRANT-' . strtoupper(uniqid()),
            // Column defaults to 'mpesa' when omitted — this path never
            // takes a real payment at all, so leaving it unset would
            // mislabel every admin-granted subscription as M-Pesa-paid on
            // the admin/subscriptions page's payment-channel column/filter.
            'payment_channel'   => 'admin_grant',
        ]);

        $organization->update([
            'status'            => 'active',
            'subscription_plan' => $request->plan,
        ]);

        AuditLog::record('admin.subscription.granted', $subscription, [
            'organization' => $organization->name,
            'plan'         => $request->plan,
            'months'       => (int) $request->months,
            'amount'       => $amount,
        ]);

        return back()->with('success', "Subscription granted to \"{$organization->name}\" ({$request->plan}, {$request->months} month(s)).");
    }
}
