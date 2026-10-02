<?php

use App\Http\Controllers\InventoryController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockReceiveController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\SaleReturnController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MpesaController;
use App\Http\Controllers\PaystackController;
use App\Http\Controllers\PesapalController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\RecurringInvoiceController;
use App\Http\Controllers\CustomerCreditController;
use App\Http\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\OnboardingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\DomainSettingsController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\BusinessController as AdminBusinessController;
use App\Http\Controllers\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\Admin\OrganizationController as AdminOrganizationController;
use App\Http\Controllers\Admin\NewsletterController as AdminNewsletterController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\CreditNoteController;
use App\Http\Controllers\DeliveryNoteController;
use App\Http\Controllers\EtimsController;
use App\Http\Controllers\PettyCashController;
use App\Http\Controllers\SupplierPaymentController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\CustomerPortalController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\CustomerTagController;
use App\Http\Controllers\CustomFieldController;
use App\Http\Controllers\Shop\StoreController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\TableController;

// ── Language switcher ─────────────────────────────────────────────────────
Route::get('/language/{locale}', [LanguageController::class, 'switch'])->name('language.switch');

// ── Public QR table self-ordering (no auth required) ─────────────────────
// The token is RestaurantTable.qr_token, not the numeric id — unguessable, so a
// diner can't just increment it to see another table's order.
Route::prefix('order/{token}')->name('table-order.')->group(function () {
    Route::get('/',  [\App\Http\Controllers\TableOrderPublicController::class, 'show'])->name('public');
    Route::post('/', [\App\Http\Controllers\TableOrderPublicController::class, 'store'])
        ->middleware('throttle:20,1,table-order')->name('public.store');
});

// ── Public Online Shop (no auth required) ────────────────────────────────
Route::prefix('shop/{slug}')->name('shop.')->group(function () {
    Route::get('/',                        [StoreController::class, 'index'])->name('index');
    Route::get('/product/{product}',       [StoreController::class, 'product'])->name('product');
    // No duplicate-block here on purpose — adding/removing/adjusting items
    // repeatedly IS normal shopping, unlike the other public forms. Just
    // rate limiting, generous enough for genuine browsing (all three share
    // one bucket since they're really one continuous cart-editing action,
    // not separate form submissions), to stop outright automated hammering.
    Route::post('/cart/add',               [StoreController::class, 'addToCart'])->middleware('throttle:60,60,shop-cart')->name('cart.add');
    Route::post('/cart/add-bundle',        [StoreController::class, 'addBundleToCart'])->middleware('throttle:60,60,shop-cart')->name('cart.add-bundle');
    Route::post('/cart/remove/{key}',      [StoreController::class, 'removeFromCart'])->middleware('throttle:60,60,shop-cart')->name('cart.remove');
    Route::post('/cart/update-qty',        [StoreController::class, 'updateCartQty'])->middleware('throttle:60,60,shop-cart')->name('cart.update-qty');
    Route::get('/cart',                    [StoreController::class, 'cart'])->name('cart');
    // Same throttle bucket/reasoning as the cart-editing routes above —
    // trying a coupon code is part of normal cart editing, not a separate
    // form a bot would spam distinctly from adding/removing items.
    Route::post('/coupon/apply',           [StoreController::class, 'applyCoupon'])->middleware('throttle:60,60,shop-cart')->name('coupon.apply');
    Route::post('/coupon/remove',          [StoreController::class, 'removeCoupon'])->middleware('throttle:60,60,shop-cart')->name('coupon.remove');
    // 10/hour per IP — more generous than the reviews/waitlist forms since
    // a genuine shopper legitimately might retry after a failed M-Pesa
    // prompt, or place a second real order later the same hour. Own
    // prefix so it doesn't share a pooled counter with those other forms
    // (see shop.reviews.store's route comment for why that matters).
    Route::post('/checkout',               [StoreController::class, 'checkout'])
        ->middleware('throttle:10,60,shop-checkout')->name('checkout');
    Route::get('/order/{reference}',       [StoreController::class, 'orderConfirmation'])->name('order');
    Route::get('/order/{reference}/mpesa-qr', [StoreController::class, 'mpesaQr'])->name('order.mpesa-qr');
});
// Was missing the 'safaricom' IP-restriction middleware every other M-Pesa
// callback endpoint in this app correctly has — this one just trusts
// ResultCode==0 in the raw POST body to mark an order paid and decrement
// stock, with nothing stopping anyone (not just Safaricom's real servers)
// from posting a forged "payment succeeded" callback for a checkout_id they
// obtained any other way and getting free goods.
Route::middleware('safaricom')->post('/api/shop/mpesa/callback', [StoreController::class, 'mpesaCallback'])->name('shop.mpesa.callback');
Route::middleware('safaricom')->post('/api/portal/invoice/mpesa/callback', [CustomerPortalController::class, 'invoiceMpesaCallback'])->name('portal.invoice.mpesa.callback');

// Public, signed, no auth — reached from the unsubscribe link in a
// customer newsletter email. A customer who never logs into the portal
// must still be able to opt out with one click.
Route::get('/portal/newsletters/unsubscribe/{customer}', [\App\Http\Controllers\CustomerNewsletterController::class, 'unsubscribeConfirm'])
    ->middleware('signed')->name('portal.newsletters.unsubscribe');
// The GET above only shows a "Confirm" button. Email security scanners and link
// previewers open every link in a message; when opening the link itself did the
// unsubscribing, they silently opted customers out. The POST does the work.
Route::post('/portal/newsletters/unsubscribe/{customer}', [\App\Http\Controllers\CustomerNewsletterController::class, 'unsubscribe'])
    ->middleware(['signed', 'throttle:20,1,unsubscribe'])->name('portal.newsletters.unsubscribe.confirm');

// ── Public receipt (signed link sent to customers via WhatsApp/email) ─────────
Route::get('/receipt/{sale}', [SalesController::class, 'publicReceipt'])
    ->name('receipt.public')->middleware('signed');

// ── Public, read-only manager dashboard (owner-generated link, no login) ──────
Route::get('/view/{token}', [App\Http\Controllers\ManagerViewController::class, 'show'])
    ->name('manager.view');

// ── M-Pesa Daraja C2B Callbacks (public, no auth, CSRF-exempt, IP-restricted) ─
// NOTE: path deliberately avoids the word "mpesa" — Safaricom's C2B Register
// URL endpoint rejects any ValidationURL/ConfirmationURL containing it.
Route::middleware('safaricom')->prefix('api/payments')->name('mpesa.')->group(function () {
    Route::post('/c2b/validate', [MpesaController::class, 'c2bValidate'])->name('c2b.validate');
    Route::post('/c2b/confirm',  [MpesaController::class, 'c2bConfirm'])->name('c2b.confirm');
});

// ── Paystack (public webhook + authenticated callback) ──────────────────────
Route::post('/api/paystack/webhook', [PaystackController::class, 'webhook'])->name('paystack.webhook');
Route::get('/paystack/callback',     [PaystackController::class, 'callback'])->name('paystack.callback');

// ── Pesapal (public IPN + authenticated callback) ───────────────────────────
Route::post('/api/pesapal/ipn',  [PesapalController::class, 'ipn'])->name('pesapal.ipn');
Route::get('/pesapal/callback',  [PesapalController::class, 'callback'])->name('pesapal.callback');

// ── PWA Offline page ─────────────────────────────────────────────────────
Route::get('/offline', fn() => view('offline'))->name('offline');

// ── Sitemap — marketing pages + every public shop's products, rebuilt
// hourly (cached). Nothing generated one before this at all. ──────────────
Route::get('/sitemap.xml', [App\Http\Controllers\SitemapController::class, 'index'])->name('sitemap');

// ── Marketing / Public pages ───────────────────────────────────────────────
Route::get('/',        [MarketingController::class, 'home'])->name('home');
Route::get('/pricing', [MarketingController::class, 'pricing'])->name('pricing');
Route::get('/contact', [MarketingController::class, 'contact'])->name('contact');
// Sends a real email on every submission with no protection at all
// previously — same reasoning as every other public form in this app
// (see shop.reviews.store's route comment for why the explicit prefix
// matters).
Route::post('/contact',[MarketingController::class, 'sendContact'])
    ->middleware('throttle:5,60,contact-form')->name('contact.send');
Route::get('/privacy',    [MarketingController::class, 'privacy'])->name('privacy');
Route::get('/terms',      [MarketingController::class, 'terms'])->name('terms');
Route::get('/disclaimer', [MarketingController::class, 'disclaimer'])->name('disclaimer');
Route::get('/guide',      [MarketingController::class, 'guide'])->name('guide');

// "Try the demo": a private sample store per visitor (POST so crawlers never spawn one).
Route::post('/demo', [App\Http\Controllers\DemoController::class, 'start'])->middleware('throttle:4,60,demo-start')->name('demo.start');
Route::post('/demo/exit', [App\Http\Controllers\DemoController::class, 'exit'])->middleware('auth')->name('demo.exit');

// ── Guest Routes ───────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {

    Route::get('/login',  [LoginController::class, 'showForm'])->name('login');
    // No rate limiting existed anywhere in the app — this was the single
    // most exploitable gap found in a security pass: unlimited password
    // guesses against any account, at any speed. throttle:5,1 keys by IP
    // for guest requests (Laravel's default ThrottleRequests behavior).
    // Explicit third prefix argument so this doesn't pool with the other
    // guest auth forms below — the bare form keys only by IP+domain (see
    // shop.reviews.store's route comment for the full explanation), so
    // without a distinct prefix, failing login a few times would also
    // lock you out of registering or resetting your password.
    Route::post('/login', [LoginController::class, 'login'])->name('login.post')->middleware('throttle:5,1,login');

    Route::get('/register',  [RegisterController::class, 'showForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register'])->name('register.post')->middleware('throttle:5,1,register');

    // Google sign-in/sign-up — used from both the login and register pages.
    // The callback is throttled by IP like the other guest auth endpoints;
    // Socialite's own 'state' parameter (stored in session) already guards
    // against a forged/replayed callback.
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback')->middleware('throttle:10,1,google-callback');

    // Password reset
    Route::get('/forgot-password',  [ForgotPasswordController::class, 'showForm'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendLink'])->name('password.email')->middleware('throttle:5,1,forgot-password');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showForm'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->name('password.update')->middleware('throttle:5,1,reset-password');
});

