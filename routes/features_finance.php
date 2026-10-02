<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProformaInvoiceController;
use App\Http\Controllers\CustomerDepositController;
use App\Http\Controllers\SupplierCreditNoteController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\CurrencyController;

// These routes are loaded by the main web.php via require
Route::middleware(['auth', '2fa', 'verified'])->group(function () {

    // Everything below except Customer Deposits (already gated) was open to ANY
    // signed-in user, cashiers included: a cashier could type the address of
    // the Proforma Invoices, Supplier Credit Notes, Gross Margin report or
    // Currency rates and read or change them. The menu hid them; the routes
    // didn't. Managers and owners only (currency rates: owner only).
    Route::middleware('role:owner,manager')->group(function () {

    // Proforma Invoices
    Route::resource('proforma-invoices', ProformaInvoiceController::class);
    Route::post('proforma-invoices/{proformaInvoice}/send',    [ProformaInvoiceController::class, 'send'])->name('proforma-invoices.send');
    Route::post('proforma-invoices/{proformaInvoice}/accept',  [ProformaInvoiceController::class, 'accept'])->name('proforma-invoices.accept');
    Route::post('proforma-invoices/{proformaInvoice}/reject',  [ProformaInvoiceController::class, 'reject'])->name('proforma-invoices.reject');
    Route::post('proforma-invoices/{proformaInvoice}/convert', [ProformaInvoiceController::class, 'convert'])->name('proforma-invoices.convert');
    });

    // Customer Deposits
    Route::middleware('role:owner,manager')->group(function () {
    Route::resource('customer-deposits', CustomerDepositController::class)->except(['edit', 'update']);
        Route::post('customer-deposits/{customerDeposit}/apply', [CustomerDepositController::class, 'apply'])->name('customer-deposits.apply');
    });

    Route::middleware('role:owner,manager')->group(function () {
    // Supplier Credit Notes
    Route::resource('supplier-credit-notes', SupplierCreditNoteController::class);
    Route::post('supplier-credit-notes/{supplierCreditNote}/apply', [SupplierCreditNoteController::class, 'apply'])->name('supplier-credit-notes.apply');

    // Reports
    Route::get('reports/gross-margin', [ReportController::class, 'grossMargin'])->name('reports.gross-margin');
    });

    // Multi-Currency (settings menu shows it to owners only)
    Route::middleware('role:owner')->group(function () {
    Route::get('settings/currencies',          [CurrencyController::class, 'index'])->name('settings.currencies');
    Route::post('settings/currencies',         [CurrencyController::class, 'store'])->name('settings.currencies.store');
    Route::put('settings/currencies/{rate}',   [CurrencyController::class, 'update'])->name('settings.currencies.update');
    Route::delete('settings/currencies/{rate}',[CurrencyController::class, 'destroy'])->name('settings.currencies.destroy');
    });
});
