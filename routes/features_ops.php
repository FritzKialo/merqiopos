<?php
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', '2fa', 'verified'])->group(function () {
    // Receipt/Invoice Branding — owner/manager only (a cashier could change
    // the logo and the wording printed on every receipt and invoice).
    Route::middleware('role:owner,manager')->group(function () {
    Route::get('settings/branding', [App\Http\Controllers\SettingsController::class, 'branding'])->name('settings.branding');
    Route::post('settings/branding', [App\Http\Controllers\SettingsController::class, 'updateBranding'])->name('settings.branding.update');
    Route::post('settings/branding/logo/remove', [App\Http\Controllers\SettingsController::class, 'removeLogo'])->name('settings.branding.logo.remove');
    });

    // Inter-Branch Transfers — already registered in web.php (PATCH routes)
});
