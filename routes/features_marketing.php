<?php
// This entire file was never require()'d from routes/web.php (every sibling
// features_*.php file is; this one was the sole exception) — every route
// below has been unreachable since whenever this file was created. That
// means the Campaigns feature, Payment Links (both the admin CRUD AND the
// public pay/{token} customer-facing payment page), the admin Product
// Reviews moderation page, Loyalty Tiers settings, and public shop review
// submission have all been completely dead: every link 404s, and every
// route() call to generate one of these URLs throws RouteNotFoundException
// immediately. Now required from web.php alongside its sibling files.
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ProductReviewController;
use App\Http\Controllers\PaymentLinkController;

Route::middleware(['auth', '2fa', 'verified'])->group(function () {
    // Loyalty Tiers — owner/manager only (sets how much customers earn).
    Route::middleware('role:owner,manager')->group(function () {
        Route::get('settings/loyalty/tiers', [App\Http\Controllers\LoyaltyController::class, 'tiers'])->name('settings.loyalty.tiers');
        Route::post('settings/loyalty/tiers', [App\Http\Controllers\LoyaltyController::class, 'saveTiers'])->name('settings.loyalty.tiers.save');
    });

    // Bulk Campaigns
    // campaigns/preview MUST be registered before the resource() call below
    // — Route::resource's implicit campaigns/{campaign} (show) is a
    // wildcard that matches "preview" as a campaign id/slug, so with the
    // resource route registered first it swallowed every request to
    // /campaigns/preview into a 404 (failed model binding) before this
    // route ever got a chance to match.
    Route::middleware('role:owner,manager')->group(function () {
    Route::get('campaigns/preview', [CampaignController::class, 'preview'])->name('campaigns.preview');
        Route::resource('campaigns', CampaignController::class)->except(['edit','update']);
        Route::post('campaigns/{campaign}/send', [CampaignController::class, 'send'])->name('campaigns.send');
    });

    // Product Reviews (admin) — owner/manager only: what appears on the shop.
    Route::middleware('role:owner,manager')->group(function () {
        Route::get('product-reviews', [ProductReviewController::class, 'index'])->name('product-reviews.index');
        Route::post('product-reviews/{review}/approve', [ProductReviewController::class, 'approve'])->name('product-reviews.approve');
        Route::post('product-reviews/{review}/reject', [ProductReviewController::class, 'reject'])->name('product-reviews.reject');
        Route::delete('product-reviews/{review}', [ProductReviewController::class, 'destroy'])->name('product-reviews.destroy');
    });

    // Payment Links
    Route::resource('payment-links', PaymentLinkController::class)->except(['edit','update']);
    Route::post('payment-links/{link}/send-sms', [PaymentLinkController::class, 'sendSms'])->name('payment-links.send-sms');
    Route::post('payment-links/{link}/cancel', [PaymentLinkController::class, 'cancel'])->name('payment-links.cancel');

    // Coupons already fully registered in web.php (Route::resource +
    // coupons.validate) — not duplicated here to avoid a second, disagreeing
    // registration for the exact same routes.
});

// Public routes (no auth)
// throttle:5,60 = at most 5 submissions per IP per 60 minutes — generous
// enough for a genuine shopper (who submits once, maybe retries after a
// validation typo) while stopping a script from flooding the queue. The
// bare numeric form of this middleware keys its counter by IP+domain
// ONLY, not by route — every public shop form using it would otherwise
// share one pooled counter (using up your review attempts would also
// block you from joining a waitlist). The third argument is an explicit
// per-route prefix so each public form gets its own independent quota.
Route::post('shop/{slug}/reviews', [ProductReviewController::class, 'store'])
    ->middleware('throttle:5,60,shop-reviews')->name('shop.reviews.store');
Route::get('pay/{token}', [PaymentLinkController::class, 'pay'])->name('pay.show');
// The phone number here is arbitrary customer input, not a fixed number
// on file — with no limit, this page could be used to spam STK push
// prompts to any number a visitor types in. Own prefix, same reasoning
// as every other public-form throttle in this app (see shop.reviews.store).
Route::post('pay/{token}/initiate', [PaymentLinkController::class, 'initiate'])
    ->middleware('throttle:10,60,payment-link-initiate')->name('pay.initiate');
// Was missing the 'safaricom' IP-restriction middleware every other M-Pesa
// callback endpoint in this app correctly has — this one just trusts
// ResultCode==0 in the raw POST body to mark a payment link "paid", with
// nothing stopping anyone (not just Safaricom's real servers) from posting
// a forged "payment succeeded" callback for a checkout_id they obtained any
// other way and marking a link paid for free.
Route::middleware('safaricom')->post('api/pay/mpesa/callback', [PaymentLinkController::class, 'mpesaCallback'])->name('pay.mpesa.callback');
