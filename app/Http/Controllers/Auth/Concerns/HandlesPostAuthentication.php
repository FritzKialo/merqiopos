<?php

namespace App\Http\Controllers\Auth\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Everything that happens once we know WHO the user is but before they reach
 * the app: super-admin fast path, suspension/deactivation checks, 2FA
 * gating, then the role-based landing page. Shared by password login and
 * Google sign-in so both enforce identical account-status rules — a
 * divergence here would be a real security gap, not just duplicated code.
 */
trait HandlesPostAuthentication
{
    protected function postAuthenticationRedirect($user)
    {
        // Super admins go straight to the admin panel — but still pass the
        // 2FA challenge first when they have 2FA enabled (highest-privilege
        // account, so it must be enforced like everyone else).
        if ($user->isSuperAdmin()) {
            if ($user->hasTwoFactorEnabled()) {
                session(['2fa_intended' => route('admin.dashboard')]);
                return redirect()->route('2fa.challenge');
            }

            return redirect()->route('admin.dashboard')
                ->with('success', 'Welcome back, ' . $user->name . '!');
        }

        if ($this->isSuspended($user)) {
            Auth::logout();
            return redirect()->route('login')->with('error',
                'Your account is suspended. Please contact support.');
        }

        if (! $user->is_active) {
            Auth::logout();
            return redirect()->route('login')->with('error',
                'Your account has been deactivated. Please contact your business owner.');
        }

        if ($user->hasTwoFactorEnabled()) {
            session(['2fa_intended' => $this->redirectAfterLogin($user)]);
            return redirect()->route('2fa.challenge');
        }

        return redirect()
            ->to($this->redirectAfterLogin($user))
            ->with('success', 'Welcome back, ' . $user->name . '!');
    }

    protected function redirectAfterLogin($user): string
    {
        if (! $user->canActAsOwner()) {
            // Set the first assigned store as active context for managers/cashiers/staff
            $business = $user->businesses()->first();
            if ($business) {
                session(['active_business_id' => $business->id]);
            }
        }

        return $user->postLoginRoute();
    }

    protected function isSuspended($user): bool
    {
        if ($user->canActAsOwner()) {
            return $user->organization?->status === 'suspended';
        }

        return $user->businesses()->first()?->organization?->status === 'suspended';
    }
}