// ── Post-Google-signup onboarding (auth required, no organization yet) ─────
Route::middleware('auth')->group(function () {
    Route::get('/onboarding/business',  [OnboardingController::class, 'showBusinessForm'])->name('onboarding.business');
    Route::post('/onboarding/business', [OnboardingController::class, 'storeBusiness'])->name('onboarding.business.store')->middleware('throttle:5,1,onboarding');
});

// ── 2FA Challenge (auth required, no 2fa middleware — this IS the 2fa step) ──
Route::middleware('auth')->group(function () {
    Route::get('/2fa/challenge',  [TwoFactorController::class, 'challenge'])->name('2fa.challenge');
    // Authenticated at this point, so Laravel's default throttle keys by
    // user id instead of IP — locks out per-account guessing regardless of
    // how many users share a network/IP. A 6-digit TOTP code is only ~1M
    // possibilities; 5/min makes brute-forcing it within any valid code
    // window computationally pointless. Own prefix so verifying a login
    // code doesn't share a pool with confirming/disabling 2FA in Settings
    // (same bucket-sharing risk as every other bare throttle in this app).
    Route::post('/2fa/verify',    [TwoFactorController::class, 'verify'])->name('2fa.verify')->middleware('throttle:5,1,2fa-verify');

    // Logout must be outside 2FA middleware so users can always log out
    Route::get('/logout',  [LogoutController::class, 'logout']);
    Route::post('/logout', [LogoutController::class, 'logout'])->name('logout');
});

// ── Auth-only (no subscription gate) ──────────────────────────────────────
Route::middleware(['auth', '2fa'])->group(function () {

    // Subscription wall — shown when trial/subscription is expired
    Route::get('/subscription/required',
        fn () => view('subscription.required'))->name('subscription.required');

    // Subscription settings page — owners and managers only
    Route::get('/settings/subscription',
        [SettingsController::class, 'subscription'])
        ->middleware('role:owner,manager')
        ->name('settings.subscription');

    // Promo code preview — validates a code and returns the discounted price
    // without charging anything (owners/managers, same gate as the page itself)
    Route::post('/settings/subscription/apply-promo',
        [SettingsController::class, 'applyPromoCode'])
        ->middleware('role:owner,manager')
        ->name('settings.subscription.apply-promo');

    // Paystack subscription checkout — must work without an active subscription
    Route::post('/paystack/subscribe', [PaystackController::class, 'initialize'])
        ->middleware('role:owner')
        ->name('paystack.subscribe');

    // M-Pesa subscription payment — must work without an active subscription
    Route::prefix('api/mpesa')->name('mpesa.')->group(function () {
        Route::post('/subscribe',
            [MpesaController::class, 'subscribe'])->name('subscribe');
        Route::get('/subscription-status',
            [MpesaController::class, 'subscriptionStatus'])->name('subscription.status');
    });
});

// ── Organization Routes (owners only) ─────────────────────────────────────
Route::middleware(['auth', '2fa', 'owner', 'store.limit'])->prefix('org')->name('org.')->group(function () {
    Route::get('/',                      [OrganizationController::class, 'dashboard'])->name('dashboard');
    Route::get('/stores',                [OrganizationController::class, 'stores'])->name('stores');
    Route::get('/stores/create',         [OrganizationController::class, 'createStore'])->name('stores.create');
    Route::post('/stores',               [OrganizationController::class, 'storeCreate'])->name('stores.store');
    Route::get('/stores/{business}/edit',[OrganizationController::class, 'editStore'])->name('stores.edit');
    Route::put('/stores/{business}',     [OrganizationController::class, 'updateStore'])->name('stores.update');
    Route::post('/switch/{business}',    [OrganizationController::class, 'switchStore'])->name('switch');

    // Mandatory picker shown when the org is over its plan's store limit —
    // see EnforceStoreSelection middleware / Organization::hasUnresolvedStoreOverage().
    Route::get('/stores/select-active',  [OrganizationController::class, 'selectActiveStoresForm'])->name('stores.select-active');
    Route::post('/stores/select-active', [OrganizationController::class, 'selectActiveStores'])->name('stores.select-active.update');
});

// ── Inter-store Stock Transfers ─────────────────────────────────────────────
// Owners and overall managers move stock between any branches. Branch managers
// may operate transfers involving only their own branch (enforced in the
// controller). URLs/names stay org.transfers.* for backward compatibility.
Route::middleware(['auth', '2fa', 'role:owner,manager'])
    ->prefix('org/transfers')->name('org.transfers.')->group(function () {
        Route::get('/',                           [StockTransferController::class, 'index'])->name('index');
        Route::get('/create',                     [StockTransferController::class, 'create'])->name('create');
        Route::post('/',                          [StockTransferController::class, 'store'])->name('store');
        Route::get('/{stockTransfer}',            [StockTransferController::class, 'show'])->name('show');
        Route::patch('/{stockTransfer}/approve',  [StockTransferController::class, 'approve'])->name('approve');
        Route::patch('/{stockTransfer}/dispatch', [StockTransferController::class, 'dispatch'])->name('dispatch');
        Route::patch('/{stockTransfer}/receive',  [StockTransferController::class, 'receive'])->name('receive');
        Route::patch('/{stockTransfer}/cancel',   [StockTransferController::class, 'cancel'])->name('cancel');
    });

