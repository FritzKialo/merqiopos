<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController as ApiProductController;
use App\Http\Controllers\Api\SaleController as ApiSaleController;
use App\Http\Controllers\Api\CustomerController as ApiCustomerController;

// ── M-Pesa callbacks (public — no auth, IP-restricted to Safaricom) ──────
Route::middleware('safaricom')->group(function () {
    Route::post('/mpesa/callback',
        [App\Http\Controllers\MpesaController::class, 'callback']
    )->name('mpesa.callback');

    Route::post('/mpesa/b2c/result',
        [App\Http\Controllers\MpesaB2CController::class, 'result']
    )->name('mpesa.b2c.result');

    Route::post('/mpesa/b2c/timeout',
        [App\Http\Controllers\MpesaB2CController::class, 'timeout']
    )->name('mpesa.b2c.timeout');

    // Transaction Status (verify a typed-in receipt code) — result + timeout.
    Route::post('/mpesa/status/result',
        [App\Http\Controllers\MpesaStatusController::class, 'result']
    )->name('mpesa.status.result');

    Route::post('/mpesa/status/timeout',
        [App\Http\Controllers\MpesaStatusController::class, 'timeout']
    )->name('mpesa.status.timeout');
});

// ── REST API (Enterprise — API token auth) ─────────────────────────────────
// ── Barcode / POS product lookup (session auth via sanctum) ──────────────
// (Moved to routes/features_ux.php: this group used the 'sanctum' guard, which
// isn't installed, so both endpoints returned a 500 for every request.)

Route::middleware('api.token')->prefix('v1')->name('api.v1.')->group(function () {

    // Products
    Route::get('/products',         [ApiProductController::class, 'index'])->name('products.index');
    Route::get('/products/{id}',    [ApiProductController::class, 'show'])->name('products.show');
    Route::post('/products',        [ApiProductController::class, 'store'])->name('products.store');
    Route::put('/products/{id}',    [ApiProductController::class, 'update'])->name('products.update');

    // Sales
    Route::get('/sales',            [ApiSaleController::class, 'index'])->name('sales.index');
    Route::get('/sales/summary',    [ApiSaleController::class, 'summary'])->name('sales.summary');
    Route::get('/sales/{id}',       [ApiSaleController::class, 'show'])->name('sales.show');

    // Customers
    Route::get('/customers',        [ApiCustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/{id}',   [ApiCustomerController::class, 'show'])->name('customers.show');
    Route::post('/customers',       [ApiCustomerController::class, 'store'])->name('customers.store');
    Route::put('/customers/{id}',   [ApiCustomerController::class, 'update'])->name('customers.update');
});