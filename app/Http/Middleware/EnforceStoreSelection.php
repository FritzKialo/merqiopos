<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceStoreSelection
{
    /**
     * Two related jobs, both driven by Organization::hasUnresolvedStoreOverage()
     * / Business::isLockedByPlan() — dynamic checks against the current plan's
     * store limit, no persisted "locked" state to keep in sync:
     *
     *   1. If the org has more stores than its plan allows and hasn't picked
     *      which ones to keep, force every request to the store-selection
     *      screen (mirrors the existing mandatory-2FA-for-admins pattern).
     *      Excludes the selection routes themselves, logout, and the
     *      subscription page (so they can still see/change their plan).
     *
     *   2. Otherwise, if the currently active store is locked by the plan
     *      (over limit and not one of the kept ones), allow normal viewing
     *      but block state-changing requests against it.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user || $user->isSuperAdmin()) {
            return $next($request);
        }

        $organization = $user->isOwner()
            ? $user->organization
            : (session('active_business_id')
                ? \App\Models\Business::find(session('active_business_id'))?->organization
                : null);

        if (!$organization) {
            return $next($request);
        }

        $exemptRoutes = [
            'org.stores.select-active',
            'org.stores.select-active.update',
            'logout',
            'settings.subscription',
            'paystack.subscribe',
            'mpesa.subscription.status',
        ];

        // Only owners/overall-managers can reach the picker (it's in the
        // owner-only 'org' route group) — redirecting anyone else there
        // would just bounce them back out and loop. Managers/cashiers fall
        // through to the normal per-store lock check below instead, which
        // already treats every store as locked while nothing is picked yet
        // (is_default is false on all of them), so they're still correctly
        // blocked from writes without being sent somewhere they can't go.
        if ($organization->hasUnresolvedStoreOverage() && $user->canActAsOwner()) {
            if (!in_array($request->route()?->getName(), $exemptRoutes, true)) {
                return redirect()->route('org.stores.select-active')
                    ->with('error', 'Your plan was downgraded below your current store count. Choose which store(s) to keep active before continuing.');
            }
            return $next($request);
        }

        // Org is within its store limit (or the pick is still valid) —
        // check only whether the ACTIVE store specifically is locked.
        $businessId = session('active_business_id');
        if ($businessId && !in_array($request->route()?->getName(), $exemptRoutes, true)) {
            $business = \App\Models\Business::find($businessId);
            if ($business && $business->isLockedByPlan() && $request->isMethod('GET') === false) {
                return redirect()->back()
                    ->with('error', 'This store is read-only — it was locked when your plan was downgraded below your store count. Upgrade your plan or free up a store slot to resume making changes here.');
            }
        }

        return $next($request);
    }
}
