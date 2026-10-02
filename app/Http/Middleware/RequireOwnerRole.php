<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireOwnerRole
{
    /**
     * Restrict a route group to organization owners only.
     * Super admins are also allowed through (they manage the platform).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Owners and overall managers both get organization-wide access.
        if (!$user || (!$user->canActAsOwner() && !$user->isSuperAdmin())) {
            return redirect()->route('dashboard')
                ->with('error', 'This area is restricted to business owners.');
        }

        return $next($request);
    }
}