// ── Subscription-gated Routes ──────────────────────────────────────────────
Route::middleware(['auth', '2fa', 'subscribed', 'store.limit'])->group(function () {

    // Home menu — colorful tile "app launcher" every non-staff store role
    // lands on right after login (see User::postLoginRoute() — staff skip
    // this and go straight to their own portal dashboard, since every
    // top-level tile except Dashboard is off-limits to them anyway).
    // Gated per-tile to exactly what that role can access, same idea as
    // the sidebar itself. Named 'menu', NOT 'home' — the public marketing
    // landing page (routes/web.php's very first routes, path '/') already
    // owns route('home'); reusing it here would silently break every
    // route('home') link on the marketing site.
    Route::get('/home', [HomeController::class, 'index'])->name('menu');

    // Dashboard — all store roles
    Route::get('/dashboard',
        [DashboardController::class, 'index'])->name('dashboard');

    // Dashboard chart AJAX — date-range picker, compare-to-previous-period,
    // and the live KPI-card poll. All store roles (same as the dashboard itself).
    Route::get('/dashboard/chart-data',   [DashboardController::class, 'chartData'])->name('dashboard.chart-data');
    Route::get('/dashboard/kpi-snapshot', [DashboardController::class, 'kpiSnapshot'])->name('dashboard.kpi-snapshot');

    // ── Inventory — all routes (explicit paths before wildcard) ──────────────
    Route::prefix('inventory')->name('inventory.')
        ->middleware('role:owner,overall_manager,manager,cashier') // staff have no inventory access
        ->group(function () {

        // Read — owner, overall manager, manager, cashier
        Route::get('/', [InventoryController::class, 'index'])->name('index');

        // Write — owner + manager only
        Route::middleware('role:owner,manager')->group(function () {
            Route::get('/export',          [InventoryController::class, 'export'])->name('export');
            Route::post('/bulk-action',    [InventoryController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/import',          [InventoryController::class, 'importForm'])->name('import');
            Route::post('/import',         [InventoryController::class, 'import'])->name('import.store');
            Route::get('/import/template', [InventoryController::class, 'importTemplate'])->name('import.template');
            Route::get('/create',          [InventoryController::class, 'create'])->name('create');
            Route::post('/',               [InventoryController::class, 'store'])->name('store');

            // Stock Adjustments
            Route::get('/adjustments',        [StockAdjustmentController::class, 'index'])->name('adjustments');
            Route::get('/adjustments/create', [StockAdjustmentController::class, 'create'])->name('adjustments.create');
            Route::post('/adjustments',       [StockAdjustmentController::class, 'store'])->name('adjustments.store');

            // Categories
            Route::get('/categories/all',           [InventoryController::class, 'categories'])->name('categories');
            Route::post('/categories',              [InventoryController::class, 'storeCategory'])->name('categories.store');
            Route::put('/categories/{category}',    [InventoryController::class, 'updateCategory'])->name('categories.update');
            Route::delete('/categories/{category}', [InventoryController::class, 'destroyCategory'])->name('categories.destroy');

            // Product edit / delete (wildcards last)
            Route::get('/{product}/edit', [InventoryController::class, 'edit'])->whereNumber('product')->name('edit');
            Route::put('/{product}',      [InventoryController::class, 'update'])->whereNumber('product')->name('update');
            Route::delete('/{product}',   [InventoryController::class, 'destroy'])->whereNumber('product')->name('destroy');
            Route::delete('/{product}/images/{image}', [InventoryController::class, 'destroyImage'])->whereNumber('product')->whereNumber('image')->name('images.destroy');
        });

        // Read wildcard — must be last so it doesn't swallow explicit paths above
        Route::get('/{product}', [InventoryController::class, 'show'])->whereNumber('product')->name('show');
    });

    // ── Sales — create + view (owner, manager, cashier; overall manager view-only) ──
    Route::prefix('sales')->name('sales.')
        ->middleware('role:owner,overall_manager,manager,cashier') // staff cannot sell
        ->group(function () {
        // POS create/store also block overall managers in the controller (they
        // inherit 'owner' via RequireRole, so a role: list can't exclude them).
        Route::get('/create',              [SalesController::class, 'create'])->name('create');
        Route::post('/',                   [SalesController::class, 'store'])->name('store');
        Route::get('/{sale}/payment',      [SalesController::class, 'payment'])->whereNumber('sale')->name('payment');
        Route::post('/{sale}/payment',     [SalesController::class, 'recordPayment'])->whereNumber('sale')->name('record-payment');
        Route::get('/{sale}',              [SalesController::class, 'show'])->whereNumber('sale')->name('show');

        // Print/PDF invoice — Business+ only
        Route::get('/{sale}/invoice',
            [SalesController::class, 'invoice'])
            ->whereNumber('sale')
            ->middleware('feature:pdf_invoices')
            ->name('invoice');
        Route::get('/{sale}/invoice/pdf',
            [SalesController::class, 'downloadPdf'])
            ->whereNumber('sale')
            ->middleware('feature:pdf_invoices')
            ->name('invoice.pdf');
    });

    // ── Sales — management (owner + manager only) ──────────────────────────
    Route::prefix('sales')->name('sales.')
        ->middleware('role:owner,manager')
        ->group(function () {
        Route::get('/',                    [SalesController::class, 'index'])->name('index');
        Route::get('/export',              [SalesController::class, 'export'])->name('export');
        Route::post('/{sale}/email',       [SalesController::class, 'emailInvoice'])->name('email-invoice');
        Route::patch('/{sale}/cancel',     [SalesController::class, 'cancel'])->name('cancel');
    });

    // ── Void requests — propose (cashier+) vs review (owner/manager only) ──
    Route::prefix('sales/{sale}/void-request')->name('sales.void-request.')
        ->middleware('role:owner,overall_manager,manager,cashier')
        ->group(function () {
        Route::post('/', [\App\Http\Controllers\VoidRequestController::class, 'store'])->name('store');
    });
    Route::prefix('void-requests')->name('void-requests.')
        ->middleware('role:owner,manager')
        ->group(function () {
        Route::get('/',                     [\App\Http\Controllers\VoidRequestController::class, 'index'])->name('index');
        Route::patch('/{voidRequest}/approve', [\App\Http\Controllers\VoidRequestController::class, 'approve'])->name('approve');
        Route::patch('/{voidRequest}/reject',  [\App\Http\Controllers\VoidRequestController::class, 'reject'])->name('reject');
    });

    // ── Cash Deposits (Digital Float) — owner + overall_manager + manager ──
    Route::prefix('cash-deposits')->name('cash-deposits.')
        ->middleware('role:owner,overall_manager,manager')
        ->group(function () {
        Route::get('/',  [\App\Http\Controllers\FloatAdjustmentController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\FloatAdjustmentController::class, 'store'])->name('store');
    });

    // ── Settings — void reasons (owner + overall_manager + manager) ────────
    Route::prefix('settings/void-reasons')->name('settings.void-reasons.')
        ->middleware('role:owner,overall_manager,manager')
        ->group(function () {
        Route::get('/',               [\App\Http\Controllers\VoidReasonController::class, 'index'])->name('index');
        Route::post('/',              [\App\Http\Controllers\VoidReasonController::class, 'store'])->name('store');
        Route::put('/{voidReason}',   [\App\Http\Controllers\VoidReasonController::class, 'update'])->name('update');
        Route::delete('/{voidReason}',[\App\Http\Controllers\VoidReasonController::class, 'destroy'])->name('destroy');
    });

    // ── Settings — tax rules (owner + overall_manager + manager) ───────────
    Route::prefix('settings/tax-rules')->name('settings.tax-rules.')
        ->middleware('role:owner,overall_manager,manager')
        ->group(function () {
        Route::get('/',              [\App\Http\Controllers\TaxRuleController::class, 'index'])->name('index');
        Route::post('/',             [\App\Http\Controllers\TaxRuleController::class, 'store'])->name('store');
        Route::put('/{taxRule}',     [\App\Http\Controllers\TaxRuleController::class, 'update'])->name('update');
        Route::delete('/{taxRule}',  [\App\Http\Controllers\TaxRuleController::class, 'destroy'])->name('destroy');
    });

    // ── Customers — read + create (all store roles) ───────────────────────
    Route::prefix('customers')->name('customers.')
        ->middleware(['feature:customers', 'role:owner,manager,cashier'])
        ->group(function () {
        Route::get('/',               [CustomerController::class, 'index'])->name('index');
        Route::get('/create',         [CustomerController::class, 'create'])->name('create');
        Route::post('/',              [CustomerController::class, 'store'])->name('store');
        // whereNumber() is load-bearing: this route is registered before the
        // second customers group below (which has /export), so an
        // unconstrained {customer} here was swallowing GET /customers/export
        // as show(customer: 'export') — a ModelNotFoundException/404 for
        // every role, same route-ordering bug found in the P9 routes.
        Route::get('/{customer}',     [CustomerController::class, 'show'])->whereNumber('customer')->name('show');
    });

    // ── Customers — management (owner + manager only) ─────────────────────
    Route::prefix('customers')->name('customers.')
        ->middleware(['feature:customers', 'role:owner,manager'])
        ->group(function () {
        Route::get('/export',                       [CustomerController::class, 'export'])->name('export');
        Route::get('/{customer}/statement',         [CustomerController::class, 'statement'])->whereNumber('customer')->name('statement');
        Route::get('/{customer}/statement/pdf',     [CustomerController::class, 'statementPdf'])->whereNumber('customer')->name('statement.pdf');
        Route::get('/{customer}/edit',              [CustomerController::class, 'edit'])->whereNumber('customer')->name('edit');
        Route::put('/{customer}',                   [CustomerController::class, 'update'])->whereNumber('customer')->name('update');
        Route::delete('/{customer}',                [CustomerController::class, 'destroy'])->whereNumber('customer')->name('destroy');
        Route::post('/{customer}/payment',          [CustomerController::class, 'recordPayment'])->whereNumber('customer')->name('payment');
        Route::post('/{customer}/portal-invite',    [CustomerPortalController::class, 'invite'])->whereNumber('customer')->name('portal-invite');
    });

    // ── Expenses — record + view own (cashier, manager, owner) ───────────
    Route::prefix('expenses')->name('expenses.')
        ->middleware(['feature:expenses', 'role:owner,manager,cashier'])
        ->group(function () {
        Route::get('/',        [ExpenseController::class, 'index'])->name('index');
        Route::get('/create',  [ExpenseController::class, 'create'])->name('create');
        Route::post('/',       [ExpenseController::class, 'store'])->name('store');
    });

    // ── Expenses — management (owner + manager only) ───────────────────
    Route::prefix('expenses')->name('expenses.')
        ->middleware(['feature:expenses', 'role:owner,manager'])
        ->group(function () {
        Route::get('/export',          [ExpenseController::class, 'export'])->name('export');
        Route::get('/{expense}/edit',  [ExpenseController::class, 'edit'])->name('edit');
        Route::put('/{expense}',       [ExpenseController::class, 'update'])->name('update');
        Route::delete('/{expense}',    [ExpenseController::class, 'destroy'])->name('destroy');
        Route::post('/categories',                    [ExpenseController::class, 'storeCategory'])->name('categories.store');
        Route::delete('/categories/{expenseCategory}',[ExpenseController::class, 'destroyCategory'])->name('categories.destroy');
    });

    // ── Reports — owner + overall_manager + manager only ──────────────────
    Route::prefix('reports')->name('reports.')
        ->middleware('role:owner,overall_manager,manager')
        ->group(function () {

        Route::get('/', [ReportController::class, 'index'])->name('index');

        Route::get('/profit-loss',
            [ReportController::class, 'profitLoss'])
            ->middleware('feature:reports_advanced')
            ->name('profit_loss');

        Route::get('/expenses',
            [ReportController::class, 'expensesReport'])
            ->middleware('feature:reports_advanced')
            ->name('expenses');

        Route::get('/export', [ReportController::class, 'export'])
            ->middleware('feature:data_export')
            ->name('export');

        Route::get('/aged-debtors',
            [ReportController::class, 'agedDebtors'])
            ->name('aged-debtors');

        Route::get('/balance-sheet',
            [ReportController::class, 'balanceSheet'])
            ->middleware('feature:reports_advanced')
            ->name('balance-sheet');

        Route::get('/cash-flow',
            [ReportController::class, 'cashFlow'])
            ->middleware('feature:reports_advanced')
            ->name('cash-flow');

        Route::get('/cash-reconciliation',
            [ReportController::class, 'cashReconciliation'])
            ->name('cash-reconciliation');

        Route::get('/dead-stock',
            [ReportController::class, 'deadStock'])
            ->name('dead-stock');

        Route::get('/gross-margin',
            [ReportController::class, 'grossMargin'])
            ->middleware('feature:reports_advanced')
            ->name('gross-margin');

        Route::get('/vat-return',
            [ReportController::class, 'vatReturn'])
            ->name('vat-return');

        Route::get('/staff-performance',
            [ReportController::class, 'staffPerformance'])
            ->name('staff-performance');

        Route::get('/sales-forecast',
            [ReportController::class, 'salesForecast'])
            ->name('sales-forecast');
    });

    // ── Settings — password + 2FA (all store roles) ────────────────────────
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/password',  [SettingsController::class, 'password'])->name('password');
        Route::put('/password',  [SettingsController::class, 'updatePassword'])->name('password.update');

        Route::get('/2fa',          [TwoFactorController::class, 'setup'])->name('2fa.setup');
        // Own prefixes, same reasoning as 2fa.verify above — otherwise
        // confirming setup and disabling 2FA would pool with each other
        // (and with verifying a login code) under one shared user-keyed bucket.
        Route::post('/2fa/confirm', [TwoFactorController::class, 'confirm'])->name('2fa.confirm')->middleware('throttle:5,1,2fa-confirm');
        Route::post('/2fa/disable', [TwoFactorController::class, 'disable'])->name('2fa.disable')->middleware('throttle:5,1,2fa-disable');
        Route::post('/2fa/recovery-codes/regenerate', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('2fa.recovery-codes.regenerate')->middleware('throttle:5,1,2fa-recovery-regenerate');
    });

    // ── Settings — owner only ──────────────────────────────────────────────
    Route::prefix('settings')->name('settings.')
        ->middleware('role:owner')
        ->group(function () {

        Route::get('/', [SettingsController::class, 'index'])->name('index');

        Route::get('/business',  [SettingsController::class, 'business'])->name('business');
        Route::put('/business',  [SettingsController::class, 'updateBusiness'])
            ->middleware('sudo')->name('business.update');

        Route::get('/mpesa',  [SettingsController::class, 'mpesa'])
            ->middleware('feature:mpesa_sales')->name('mpesa');
        Route::put('/mpesa',  [SettingsController::class, 'updateMpesa'])
            ->middleware(['feature:mpesa_sales', 'sudo'])->name('mpesa.update');
        Route::post('/mpesa/register-c2b', [MpesaController::class, 'registerC2B'])
            ->middleware(['feature:mpesa_sales', 'sudo'])->name('mpesa.register-c2b');
        Route::delete('/mpesa', [SettingsController::class, 'disconnectMpesa'])
            ->middleware(['feature:mpesa_sales', 'sudo'])->name('mpesa.disconnect');

        Route::get('/pesapal',  [SettingsController::class, 'pesapal'])->name('pesapal');
        Route::put('/pesapal',  [SettingsController::class, 'updatePesapal'])
            ->middleware('sudo')->name('pesapal.update');

        Route::get('/sms',  [SettingsController::class, 'sms'])
            ->middleware('feature:sms_notifications')->name('sms');
        Route::put('/sms',  [SettingsController::class, 'updateSms'])
            ->middleware(['feature:sms_notifications', 'sudo'])->name('sms.update');
        Route::post('/sms/test', [SettingsController::class, 'testSms'])
            ->middleware('feature:sms_notifications')->name('sms.test');

        Route::get('/api',  [SettingsController::class, 'api'])->name('api');
        Route::post('/api/token/generate', [SettingsController::class, 'generateApiToken'])
            ->middleware(['feature:api_access', 'sudo'])->name('api.generate');
        Route::delete('/api/token', [SettingsController::class, 'revokeApiToken'])
            ->middleware(['feature:api_access', 'sudo'])->name('api.revoke');

        // ── Custom sending domain (campaign email branding) ─────────────────
        // Lets a business verify their own domain so campaign email arrives
        // as "promo@theirbusiness.com" instead of the shared platform
        // address. 'sudo' on the write actions matches every other action
        // here that creates/rotates a credential (mpesa/sms/api above).
        Route::get('/email-domain', [DomainSettingsController::class, 'show'])->name('email-domain');
        Route::post('/email-domain', [DomainSettingsController::class, 'store'])
            ->middleware('sudo')->name('email-domain.store');
        Route::post('/email-domain/verify', [DomainSettingsController::class, 'verify'])
            ->middleware('sudo')->name('email-domain.verify');
        Route::delete('/email-domain', [DomainSettingsController::class, 'destroy'])
            ->middleware('sudo')->name('email-domain.destroy');

        // ── Manager Dashboard link (read-only, no login, no plan gate) ──────
        Route::get('/dashboard-link',  [SettingsController::class, 'dashboardLink'])->name('dashboard-link');
        Route::post('/dashboard-link/generate', [SettingsController::class, 'generateDashboardToken'])
            ->middleware('sudo')->name('dashboard-link.generate');
        Route::delete('/dashboard-link', [SettingsController::class, 'revokeDashboardToken'])
            ->middleware('sudo')->name('dashboard-link.revoke');

        Route::get('/vat',  [SettingsController::class, 'vat'])->name('vat');
        Route::post('/vat', [SettingsController::class, 'updateVat'])->name('vat.update');

        Route::get('/cash-float',  [SettingsController::class, 'cashFloat'])->name('cash-float');
        Route::post('/cash-float', [SettingsController::class, 'updateCashFloat'])->name('cash-float.update');

        Route::get('/etims',  [SettingsController::class, 'etims'])->name('etims');
        Route::post('/etims', [SettingsController::class, 'updateEtims'])->name('etims.update');
        Route::post('/etims/activate', [SettingsController::class, 'activateEtims'])->name('etims.activate');

        // Online Store (store_slug/store_public) — SettingsController::store()/
        // updateStore() have existed with zero route pointing to either one.
        // The view itself calls route('settings.store.update') while
        // rendering its <form action>, so the page has never even been
        // viewable — it 500s on load, not just on submit. This is the same
        // toggle the online-order-management feature (built earlier this
        // session) depends on a business having actually turned on; no
        // business could ever have reached it through the normal UI.
        Route::get('/store',  [SettingsController::class, 'store'])->name('store');
        Route::put('/store',  [SettingsController::class, 'updateStore'])->name('store.update');
        // The shop's QR code (image) and a printable poster / sign / sticker.
        Route::get('/store/qr',     [SettingsController::class, 'storeQr'])->name('store.qr');
        Route::get('/store/poster', [SettingsController::class, 'storePoster'])->name('store.poster');
    });

    // ── Settings — team management (owner + overall_manager + manager) ─────
    // NOT gated by feature:team_management (that flag is unused everywhere
    // else and was blocking Solo — which genuinely allows up to 3 team
    // members — from ever reaching this page at all). View/edit/remove must
    // always be reachable regardless of plan, since removing people is the
    // only way an over-limit organisation (e.g. after downgrading) can get
    // back under its new limit. Invite creation is still correctly capped —
    // Organization::canAddUser() inside storeMember() enforces the real,
    // per-plan, organisation-wide limit.
    Route::prefix('settings')->name('settings.')
        ->middleware(['role:owner,overall_manager,manager'])
        ->group(function () {
        Route::get('/team',                  [SettingsController::class, 'team'])->name('team');
        Route::post('/team',                 [SettingsController::class, 'storeMember'])
            ->middleware('sudo')->name('team.store');
        Route::get('/team/{user}/edit',      [SettingsController::class, 'editMember'])->name('team.edit');
        Route::put('/team/{user}',           [SettingsController::class, 'updateMember'])
            ->middleware('sudo')->name('team.update');
        Route::patch('/team/{user}/toggle',  [SettingsController::class, 'toggleMember'])
            ->middleware('sudo')->name('team.toggle');
        Route::delete('/team/{user}',        [SettingsController::class, 'destroyMember'])
            ->middleware('sudo')->name('team.destroy');
        Route::post('/team/{user}/reset-2fa', [SettingsController::class, 'resetMemberTwoFactor'])
            ->middleware('sudo')->name('team.reset-2fa');
    });

    // ── Memos — broadcast announcements to staff (owner + overall_manager +
    // manager) ─────────────────────────────────────────────────────────────
    Route::prefix('settings/memos')->name('settings.memos.')
        ->middleware('role:owner,overall_manager,manager')
        ->group(function () {
        Route::get('/',  [\App\Http\Controllers\MemoController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\MemoController::class, 'store'])->name('store');
    });

    // ── Newsletters — marketing emails a store/org sends to its own
    // customers (distinct from Memo, which is staff-only/in-app-only) ──────
    Route::prefix('settings/newsletters')->name('settings.newsletters.')
        ->middleware('role:owner,overall_manager,manager')
        ->group(function () {
        Route::get('/',  [\App\Http\Controllers\CustomerNewsletterController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\CustomerNewsletterController::class, 'store'])->name('store');
        // Removes only the send-history record, never the emails already delivered — owner-only since
        // it's erasing what was actually sent to real customers, not just an unsent draft.
        Route::delete('/{newsletter}', [\App\Http\Controllers\CustomerNewsletterController::class, 'destroy'])
            ->middleware('owner')->name('destroy');
    });

    // ── Settings — payroll config (owner + overall_manager + manager) ──────
    Route::prefix('settings')->name('settings.')
        ->middleware(['feature:payroll', 'role:owner,overall_manager,manager'])
        ->group(function () {
        Route::get('/payroll',  [SettingsController::class, 'payroll'])->name('payroll');
        Route::post('/payroll', [SettingsController::class, 'updatePayroll'])->name('payroll.update');
    });

    // ── Settings — leave types (owner + overall_manager + manager) ─────────
    Route::prefix('settings/leave-types')->name('settings.leave-types.')
        ->middleware('role:owner,overall_manager,manager')
        ->group(function () {
        Route::get('/',               [\App\Http\Controllers\LeaveTypeController::class, 'index'])->name('index');
        Route::post('/',              [\App\Http\Controllers\LeaveTypeController::class, 'store'])->name('store');
        Route::put('/{leaveType}',    [\App\Http\Controllers\LeaveTypeController::class, 'update'])->name('update');
        Route::delete('/{leaveType}', [\App\Http\Controllers\LeaveTypeController::class, 'destroy'])->name('destroy');
    });

    // ── Quotes — owner + manager only ─────────────────────────────────────
    Route::prefix('quotes')->name('quotes.')
        ->middleware(['feature:quotes', 'role:owner,overall_manager,manager'])
        ->group(function () {
        Route::get('/',                           [QuoteController::class, 'index'])->name('index');
        Route::get('/create',                     [QuoteController::class, 'create'])->name('create');
        Route::post('/',                          [QuoteController::class, 'store'])->name('store');
        Route::get('/{quote}',                    [QuoteController::class, 'show'])->name('show');
        Route::get('/{quote}/edit',               [QuoteController::class, 'edit'])->name('edit');
        Route::put('/{quote}',                    [QuoteController::class, 'update'])->name('update');
        Route::delete('/{quote}',                 [QuoteController::class, 'destroy'])->name('destroy');
        Route::patch('/{quote}/mark-sent',        [QuoteController::class, 'markSent'])->name('mark-sent');
        Route::patch('/{quote}/mark-accepted',    [QuoteController::class, 'markAccepted'])->name('mark-accepted');
        Route::patch('/{quote}/mark-rejected',    [QuoteController::class, 'markRejected'])->name('mark-rejected');
        Route::post('/{quote}/convert',           [QuoteController::class, 'convertToSale'])->name('convert');
        Route::get('/{quote}/pdf',                [QuoteController::class, 'pdf'])->name('pdf');
        Route::post('/{quote}/email',             [QuoteController::class, 'email'])->name('email');
    });

    // ── Suppliers — owner + manager only ──────────────────────────────────
    Route::prefix('suppliers')->name('suppliers.')
        ->middleware(['feature:suppliers', 'role:owner,manager'])
        ->group(function () {
        Route::get('/',               [SupplierController::class, 'index'])->name('index');
        Route::get('/create',         [SupplierController::class, 'create'])->name('create');
        Route::post('/',              [SupplierController::class, 'store'])->name('store');
        Route::get('/{supplier}',          [SupplierController::class, 'show'])->name('show');
        Route::get('/{supplier}/edit',     [SupplierController::class, 'edit'])->name('edit');
        Route::put('/{supplier}',          [SupplierController::class, 'update'])->name('update');
        Route::delete('/{supplier}',       [SupplierController::class, 'destroy'])->name('destroy');
        Route::get('/{supplier}/payments', [SupplierPaymentController::class, 'index'])->name('payments');
        Route::post('/{supplier}/payments',[SupplierPaymentController::class, 'store'])->name('payments.store');
        Route::post('/{supplier}/bill',    [SupplierPaymentController::class, 'bill'])->name('bill');
    });

    // ── Purchase Orders — owner + manager only ─────────────────────────────
    Route::prefix('purchases')->name('purchases.')
        ->middleware(['feature:suppliers', 'role:owner,manager'])
        ->group(function () {
        Route::get('/',                          [PurchaseOrderController::class, 'index'])->name('index');
        Route::get('/create',                    [PurchaseOrderController::class, 'create'])->name('create');
        Route::post('/',                         [PurchaseOrderController::class, 'store'])->name('store');
        Route::get('/{purchaseOrder}',           [PurchaseOrderController::class, 'show'])->name('show');
        Route::post('/{purchaseOrder}/receive',  [PurchaseOrderController::class, 'receive'])->name('receive');
        Route::post('/{purchaseOrder}/payment',  [PurchaseOrderController::class, 'recordPayment'])->name('payment');
        Route::patch('/{purchaseOrder}/cancel',  [PurchaseOrderController::class, 'cancel'])->name('cancel');
    });

    // ── Recurring Invoices — owner + manager only ──────────────────────────
    Route::prefix('recurring')->name('recurring.')
        ->middleware(['feature:recurring_invoices', 'role:owner,manager'])
        ->group(function () {
        Route::get('/',                               [RecurringInvoiceController::class, 'index'])->name('index');
        Route::get('/create',                         [RecurringInvoiceController::class, 'create'])->name('create');
        Route::post('/',                              [RecurringInvoiceController::class, 'store'])->name('store');
        Route::get('/{recurringInvoice}',             [RecurringInvoiceController::class, 'show'])->name('show');
        Route::get('/{recurringInvoice}/edit',        [RecurringInvoiceController::class, 'edit'])->name('edit');
        Route::put('/{recurringInvoice}',             [RecurringInvoiceController::class, 'update'])->name('update');
        Route::delete('/{recurringInvoice}',          [RecurringInvoiceController::class, 'destroy'])->name('destroy');
        Route::patch('/{recurringInvoice}/toggle',    [RecurringInvoiceController::class, 'toggle'])->name('toggle');
        Route::post('/{recurringInvoice}/run-now',    [RecurringInvoiceController::class, 'runNow'])->name('run-now');
    });

    // ── Sale Returns — owner + manager only ───────────────────────────────────
    Route::prefix('sales/returns')->name('returns.')
        ->middleware('role:owner,manager')
        ->group(function () {
        Route::get('/',               [SaleReturnController::class, 'index'])->name('index');
        Route::get('/create',         [SaleReturnController::class, 'create'])->name('create');
        Route::post('/',              [SaleReturnController::class, 'store'])->name('store');
        Route::get('/{saleReturn}',   [SaleReturnController::class, 'show'])->name('show');
    });

    // ── Shifts — owner + manager only ──────────────────────────────────────
    Route::prefix('shifts')->name('shifts.')
        ->middleware('role:owner,manager')
        ->group(function () {
        Route::get('/',                     [ShiftController::class, 'index'])->name('index');
        Route::get('/open',                 [ShiftController::class, 'open'])->name('open');
        Route::post('/open',                [ShiftController::class, 'openStore'])->name('open.store');
        Route::get('/{shift}',              [ShiftController::class, 'show'])->name('show');
        Route::get('/{shift}/close',        [ShiftController::class, 'close'])->name('close');
        Route::post('/{shift}/close',       [ShiftController::class, 'closeStore'])->name('close.store');
    });

    // ── Stock Receives — owner + manager only ──────────────────────────────
    Route::prefix('inventory/receives')->name('receives.')
        ->middleware('role:owner,manager')
        ->group(function () {
        Route::get('/',                   [StockReceiveController::class, 'index'])->name('index');
        Route::get('/create',             [StockReceiveController::class, 'create'])->name('create');
        Route::post('/',                  [StockReceiveController::class, 'store'])->name('store');
        Route::get('/{stockReceive}',     [StockReceiveController::class, 'show'])->name('show');
    });

    // ── Invoices — owner + manager only ────────────────────────────────────
    Route::prefix('invoices')->name('invoices.')
        ->middleware('role:owner,manager')
        ->group(function () {
        Route::get('/',                               [InvoiceController::class, 'index'])->name('index');
        Route::get('/create',                         [InvoiceController::class, 'create'])->name('create');
        Route::post('/',                              [InvoiceController::class, 'store'])->name('store');
        Route::get('/{invoice}',                      [InvoiceController::class, 'show'])->name('show');
        Route::get('/{invoice}/edit',                 [InvoiceController::class, 'edit'])->name('edit');
        Route::put('/{invoice}',                      [InvoiceController::class, 'update'])->name('update');
        Route::delete('/{invoice}',                   [InvoiceController::class, 'destroy'])->name('destroy');
        Route::patch('/{invoice}/send',               [InvoiceController::class, 'send'])->name('send');
        Route::post('/{invoice}/payments',            [InvoiceController::class, 'recordPayment'])->name('payments.store');
        Route::get('/{invoice}/pdf',                  [InvoiceController::class, 'pdf'])->name('pdf');
    });

    // ── Customer Credits — owner + manager only ─────────────────────────────
    Route::prefix('customers/{customer}/credits')->name('customer.credits.')
        ->middleware(['feature:customers', 'role:owner,manager'])
        ->group(function () {
        Route::get('/',         [CustomerCreditController::class, 'index'])->name('index');
        Route::post('/',        [CustomerCreditController::class, 'store'])->name('store');
        Route::patch('/limit',  [CustomerCreditController::class, 'adjustLimit'])->name('limit');
    });

    // Audit Log now lives in routes/features_ux.php as 'audit-log.index' —
    // this was a dead duplicate registration under the old name
    // 'audit.index', which the audit/index.blade.php view was still using,
    // crashing the page with RouteNotFoundException on every single load.

    // ── VAT Settings ────────────────────────────────────────────────────────
    // (inside settings prefix group, added as standalone for clarity)

    // ── M-Pesa sale payments — Business+ only ─────────────────────────────
    Route::prefix('api/mpesa')->name('mpesa.')
        ->middleware('feature:mpesa_sales')
        ->group(function () {
        Route::post('/initiate', [MpesaController::class, 'initiate'])->name('initiate');
        Route::get('/status',    [MpesaController::class, 'status'])->name('status');
        Route::post('/qr',       [MpesaController::class, 'generateQr'])->name('qr');
    });

    // ── Pesapal card payments (optional per-business) ──────────────────────
    Route::prefix('api/pesapal')->name('pesapal.')->group(function () {
        Route::post('/initiate',     [PesapalController::class, 'initiate'])->name('initiate');
        Route::get('/status',        [PesapalController::class, 'status'])->name('status');
        Route::post('/register-ipn', [PesapalController::class, 'registerIpn'])->name('register-ipn');
    });

    // ── Staff / HR — owner + manager (view); owner-only write ─────────────
    Route::prefix('staff')->name('staff.')
        ->middleware(['feature:payroll', 'role:owner,manager'])
        ->group(function () {
        Route::get('/',         [StaffController::class, 'index'])->name('index');
        Route::get('/{user}',   [StaffController::class, 'show'])->whereNumber('user')->name('show');

        // Profile management — owner only
        Route::get('/{user}/profile',  [StaffController::class, 'editProfile'])
            ->whereNumber('user')->middleware('role:owner')->name('profile');
        Route::post('/{user}/profile', [StaffController::class, 'updateProfile'])
            ->whereNumber('user')->middleware('role:owner')->name('profile.update');
    });

    // ── Payroll — owner only (management); own payslip accessible to all ──
    Route::prefix('payroll')->name('payroll.')
        ->middleware('feature:payroll')
        ->group(function () {

        // My payslips — self-service list (any role)
        Route::get('/my-payslips', [PayrollController::class, 'myPayslips'])->name('payslips.mine');

        // Own payslip — all store roles can view their own
        Route::get('/{payrollPeriod}/items/{payrollItem}/payslip',
            [PayrollController::class, 'payslip'])->name('payslip');

        // P9 certificate — Growth+ only, owner only
        Route::get('/p9/{user}/{year}', [PayrollController::class, 'p9'])
            ->whereNumber('user')->whereNumber('year')
            ->middleware(['feature:p9_forms', 'role:owner'])
            ->name('p9');
    });

    // ── Payroll — management (owner only) ─────────────────────────────────
    Route::prefix('payroll')->name('payroll.')
        ->middleware(['feature:payroll', 'role:owner'])
        ->group(function () {
        Route::get('/',                                        [PayrollController::class, 'index'])->name('index');
        Route::get('/create',                                  [PayrollController::class, 'create'])->name('create');
        Route::post('/',                                       [PayrollController::class, 'store'])->name('store');
        // whereNumber() on {payrollPeriod}/{payrollItem} is load-bearing, not
        // cosmetic: this group is registered before routes/features_payroll.php
        // is require()'d (bottom of this file), and an unconstrained wildcard
        // here matches ANYTHING with the right segment count — so GET
        // /payroll/p9 was being swallowed by this show() route (id='p9'),
        // 404ing via a failed PayrollPeriod lookup, before the router ever
        // reached payroll.p9.index. The entire P9 Certificates feature was
        // unreachable for every role, owner included. Same root cause as the
        // VAT-return/audit-log route collisions found earlier this session,
        // just first-registration-wins instead of last-registration-wins.
        Route::get('/{payrollPeriod}',                         [PayrollController::class, 'show'])->whereNumber('payrollPeriod')->name('show');
        Route::post('/{payrollPeriod}/calculate',              [PayrollController::class, 'calculate'])->whereNumber('payrollPeriod')->name('calculate');
        Route::patch('/{payrollPeriod}/approve',               [PayrollController::class, 'approve'])->whereNumber('payrollPeriod')->name('approve');
        Route::patch('/{payrollPeriod}/mark-paid',                          [PayrollController::class, 'markPaid'])->whereNumber('payrollPeriod')->name('mark-paid');
        Route::patch('/{payrollPeriod}/items/{payrollItem}/pay',           [PayrollController::class, 'markItemPaid'])->whereNumber(['payrollPeriod', 'payrollItem'])->name('item-pay');
        Route::delete('/{payrollPeriod}',                                  [PayrollController::class, 'destroy'])->whereNumber('payrollPeriod')->name('destroy');
    });

    // ── Credit Notes ───────────────────────────────────────────────────────────
    Route::prefix('credit-notes')->name('credit-notes.')
        ->middleware('role:owner,manager')
        ->group(function () {
        Route::get('/',                          [CreditNoteController::class, 'index'])->name('index');
        Route::get('/create',                    [CreditNoteController::class, 'create'])->name('create');
        Route::post('/',                         [CreditNoteController::class, 'store'])->name('store');
        Route::get('/{creditNote}',              [CreditNoteController::class, 'show'])->name('show');
        Route::patch('/{creditNote}/issue',      [CreditNoteController::class, 'issue'])->name('issue');
        Route::get('/{creditNote}/pdf',          [CreditNoteController::class, 'pdf'])->name('pdf');
    });

    // ── Delivery Notes ─────────────────────────────────────────────────────────
    Route::prefix('delivery-notes')->name('delivery-notes.')
        ->middleware('role:owner,manager')
        ->group(function () {
        Route::get('/',                              [DeliveryNoteController::class, 'index'])->name('index');
        Route::get('/create',                        [DeliveryNoteController::class, 'create'])->name('create');
        Route::post('/',                             [DeliveryNoteController::class, 'store'])->name('store');
        Route::get('/{deliveryNote}',                [DeliveryNoteController::class, 'show'])->name('show');
        Route::patch('/{deliveryNote}/dispatch',     [DeliveryNoteController::class, 'dispatch'])->name('dispatch');
        Route::patch('/{deliveryNote}/deliver',      [DeliveryNoteController::class, 'deliver'])->name('deliver');
        Route::get('/{deliveryNote}/pdf',            [DeliveryNoteController::class, 'pdf'])->name('pdf');
    });

    // ── Petty Cash ─────────────────────────────────────────────────────────────
    Route::prefix('petty-cash')->name('petty-cash.')
        ->middleware('role:owner,manager')
        ->group(function () {
        Route::get('/',          [PettyCashController::class, 'index'])->name('index');
        Route::post('/topup',    [PettyCashController::class, 'topup'])->name('topup');
        Route::post('/disburse', [PettyCashController::class, 'disburse'])->name('disburse');
    });

    // ── eTIMS Resubmit ─────────────────────────────────────────────────────────
    Route::post('/etims/resubmit', [EtimsController::class, 'resubmit'])
        ->middleware('role:owner,manager')
        ->name('etims.resubmit');

    // ── Feature 1: Product Variants ────────────────────────────────────────────
    Route::prefix('inventory/{product}/variants')->name('inventory.variants.')
        ->middleware('role:owner,manager')
        ->group(function () {
        Route::get('/',            [\App\Http\Controllers\ProductVariantController::class, 'index'])->name('index');
        Route::post('/',           [\App\Http\Controllers\ProductVariantController::class, 'store'])->name('store');
        Route::patch('/{variant}', [\App\Http\Controllers\ProductVariantController::class, 'update'])->name('update');
        Route::delete('/{variant}',[\App\Http\Controllers\ProductVariantController::class, 'destroy'])->name('destroy');
    });

    // ── Feature 3: Product Batches ─────────────────────────────────────────────
    Route::prefix('inventory/{product}/batches')->name('inventory.batches.')
        ->middleware('role:owner,manager')
        ->group(function () {
        Route::get('/',           [\App\Http\Controllers\ProductBatchController::class, 'index'])->name('index');
        Route::post('/',          [\App\Http\Controllers\ProductBatchController::class, 'store'])->name('store');
        Route::delete('/{batch}', [\App\Http\Controllers\ProductBatchController::class, 'destroy'])->name('destroy');
    });

    // ── Feature 6: Serial Numbers ──────────────────────────────────────────────
    Route::prefix('inventory/{product}/serials')->name('inventory.serials.')
        ->middleware('role:owner,manager')
        ->group(function () {
        Route::get('/',           [\App\Http\Controllers\SerialNumberController::class, 'index'])->name('index');
        Route::post('/',          [\App\Http\Controllers\SerialNumberController::class, 'store'])->name('store');
        Route::patch('/{serial}', [\App\Http\Controllers\SerialNumberController::class, 'update'])->name('update');
    });

    // ── Feature 2: Discounts & Coupons ────────────────────────────────────────
    Route::middleware('role:owner,manager')->group(function () {
        Route::resource('discounts', \App\Http\Controllers\DiscountController::class)->except(['show']);
        Route::patch('discounts/{discount}/toggle', [\App\Http\Controllers\DiscountController::class, 'toggle'])->name('discounts.toggle');
        Route::post('sales/apply-discount', [\App\Http\Controllers\DiscountController::class, 'applyDiscount'])->name('sales.apply-discount');
        Route::get('sales/customer-loyalty/{customer}', [\App\Http\Controllers\SalesController::class, 'customerLoyalty'])->name('sales.customer-loyalty');

        Route::resource('coupons', \App\Http\Controllers\CouponController::class)->except(['show']);
        Route::post('coupons/validate', [\App\Http\Controllers\CouponController::class, 'validateCoupon'])->name('coupons.validate');
    });

    // ── Feature 4: Price Tiers ─────────────────────────────────────────────────
    Route::middleware('role:owner,manager')->group(function () {
        Route::resource('price-tiers', \App\Http\Controllers\PriceTierController::class)->except(['create','edit','show']);
        Route::patch('inventory/{product}/prices', [\App\Http\Controllers\PriceTierController::class, 'updateProductPrices'])->name('inventory.prices.update');
    });

    // ── Feature 5: Bundles ─────────────────────────────────────────────────────
    Route::resource('bundles', \App\Http\Controllers\ProductBundleController::class)
        ->except(['show'])
        ->middleware('role:owner,manager');

    // ── FEATURE: Thermal Receipt ───────────────────────────────────────────────
    Route::get('/sales/{sale}/receipt', [\App\Http\Controllers\SalesController::class, 'receipt'])->name('sales.receipt');
    Route::post('/sales/{sale}/send-receipt', [\App\Http\Controllers\SalesController::class, 'sendReceipt'])->name('sales.send-receipt');

    // ── FEATURE: Staff Leave Management ───────────────────────────────────────
    Route::prefix('staff/leave')->name('staff.leave.')->group(function () {
        Route::get('/',         [\App\Http\Controllers\LeaveController::class, 'index'])->name('index');
        Route::get('/create',   [\App\Http\Controllers\LeaveController::class, 'create'])->name('create');
        Route::post('/',        [\App\Http\Controllers\LeaveController::class, 'store'])->name('store');
        // Delete and cancel are also for the employee's own requests, so they sit
        // outside the manager-only group; the controller checks who may do what.
        Route::delete('/{leave}',         [\App\Http\Controllers\LeaveController::class, 'destroy'])->name('destroy');
        Route::patch('/{leave}/cancel',   [\App\Http\Controllers\LeaveController::class, 'cancel'])->name('cancel');
        // Approve and reject require manager or owner at the route level
        Route::middleware('role:owner,overall_manager,manager')->group(function () {
            Route::patch('/{leave}/approve',  [\App\Http\Controllers\LeaveController::class, 'approve'])->name('approve');
            Route::patch('/{leave}/reject',   [\App\Http\Controllers\LeaveController::class, 'reject'])->name('reject');
        });
    });

    // ── FEATURE: Attendance ────────────────────────────────────────────────────
    Route::prefix('staff/attendance')->name('staff.attendance.')->group(function () {
        Route::get('/',                              [\App\Http\Controllers\AttendanceController::class, 'index'])->name('index');
        Route::post('/',                             [\App\Http\Controllers\AttendanceController::class, 'store'])->name('store');
        Route::post('/clock-in',                     [\App\Http\Controllers\AttendanceController::class, 'clockIn'])->name('clock-in');
        Route::post('/clock-out',                    [\App\Http\Controllers\AttendanceController::class, 'clockOut'])->name('clock-out');
    });

    // ── FEATURE: Salary Advances ───────────────────────────────────────────────
    Route::prefix('staff/advances')->name('staff.advances.')->group(function () {
        Route::get('/',                              [\App\Http\Controllers\SalaryAdvanceController::class, 'index'])->name('index');
        Route::post('/',                             [\App\Http\Controllers\SalaryAdvanceController::class, 'store'])->name('store');
        Route::patch('/{advance}/approve',           [\App\Http\Controllers\SalaryAdvanceController::class, 'approve'])->name('approve');
        Route::patch('/{advance}/reject',            [\App\Http\Controllers\SalaryAdvanceController::class, 'reject'])->name('reject');
        Route::delete('/{advance}',                  [\App\Http\Controllers\SalaryAdvanceController::class, 'destroy'])->name('destroy');
    });

    // ── FEATURE: WhatsApp Settings ─────────────────────────────────────────────
    // Was reachable on every plan regardless of feature access, even though
    // this page's own copy says it "uses the same SMS credentials configured
    // under SMS settings" — and that SMS settings page is itself gated
    // behind feature:sms_notifications (Business+). A business without that
    // feature could open WhatsApp Settings, tick "Enable", and click "Send
    // Test" — but had no way to ever set a provider/API key, since the one
    // page that lets them do that was invisible in their nav. Gating this
    // identically to SMS so the dependency is enforced consistently.
    Route::middleware(['role:owner', 'feature:sms_notifications'])->group(function () {
        Route::get('/settings/whatsapp',             [\App\Http\Controllers\SettingsController::class, 'whatsapp'])->name('settings.whatsapp');
        Route::post('/settings/whatsapp',            [\App\Http\Controllers\SettingsController::class, 'updateWhatsapp'])->name('settings.whatsapp.update');
        Route::post('/settings/whatsapp/test',       [\App\Http\Controllers\SettingsController::class, 'testWhatsapp'])->name('settings.whatsapp.test');
    });

    // ── FEATURE: Loyalty Program ───────────────────────────────────────────────
    Route::middleware('role:owner')->group(function () {
        Route::get('/settings/loyalty',              [\App\Http\Controllers\SettingsController::class, 'loyalty'])->name('settings.loyalty');
        Route::post('/settings/loyalty',             [\App\Http\Controllers\SettingsController::class, 'updateLoyalty'])->name('settings.loyalty.update');
    });
    Route::get('/customers/{customer}/loyalty', [\App\Http\Controllers\LoyaltyController::class, 'customerPoints'])
        ->middleware(['feature:customers', 'role:owner,manager'])
        ->name('customers.loyalty');

    // ── FEATURE: Bank Reconciliation ──────────────────────────────────────────
    Route::middleware('role:owner,manager')->group(function () {
        Route::get('/bank-reconciliation',                               [\App\Http\Controllers\BankReconciliationController::class, 'index'])->name('bank-reconciliation.index');
        Route::post('/bank-reconciliation/accounts',                     [\App\Http\Controllers\BankReconciliationController::class, 'createAccount'])->name('bank-reconciliation.accounts');
        Route::post('/bank-reconciliation/{account}/import',             [\App\Http\Controllers\BankReconciliationController::class, 'import'])->name('bank-reconciliation.import');
        Route::get('/bank-reconciliation/{account}/lines',               [\App\Http\Controllers\BankReconciliationController::class, 'lines'])->name('bank-reconciliation.lines');
        Route::patch('/bank-reconciliation/lines/{line}/reconcile',      [\App\Http\Controllers\BankReconciliationController::class, 'reconcile'])->name('bank-reconciliation.reconcile');
    });

    // ── FEATURE: Loans ────────────────────────────────────────────────────────
    Route::prefix('loans')->name('loans.')->middleware('role:owner,manager')->group(function () {
        Route::get('/',                         [LoanController::class, 'index'])->name('index');
        Route::get('/create',                   [LoanController::class, 'create'])->name('create');
        Route::post('/',                        [LoanController::class, 'store'])->name('store');
        Route::get('/{loan}',                   [LoanController::class, 'show'])->name('show');
        Route::delete('/{loan}',                [LoanController::class, 'destroy'])->name('destroy');
        Route::post('/{loan}/repayment',        [LoanController::class, 'recordPayment'])->name('repayment');
    });

    // ── FEATURE: Assets ───────────────────────────────────────────────────────
    Route::prefix('assets')->name('assets.')->middleware('role:owner,manager')->group(function () {
        Route::get('/',                         [AssetController::class, 'index'])->name('index');
        Route::get('/create',                   [AssetController::class, 'create'])->name('create');
        Route::post('/',                        [AssetController::class, 'store'])->name('store');
        Route::get('/{asset}/edit',             [AssetController::class, 'edit'])->name('edit');
        Route::put('/{asset}',                  [AssetController::class, 'update'])->name('update');
        Route::delete('/{asset}',               [AssetController::class, 'destroy'])->name('destroy');
    });

    // ── FEATURE: Budgets ──────────────────────────────────────────────────────
    Route::prefix('budgets')->name('budgets.')->middleware('role:owner,manager')->group(function () {
        Route::get('/',                         [BudgetController::class, 'index'])->name('index');
        Route::post('/',                        [BudgetController::class, 'store'])->name('store');
        Route::delete('/{budget}',              [BudgetController::class, 'destroy'])->name('destroy');
    });

    // ── FEATURE: Appointments / Services ─────────────────────────────────────
    Route::prefix('services')->name('services.')->middleware('role:owner,manager,cashier')->group(function () {
        Route::get('/',                         [ServiceController::class, 'index'])->name('index');
        Route::get('/create',                   [ServiceController::class, 'create'])->name('create');
        Route::post('/',                        [ServiceController::class, 'store'])->name('store');
        Route::get('/{service}/edit',           [ServiceController::class, 'edit'])->name('edit');
        Route::put('/{service}',                [ServiceController::class, 'update'])->name('update');
        Route::delete('/{service}',             [ServiceController::class, 'destroy'])->name('destroy');
    });
    Route::prefix('appointments')->name('appointments.')->middleware('role:owner,manager,cashier,staff')->group(function () {
        Route::get('/',                         [AppointmentController::class, 'index'])->name('index');
        Route::get('/create',                   [AppointmentController::class, 'create'])->name('create');
        Route::post('/',                        [AppointmentController::class, 'store'])->name('store');
        // Was missing entirely — AppointmentController::show() exists and
        // store()/update() both redirect() here on success, so creating or
        // editing an appointment has been throwing RouteNotFoundException
        // immediately after the save actually succeeded.
        Route::get('/{appointment}',            [AppointmentController::class, 'show'])->name('show');
        Route::get('/{appointment}/edit',       [AppointmentController::class, 'edit'])->name('edit');
        Route::put('/{appointment}',            [AppointmentController::class, 'update'])->name('update');
        Route::delete('/{appointment}',         [AppointmentController::class, 'destroy'])->name('destroy');
        Route::patch('/{appointment}/status',   [AppointmentController::class, 'updateStatus'])->name('status');
    });

    // ── FEATURE: Table Management ─────────────────────────────────────────────
    Route::prefix('tables')->name('tables.')->middleware('role:owner,manager,cashier')->group(function () {
        // Printable bill (before payment) and kitchen slip
        Route::get('/orders/{order}/bill', [\App\Http\Controllers\TableController::class, 'printBill'])->name('orders.bill.print');
        Route::post('/orders/{order}/kitchen', [\App\Http\Controllers\TableController::class, 'sendToKitchen'])->name('orders.kitchen');
        Route::get('/orders/{order}/kitchen', [\App\Http\Controllers\TableController::class, 'printKitchen'])->name('orders.kitchen.print');
        Route::post('/orders/{order}/service-charge', [\App\Http\Controllers\TableController::class, 'setServiceCharge'])->name('orders.service-charge');
        Route::post('/orders/{order}/move', [\App\Http\Controllers\TableController::class, 'moveTable'])->name('orders.move');
        Route::post('/orders/{order}/merge', [\App\Http\Controllers\TableController::class, 'mergeTable'])->name('orders.merge');
        Route::post('/orders/{order}/items/{item}/discount', [\App\Http\Controllers\TableController::class, 'setItemDiscount'])->name('orders.items.discount');
        Route::get('/{table}/qr', [\App\Http\Controllers\TableController::class, 'qr'])->name('qr');
        Route::post('/{table}/requests/{tableOrderRequest}/approve', [\App\Http\Controllers\TableController::class, 'approveRequest'])->name('requests.approve');
        Route::post('/{table}/requests/{tableOrderRequest}/reject', [\App\Http\Controllers\TableController::class, 'rejectRequest'])->name('requests.reject');
        Route::post('/service-charge', [\App\Http\Controllers\TableController::class, 'setDefaultServiceCharge'])->middleware('role:owner,manager')->name('service-charge.default');
        Route::get('/',                                 [TableController::class, 'floor'])->name('floor');
        Route::get('/manage',                           [TableController::class, 'manage'])->name('manage');
        Route::post('/',                                [TableController::class, 'store'])->name('store');
        Route::delete('/{table}',                       [TableController::class, 'destroy'])->name('destroy');
        // Order routes (order-centric, not table-centric)
        Route::post('/{table}/open',                    [TableController::class, 'openOrder'])->name('open');
        Route::prefix('orders')->name('orders.')->group(function () {
            Route::get('/{order}',                      [TableController::class, 'showOrder'])->name('show');
            Route::post('/{order}/items',               [TableController::class, 'addItem'])->name('items.add');
            Route::delete('/{order}/items/{item}',      [TableController::class, 'removeItem'])->name('items.remove');
            Route::delete('/{order}/items',              [TableController::class, 'clearOrder'])->name('clear');
            Route::post('/{order}/pay',                 [TableController::class, 'pay'])->name('pay');
            Route::patch('/{order}/bill',               [TableController::class, 'bill'])->name('bill');
        });
    });

    // Staff Performance & Sales Forecast moved into the reports group above

    // ── SETTINGS: Store, Custom Fields, Customer Tags ─────────────────────────
    Route::prefix('settings/custom-fields')->name('settings.custom-fields.')->middleware('role:owner,manager')->group(function () {
        Route::get('/',             [CustomFieldController::class, 'index'])->name('index');
        Route::post('/',            [CustomFieldController::class, 'store'])->name('store');
        Route::put('/{customFieldDefinition}',    [CustomFieldController::class, 'update'])->name('update');
        Route::delete('/{customFieldDefinition}', [CustomFieldController::class, 'destroy'])->name('destroy');
    });
    Route::prefix('settings/customer-tags')->name('settings.customer-tags.')->middleware('role:owner,manager')->group(function () {
        Route::get('/',             [CustomerTagController::class, 'index'])->name('index');
        Route::post('/',            [CustomerTagController::class, 'store'])->name('store');
        Route::put('/{customerTag}',    [CustomerTagController::class, 'update'])->name('update');
        Route::delete('/{customerTag}', [CustomerTagController::class, 'destroy'])->name('destroy');
    });
});

// ── Customer Portal — separate auth ───────────────────────────────────────
Route::prefix('portal')->name('portal.')->group(function () {
    // Guest routes
    Route::middleware('guest:customer')->group(function () {
        Route::get('/login',          [CustomerPortalController::class, 'loginForm'])->name('login');
        // Own prefixes — bare throttle keys by IP+domain only (no user is
        // authenticated yet), so without these these three would pool with
        // each other AND with the main site's /login, /forgot-password,
        // /reset-password (same IP, same null route-domain on both).
        Route::post('/login',         [CustomerPortalController::class, 'login'])->name('login.post')->middleware('throttle:5,1,portal-login');
        Route::get('/forgot',         [CustomerPortalController::class, 'forgotForm'])->name('forgot');
        Route::post('/forgot',        [CustomerPortalController::class, 'sendReset'])->name('forgot.post')->middleware('throttle:5,1,portal-forgot');
        Route::get('/reset/{token}',  [CustomerPortalController::class, 'resetForm'])->name('reset');
        Route::post('/reset',         [CustomerPortalController::class, 'resetPassword'])->name('reset.post')->middleware('throttle:5,1,portal-reset');

        // Shown when an email+password matches customer records at more than
        // one business — customer picks which one they meant to sign into.
        Route::get('/choose-business',  [CustomerPortalController::class, 'chooseBusinessForm'])->name('choose-business');
        Route::post('/choose-business', [CustomerPortalController::class, 'chooseBusiness'])->name('choose-business.post');
    });
    // Authenticated customer routes
    Route::middleware('portal.auth')->group(function () {
        Route::get('/',                          [CustomerPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/invoices',                  [CustomerPortalController::class, 'invoices'])->name('invoices');
        Route::get('/invoices/{invoice}',        [CustomerPortalController::class, 'invoiceDetail'])->name('invoices.show');
        Route::get('/invoices/{invoice}/pdf',    [CustomerPortalController::class, 'invoicePdf'])->name('invoices.pdf');
        Route::get('/statement',                 [CustomerPortalController::class, 'statement'])->name('statement');
        Route::get('/loyalty',                   [CustomerPortalController::class, 'loyalty'])->name('loyalty');
        Route::post('/pay/{invoice}',            [CustomerPortalController::class, 'payInvoice'])->name('pay');
        Route::post('/logout',                   [CustomerPortalController::class, 'logout'])->name('logout');
        Route::get('/profile',                   [CustomerPortalController::class, 'profile'])->name('profile');
        Route::post('/profile',                  [CustomerPortalController::class, 'updateProfile'])->name('profile.update');

        // In-app delivery for newsletters — the portal had no notification
        // concept at all before this; mirrors the staff bell dropdown's
        // mark-read/read-all/destroy actions in NotificationController.
        Route::post('/notifications/{id}/read', [\App\Http\Controllers\Portal\PortalNotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('/notifications/read-all',  [\App\Http\Controllers\Portal\PortalNotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::delete('/notifications/{id}',    [\App\Http\Controllers\Portal\PortalNotificationController::class, 'destroy'])->name('notifications.destroy');
    });
});

// ── Support chat (the tenant's side) ─────────────────────────────────────────
// Deliberately outside the 'subscribed' group: a store whose plan has lapsed must still be
// able to ask for help.
Route::middleware(['auth', '2fa'])->prefix('support')->name('support.')->group(function () {
    $c = \App\Http\Controllers\SupportChatController::class;
    Route::get('/thread',  [$c, 'thread'])->middleware('throttle:90,1,support-thread')->name('thread');
    Route::get('/unread',  [$c, 'unread'])->middleware('throttle:30,1,support-unread')->name('unread');
    Route::post('/messages', [$c, 'send'])->middleware('throttle:20,1,support-send')->name('send');
    Route::get('/attachments/{message}', [$c, 'attachment'])->name('attachment');
});

// ── Super Admin Routes ─────────────────────────────────────────────────────
Route::middleware(['auth', 'admin', '2fa'])->prefix('admin')->name('admin.')->group(function () {

    // Dashboard
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Every state-changing action below needs the super admin to have confirmed
    // their 2FA code in the last 15 minutes ('sudo'): suspending an organization
    // or granting a free subscription from a session that was left open, or
    // stolen after the login challenge, is exactly what this account must resist.
    // Organizations
    Route::prefix('organizations')->name('organizations.')->group(function () {
        Route::get('/',                                             [AdminOrganizationController::class, 'index'])->name('index');
        Route::get('/{organization}',                              [AdminOrganizationController::class, 'show'])->name('show');
        Route::patch('/{organization}/suspend',                    [AdminOrganizationController::class, 'suspend'])->middleware('sudo')->name('suspend');
        Route::patch('/{organization}/activate',                   [AdminOrganizationController::class, 'activate'])->middleware('sudo')->name('activate');
        Route::patch('/{organization}/extend-trial',               [AdminOrganizationController::class, 'extendTrial'])->middleware('sudo')->name('extend-trial');
        Route::post('/{organization}/grant-subscription',          [AdminOrganizationController::class, 'grantSubscription'])->middleware('sudo')->name('grant-subscription');
        Route::patch('/{organization}/notes',                      [AdminOrganizationController::class, 'updateNotes'])->name('notes');
        Route::patch('/{organization}/subscriptions/{subscription}/cancel', [AdminOrganizationController::class, 'cancelSubscription'])->middleware('sudo')->name('subscriptions.cancel');
        Route::post('/{organization}/reset-2fa',                   [AdminOrganizationController::class, 'resetTwoFactor'])->middleware('sudo')->name('reset-2fa');
    });

    // Businesses (stores — drill-down from org)
    // Trial/subscription stay org-level — see admin/organizations. But a
    // single store can now be suspended independently of the org (e.g. one
    // branch closed or flagged, others keep trading) — see BusinessController
    // and CheckSubscription middleware.
    Route::prefix('businesses')->name('businesses.')->group(function () {
        Route::get('/',                    [AdminBusinessController::class, 'index'])->name('index');
        Route::get('/{business}',          [AdminBusinessController::class, 'show'])->name('show');
        Route::patch('/{business}/suspend', [AdminBusinessController::class, 'suspend'])->middleware('sudo')->name('suspend');
        Route::patch('/{business}/activate',[AdminBusinessController::class, 'activate'])->middleware('sudo')->name('activate');
        Route::patch('/{business}/members/{user}/toggle', [AdminBusinessController::class, 'toggleMember'])->middleware('sudo')->name('members.toggle');
    });

    // Subscriptions
    Route::get('/subscriptions', [AdminSubscriptionController::class, 'index'])->name('subscriptions.index');

    // Promo codes — platform-level discounts on a tenant's own subscription
    Route::prefix('promo-codes')->name('promo-codes.')->group(function () {
        Route::get('/',                 [App\Http\Controllers\Admin\PromoCodeController::class, 'index'])->name('index');
        Route::post('/',                [App\Http\Controllers\Admin\PromoCodeController::class, 'store'])->middleware('sudo')->name('store');
        Route::patch('/{promoCode}/toggle', [App\Http\Controllers\Admin\PromoCodeController::class, 'toggle'])->middleware('sudo')->name('toggle');
        Route::delete('/{promoCode}',   [App\Http\Controllers\Admin\PromoCodeController::class, 'destroy'])->middleware('sudo')->name('destroy');
    });

    // Newsletters (platform-wide announcements to organization owners)
    Route::prefix('newsletters')->name('newsletters.')->group(function () {
        Route::get('/',  [AdminNewsletterController::class, 'index'])->middleware('sudo')->name('index');
        Route::post('/', [AdminNewsletterController::class, 'store'])->middleware('sudo')->name('store');
    });

    // Support inbox: messages from the chat bubble in every store
    Route::prefix('support')->name('support.')->group(function () {
        $s = \App\Http\Controllers\Admin\SupportController::class;
        Route::get('/',        [$s, 'index'])->name('index');
        Route::get('/unread',  [$s, 'unread'])->name('unread');
        Route::post('/canned', [$s, 'storeCanned'])->name('canned.store');
        Route::delete('/canned/{canned}', [$s, 'destroyCanned'])->name('canned.destroy');
        Route::get('/attachments/{message}', [$s, 'attachment'])->name('attachment');
        Route::get('/{conversation}',         [$s, 'show'])->whereNumber('conversation')->name('show');
        Route::post('/{conversation}/reply',  [$s, 'reply'])->whereNumber('conversation')->name('reply');
        Route::post('/{conversation}/note',   [$s, 'note'])->whereNumber('conversation')->name('note');
        Route::post('/{conversation}/status', [$s, 'status'])->whereNumber('conversation')->name('status');
    });

    // Developer logs: what stores do, and what fails (super admin only, like the rest of /admin)
    Route::prefix('logs')->name('logs.')->group(function () {
        $c = \App\Http\Controllers\Admin\LogController::class;
        Route::get('/',                             [$c, 'index'])->name('index');
        Route::get('/errors',                       [$c, 'errors'])->name('errors');
        Route::get('/errors/{fingerprint}',         [$c, 'errorShow'])->where('fingerprint', '[a-f0-9]{40}')->name('errors.show');
        Route::post('/errors/{fingerprint}/resolve', [$c, 'resolve'])->where('fingerprint', '[a-f0-9]{40}')->middleware('sudo')->name('errors.resolve');
        Route::get('/activity',                     [$c, 'activity'])->name('activity');
        Route::get('/failures',                     [$c, 'failures'])->name('failures');
    });

    // Change Password
    Route::get('/password',  [AdminDashboardController::class, 'passwordForm'])->name('password');
    Route::post('/password', [AdminDashboardController::class, 'passwordUpdate'])->name('password.update');
});

// ── FEATURE 1: Webhooks (settings, owner only) ───────────────────────────────
Route::middleware(['auth', '2fa', 'subscribed', 'role:owner'])->prefix('settings/webhooks')->name('settings.webhooks.')->group(function () {
    Route::get('/',                         [WebhookController::class, 'index'])->name('index');
    Route::get('/create',                   [WebhookController::class, 'create'])->name('create');
    Route::post('/',                        [WebhookController::class, 'store'])->name('store');
    Route::get('/{webhook}/edit',           [WebhookController::class, 'edit'])->name('edit');
    Route::put('/{webhook}',                [WebhookController::class, 'update'])->name('update');
    Route::delete('/{webhook}',             [WebhookController::class, 'destroy'])->name('destroy');
    Route::post('/{webhook}/test',          [WebhookController::class, 'test'])->name('test');
    Route::get('/{webhook}/deliveries',     [WebhookController::class, 'deliveries'])->name('deliveries');
});

// ── Google Sheets live export (settings, owner only) ─────────────────────────
Route::middleware(['auth', '2fa', 'subscribed', 'role:owner'])->prefix('settings/google-sheets')->name('settings.google-sheets.')->group(function () {
    Route::get('/',            [App\Http\Controllers\GoogleSheetsController::class, 'index'])->name('index');
    Route::get('/connect',     [App\Http\Controllers\GoogleSheetsController::class, 'connect'])->name('connect');
    Route::get('/callback',    [App\Http\Controllers\GoogleSheetsController::class, 'callback'])->name('callback');
    Route::post('/disconnect', [App\Http\Controllers\GoogleSheetsController::class, 'disconnect'])->middleware('sudo')->name('disconnect');
    Route::post('/sync',       [App\Http\Controllers\GoogleSheetsController::class, 'syncNow'])->name('sync');
});

// ── FEATURE 2: Accounting Exports (settings, owner only) ─────────────────────
Route::middleware(['auth', '2fa', 'subscribed', 'role:owner'])->group(function () {
    Route::get('/settings/export',           [ExportController::class, 'index'])->name('settings.export');
    Route::get('/settings/export/quickbooks',[ExportController::class, 'quickbooks'])->name('settings.export.quickbooks');
    Route::get('/settings/export/xero',      [ExportController::class, 'xero'])->name('settings.export.xero');
});

require __DIR__.'/features_payroll.php';

require __DIR__.'/features_inventory.php';

require __DIR__.'/features_finance.php';

require __DIR__.'/features_ops.php';

require __DIR__.'/features_tax.php';

require __DIR__.'/features_business.php';

require __DIR__.'/features_ux.php';

// Was never required at all — see the note at the top of that file for the
// full list of features this restores (Campaigns, Payment Links including
// the public pay/{token} page, Product Reviews admin, Loyalty Tiers, public
// shop review submission).
require __DIR__.'/features_marketing.php';
