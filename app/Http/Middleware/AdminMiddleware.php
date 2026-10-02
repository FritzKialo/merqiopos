<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isSuperAdmin()) {
            abort(403, 'Unauthorized. Super admin access required.');
        }

        // 2FA is mandatory for super admins — the most privileged account in the
        // system. Force enrollment before granting any admin access. (The setup
        // page is a regular settings route, not an admin route, so no redirect loop.)
        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('settings.2fa.setup')
                ->with('warning', 'Two-factor authentication is required for admin accounts. Please enable it to continue.');
        }

        return $next($request);
    }
}
