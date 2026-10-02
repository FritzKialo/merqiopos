<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.custom');

        // Developer logs: sign-ins, sign-outs and failed sign-ins. A failed sign-in keeps
        // the IP and a masked email ("n***@gmail.com") — enough to spot someone trying
        // many accounts, never the password.
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, function ($e) {
            $ctx = \App\Support\DevLog::context();
            \App\Support\DevLog::activity([
                'kind' => 'login', 'user_id' => $e->user->id ?? null,
                'role' => $e->guard === 'customer' ? 'customer' : ($e->user->role ?? null),
                'organization_id' => $e->user->organization_id ?? null, 'business_id' => $ctx['business_id'],
                'method' => 'POST', 'path' => $ctx['path'], 'route_name' => $ctx['route_name'],
                'message' => $e->guard === 'customer' ? 'Customer portal sign-in' : 'Signed in',
            ]);
        });
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Logout::class, function ($e) {
            \App\Support\DevLog::activity([
                'kind' => 'logout', 'user_id' => $e->user->id ?? null, 'role' => $e->user->role ?? null,
                'organization_id' => $e->user->organization_id ?? null, 'message' => 'Signed out',
            ]);
        });
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Failed::class, function ($e) {
            // When the email belongs to a real account, tie the failure to that account's
            // store, so its owner can see that someone is trying to get into a staff login.
            $storeId = null;
            try {
                $storeId = $e->guard === 'customer' ? ($e->user->business_id ?? null) : ($e->user?->currentBusiness()?->id);
            } catch (\Throwable $x) {
            }
            \App\Support\DevLog::activity([
                'kind' => 'login_failed', 'outcome' => 'failed', 'user_id' => $e->user->id ?? null,
                'business_id' => $storeId, 'organization_id' => $e->user->organization_id ?? null,
                'role' => $e->guard === 'customer' ? 'customer' : ($e->user->role ?? null),
                'method' => 'POST', 'path' => '/' . ltrim(request()->path(), '/'),
                'message' => 'Failed sign-in for ' . \App\Support\DevLog::maskEmail($e->credentials['email'] ?? null),
            ]);
        });

        // @role('owner','manager') ... @endrole
        Blade::directive('role', function (string $expression) {
            return "<?php if(Auth::check() && Auth::user()->hasAnyRole({$expression})): ?>";
        });
        Blade::directive('endrole', function () {
            return '<?php endif; ?>';
        });

        // @cashier ... @endcashier  (shorthand for cashier-only blocks)
        Blade::directive('cashier', function () {
            return "<?php if(Auth::check() && Auth::user()->hasRole('cashier')): ?>";
        });
        Blade::directive('endcashier', function () {
            return '<?php endif; ?>';
        });

        // Laravel's default "guest" middleware (guards /login, /register,
        // the customer portal's /portal/login, etc. — all the SAME
        // middleware class, just parameterized with different guards)
        // redirects an already-authenticated visitor by probing for a
        // 'dashboard' or 'home' named route and sending them straight
        // there — it runs BEFORE the controller, so it fully bypasses
        // LoginController::showForm()'s own Auth::check() branch, which is
        // otherwise dead code. The callback isn't told which guard matched,
        // so check the customer guard explicitly first (portal.login uses
        // 'guest:customer') before falling back to the default web guard's
        // destination (User::postLoginRoute()).
        RedirectIfAuthenticated::redirectUsing(function ($request) {
            if (Auth::guard('customer')->check()) {
                return route('portal.dashboard');
            }

            $user = Auth::user();
            return $user ? $user->postLoginRoute() : '/';
        });
    }

    
}
