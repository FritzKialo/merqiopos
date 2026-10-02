<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    /**
     * Gate every request behind an active org subscription or a live trial.
     *
     * Also resolves and sets the active business context in session when
     * the user first enters the store app (managers/cashiers auto-resolved;
     * owners must have switched via the org dashboard or we default to first store).
     *
     * Access is allowed when EITHER of these is true:
     *   1. The organization is still within its free trial window.
     *   2. The organization has an active subscription (status=active, end_date >= today).
     * Neither of the above — no free tier to fall back to anymore since the
     * 3-tier pricing restructure — redirects to the subscription.required
     * paywall instead (see bottom).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Super admins have no org — let them through unconditionally
        if ($user?->isSuperAdmin()) {
            return $next($request);
        }

        // Staff deactivation (Settings → Team, or the admin panel) has
        // always flipped is_active, but nothing enforced it mid-session —
        // deactivating someone already logged in did nothing until they
        // happened to log out. LoginController blocks it at the login
        // attempt itself; this catches an existing session the moment
        // it's deactivated.
        if ($user && ! $user->is_active) {
            \Illuminate\Support\Facades\Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')
                ->with('error', 'Your account has been deactivated. Please contact your business owner.');
        }

        // ── Resolve active business context ──────────────────────────────────

        if (!session('active_business_id')) {
            $business = $user->currentBusiness();
            if ($business) {
                session(['active_business_id' => $business->id]);
            }
        }

        // ── Per-store suspension ────────────────────────────────────────────
        // Independent of the organization's own status — a single store can
        // be suspended (a specific branch closed, or flagged for support/
        // abuse reasons) while the rest of the org keeps trading normally.
        // business.status has existed and been displayed in the admin panel
        // this whole time, but nothing ever actually checked it here, so an
        // admin "suspending" one store changed only what was displayed —
        // staff at that store could keep using it without interruption.
        $activeBusinessId = session('active_business_id');
        if ($activeBusinessId) {
            $activeBusiness = \App\Models\Business::find($activeBusinessId);

            if ($activeBusiness && $activeBusiness->status === 'suspended') {
                if ($user->isOwner()) {
                    // Owners can have other, still-active stores — bounce
                    // them to the store picker instead of locking the whole
                    // account out, matching the store-limit picker's own
                    // "redirect to resolve, don't log out" pattern.
                    session()->forget('active_business_id');
                    return redirect()->route('org.dashboard')
                        ->with('error', "\"{$activeBusiness->name}\" has been suspended. Please select a different store.");
                }

                // Managers/cashiers are tied to exactly one store (see
                // User::currentBusiness()) — there is nothing else for them
                // to fall back to, so this mirrors the organization-suspended
                // behavior below.
                \Illuminate\Support\Facades\Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect()->route('login')
                    ->with('error', 'This store has been suspended. Please contact your business owner.');
            }
        }

        // ── Resolve organization ──────────────────────────────────────────────

        $organization = null;

        if ($user->isOwner()) {
            $organization = $user->organization;
        } else {
            // Managers/cashiers inherit org from their assigned store
            $businessId = session('active_business_id');
            if ($businessId) {
                $organization = \App\Models\Business::find($businessId)?->organization;
            }
        }

        // No organization attached — hard stop
        if (!$organization) {
            return redirect()->route('subscription.required');
        }

        // Suspended organization — cut off access entirely (mid-session too),
        // regardless of any active subscription. Mirrors the login-time check.
        if ($organization->status === 'suspended') {
            \Illuminate\Support\Facades\Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')
                ->with('error', 'This account has been suspended. Please contact support.');
        }

        // 1. Free trial still running, or a real, unexpired subscription row
        if ($organization->hasActiveAccess()) {
            return $next($request);
        }

        // 2. Trial ended (or a paid subscription lapsed) with nothing else
        // active — there's no free tier to gracefully fall back to anymore
        // (this app previously auto-downgraded to a permanent Free plan
        // here instead of locking the account out; that fallback no longer
        // exists). Send them to the same "Access Paused" wall already used
        // when an org has no subscription at all, with a clear path back
        // via Settings → Subscription.
        return redirect()->route('subscription.required');
    }
}
