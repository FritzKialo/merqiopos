<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireTwoFactor
{
    /**
     * Enforce 2FA verification after login.
     * If the authenticated user has 2FA enabled but hasn't verified
     * this session, redirect them to the challenge page.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->hasTwoFactorEnabled() && ! session('2fa_verified')) {
            session(['2fa_intended' => $request->url()]);
            return redirect()->route('2fa.challenge');
        }

        return $next($request);
    }
}
