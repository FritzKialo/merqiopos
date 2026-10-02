<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // Locale middleware
        $middleware->web(\App\Http\Middleware\SetLocale::class);

        // Content-Security-Policy on every web response (mode set by CSP_MODE).
        $middleware->web(\App\Http\Middleware\ContentSecurityPolicy::class);

        // Developer logs: what each store does and what fails (after the response is sent).
        $middleware->web(\App\Http\Middleware\LogActivity::class);

        // "Try the demo" sandboxes: block anything that could reach the outside world.
        $middleware->web(\App\Http\Middleware\DemoGuard::class);

        // Exclude M-Pesa Daraja callback from CSRF verification
        $middleware->validateCsrfTokens(except: [
            'api/mpesa/callback',
            'api/shop/mpesa/callback',
            // Registered in routes/features_marketing.php, required from
            // web.php — inherits the 'web' group's CSRF check like any other
            // web.php route. This was missing from this list entirely, the
            // same class of bug the shop's callback had (see bug #39): every
            // Payment Link M-Pesa payment has likely been silently 419'd
            // before PaymentLinkController::mpesaCallback() ever ran, so a
            // payment link could never auto-confirm via the webhook — only
            // caught while building the sibling portal-invoice callback below
            // and checking every other webhook route against this list.
            'api/pay/mpesa/callback',
            'api/portal/invoice/mpesa/callback',
            'api/payments/c2b/validate',
            'api/payments/c2b/confirm',
            'api/paystack/webhook',
            'api/pesapal/ipn',
        ]);

        // Named middleware aliases
        $middleware->alias([
            'safaricom'  => \App\Http\Middleware\TrustSafaricom::class,
            'subscribed' => \App\Http\Middleware\CheckSubscription::class,
            'store.limit' => \App\Http\Middleware\EnforceStoreSelection::class,
            'admin'      => \App\Http\Middleware\AdminMiddleware::class,
            'feature'    => \App\Http\Middleware\CheckPlanFeature::class,
            '2fa'        => \App\Http\Middleware\RequireTwoFactor::class,
            'sudo'       => \App\Http\Middleware\RequireRecentTwoFactor::class,
            'api.token'  => \App\Http\Middleware\ApiTokenAuth::class,
            'owner'       => \App\Http\Middleware\RequireOwnerRole::class,
            'role'        => \App\Http\Middleware\RequireRole::class,
            'portal.auth' => \App\Http\Middleware\CustomerPortalAuth::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Developer logs: every reportable error is also saved, with the store and
        // page it happened on, grouped so one bug is one line. Laravel's normal
        // logging continues as before.
        $exceptions->report(function (\Throwable $e) {
            \App\Support\DevLog::captureException($e);
        });

        // An expired/mismatched CSRF token normally shows a dead "419 Page Expired"
        // screen. Instead, bounce the user back to the form with their
        // (non-sensitive) input and a clear, recoverable message. We handle both
        // the raw TokenMismatchException and the 419 HttpException it maps to.
        $handle419 = function ($request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session expired. Please refresh and try again.',
                ], 419);
            }

            return redirect()->back()
                ->withInput($request->except([
                    '_token', 'password', 'password_confirmation', 'current_password',
                    'mpesa_consumer_secret', 'mpesa_passkey',
                ]))
                ->with('error', 'Your session expired for security reasons. Please review the form and submit again.');
        };

        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, $request) use ($handle419) {
            return $handle419($request);
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, $request) use ($handle419) {
            if ($e->getStatusCode() === 419) {
                return $handle419($request);
            }
        });
    })
    // All scheduled commands live in routes/console.php (the `commands:` file
    // above) — that file's own Schedule::command() calls register directly on
    // app boot, completely independently of this ->withSchedule() hook. Both
    // mechanisms were active at once: every command listed here was ALSO
    // registered in routes/console.php, so each ran twice a day (at two
    // different times, since the two files disagreed on the hour) for as
    // long as this file has existed. Confirmed live via `php artisan
    // schedule:list` showing literal duplicate rows before this fix.
    // sme:subscription-warnings has zero de-dup guard of its own, so this
    // meant every customer whose trial/subscription was expiring in exactly
    // 3 days got the SAME warning email twice, every day, in production.
    // Consolidated into routes/console.php alone — see that file.
    ->create();