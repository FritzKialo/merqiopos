<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireRecentTwoFactor
{
    /**
     * Require re-verification for critical pages (sudo mode).
     *
     * If 2FA is enabled, the user must have verified within the last
     * 15 minutes to access the route. Otherwise they are redirected
     * to the challenge page with intent=sudo.
     *
     * If 2FA is not enabled, the route is accessible without this check
     * (the owner is responsible for enabling 2FA for the protection to apply).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || ! $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        $lastVerified = session('2fa_last_verified');
        $window       = 15 * 60; // 15 minutes in seconds

        if (! $lastVerified || (now()->timestamp - $lastVerified) > $window) {
            // Every sudo-gated route in this app is a mutating POST/PUT/
            // PATCH/DELETE action (grant-subscription, business.update,
            // team.store, ...), never a page to just look at. Redirecting
            // straight back to $request->url() after confirming identity
            // would issue a GET against that action URL — which 405s,
            // since none of them accept GET. Send the admin back to the
            // page the form itself lives on (session's tracked previous
            // GET, falling back to the Referer header) instead, so they
            // land somewhere safe to retry the action from, now verified
            // within the fresh window.
            $info = 'Please confirm your identity to continue.';
            if ($request->isMethod('get')) {
                session(['2fa_intended' => $request->fullUrl()]);
            } else {
                session(['2fa_intended' => url()->previous()]);
                $info = 'Please confirm your identity, then try that action again.';
            }

            return redirect()
                ->route('2fa.challenge', ['intent' => 'sudo'])
                ->with('info', $info);
        }

        return $next($request);
    }
}
