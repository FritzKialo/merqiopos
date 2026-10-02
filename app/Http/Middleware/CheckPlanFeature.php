<?php

namespace App\Http\Middleware;

use App\Models\Business;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanFeature
{
    /**
     * Gate a route behind a specific plan feature.
     *
     * Feature access is determined by the organization's plan, which flows
     * down to all stores under it. The active business is resolved from the
     * session context set by CheckSubscription or the store switcher.
     *
     * Usage in routes:  ->middleware('feature:customers')
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        // Resolve active business from session context
        $businessId = session('active_business_id');
        $business   = $businessId
            ? Business::find($businessId)
            : $user?->currentBusiness();

        // Resolve the organization to check feature access
        $organization = $user->isOwner()
            ? $user->organization
            : $business?->organization;

        // Org-level features (payroll, p9_forms, cross_store_reports, api_access)
        // are defined on the org plan. Store-level features (customers, expenses,
        // barcode, etc.) are defined on the store plan derived from the org plan.
        $hasFeature = ($organization && $organization->hasFeature($feature))
            || ($business && $business->hasFeature($feature));

        if (!$hasFeature) {
            $upgradePlan  = $organization?->upgradePlan() ?? 'growth';
            $planName     = ucfirst($upgradePlan);
            $featureLabel = str_replace('_', ' ', $feature);

            return redirect()
                ->route('settings.subscription')
                ->with('warning', "The \"{$featureLabel}\" feature requires the {$planName} plan or higher. Upgrade to unlock it.");
        }

        return $next($request);
    }
}
