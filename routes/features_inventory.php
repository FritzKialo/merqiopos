<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StockCountController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\ProductWaitlistController;

Route::middleware(['auth', '2fa', 'verified'])->group(function () {
    // Stock Counts
    Route::get('inventory/stock-counts', [StockCountController::class, 'index'])->name('inventory.stock-counts.index');
    Route::get('inventory/stock-counts/create', [StockCountController::class, 'create'])->name('inventory.stock-counts.create');
    Route::post('inventory/stock-counts', [StockCountController::class, 'store'])->name('inventory.stock-counts.store');
    Route::get('inventory/stock-counts/{count}', [StockCountController::class, 'show'])->name('inventory.stock-counts.show');
    Route::patch('inventory/stock-counts/{count}', [StockCountController::class, 'update'])->name('inventory.stock-counts.update');
    // Completing a count writes the counted quantities into stock, so it is a
    // manager decision; cashiers can still enter counts.
    Route::middleware('role:owner,manager')->group(function () {
        Route::post('inventory/stock-counts/{count}/complete', [StockCountController::class, 'complete'])->name('inventory.stock-counts.complete');
        Route::post('inventory/stock-counts/{count}/cancel', [StockCountController::class, 'cancel'])->name('inventory.stock-counts.cancel');
        Route::delete('inventory/stock-counts/{count}', [StockCountController::class, 'destroy'])->name('inventory.stock-counts.destroy');
    });

    // Cash Registers
    Route::get('cash-registers', [CashRegisterController::class, 'index'])->name('cash-registers.index');
    Route::get('cash-registers/open', [CashRegisterController::class, 'openForm'])->name('cash-registers.open-form');
    Route::post('cash-registers/open', [CashRegisterController::class, 'open'])->name('cash-registers.open');
    Route::get('cash-registers/current', [CashRegisterController::class, 'current'])->name('cash-registers.current');
    Route::get('cash-registers/{register}', [CashRegisterController::class, 'show'])->name('cash-registers.show');
    Route::post('cash-registers/{register}/entry', [CashRegisterController::class, 'addEntry'])->name('cash-registers.entry');
    Route::post('cash-registers/{register}/close', [CashRegisterController::class, 'close'])->name('cash-registers.close');

    // Delivery Settings and the customer waitlist (which emails customers) —
    // owner/manager only.
    Route::middleware('role:owner,manager')->group(function () {
    Route::get('settings/delivery', [App\Http\Controllers\SettingsController::class, 'deliverySettings'])->name('settings.delivery');
    Route::post('settings/delivery', [App\Http\Controllers\SettingsController::class, 'updateDeliverySettings'])->name('settings.delivery.update');

    // Waitlist (admin)
    Route::get('inventory/waitlist', [ProductWaitlistController::class, 'index'])->name('inventory.waitlist.index');
    Route::post('inventory/waitlist/{entry}/notify', [ProductWaitlistController::class, 'notify'])->name('inventory.waitlist.notify');
    Route::post('inventory/waitlist/notify-all', [ProductWaitlistController::class, 'notifyAll'])->name('inventory.waitlist.notify-all');
    Route::delete('inventory/waitlist/{entry}', [ProductWaitlistController::class, 'destroy'])->name('inventory.waitlist.destroy');
    });
});

// Public routes (no auth)
// Same protection as shop.reviews.store, with its own independent quota —
// see that route's comment for why the third "shop-waitlist" argument
// matters (without it, this would share one pooled counter with every
// other public shop form using the same bare throttle:5,60).
Route::post('shop/{slug}/waitlist', [ProductWaitlistController::class, 'publicRegister'])
    ->middleware('throttle:5,60,shop-waitlist')->name('shop.waitlist');
