<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the "Try the demo" sandbox harmless. Everything that could reach the outside
 * world (email, SMS, WhatsApp, M-Pesa, card payments, webhooks), change the login,
 * or store uploaded files is refused; the rest of the app works normally.
 */
class DemoGuard
{
    // Non-GET requests to these paths are refused inside a demo.
    private const BLOCKED = [
        '2fa/*', 'settings/2fa*', 'settings/password', 'settings/api*', 'settings/dashboard-link*', 'settings/email-domain*',
        'settings/mpesa*', 'settings/pesapal', 'settings/sms*', 'settings/whatsapp*', 'settings/etims*', 'settings/webhooks*',
        'settings/team*', 'settings/newsletters*', 'settings/branding*', 'campaigns/*/send', 'customers/*/portal-invite',
        'payment-links*', 'inventory/waitlist/*notify*', 'sales/*/verify-mpesa', 'api/mpesa/*', 'api/pesapal/*', 'paystack/*',
        'etims/*', 'attachments*', 'inventory/import', 'bank-reconciliation/*/import', 'support/*',
        'expense-claims/*/pay', 'payroll/*/items/*/pay',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isDemo($request)) {
            return $next($request);
        }

        $request->attributes->set('is_demo', true);
        // Anything that slips through still cannot send real mail.
        // (Queued mail runs immediately, under the same log mailer, so it can never be sent later by a worker.)
        config(['mail.default' => 'log', 'queue.default' => 'sync']);

        $write = ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);
        if ($write && ($request->allFiles() || $request->is(...self::BLOCKED))) {
            $message = 'This is switched off in the demo. Sign up free to use it for real.';
            return $request->expectsJson()
                ? response()->json(['message' => $message], 403)
                : back()->with('error', $message);
        }

        return $next($request);
    }

    private function isDemo(Request $request): bool
    {
        $user = Auth::guard('web')->user();
        if (! $user || ! $user->organization_id) return false;

        return Cache::remember('demo-org:' . $user->organization_id, 600,
            fn () => (bool) DB::table('organizations')->where('id', $user->organization_id)->value('is_demo'));
    }
}
