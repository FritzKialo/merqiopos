<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    /**
     * Restrict a route to users whose role is in the allowed list.
     * Super admins always pass through.
     *
     * Usage:  ->middleware('role:owner,manager')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // An overall manager inherits everything an owner can reach: if a route
        // allows 'owner', it implicitly allows 'overall_manager' too.
        if ($user->isOverallManager() && in_array('owner', $roles, true)) {
            return $next($request);
        }

        if (! in_array($user->role, $roles, true)) {
            return redirect()->route('dashboard')
                ->with('error', 'You do not have access to that area.');
        }

        return $next($request);
    }
}
