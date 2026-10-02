<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PurchaseRequisitionController;
use App\Http\Controllers\ExpenseClaimController;
use App\Http\Controllers\OnlineOrderController;

Route::middleware(['auth', '2fa', 'verified'])->group(function () {
    // Credit Limits
    Route::post('customers/{customer}/credit-limit', [App\Http\Controllers\CustomerController::class, 'updateCreditLimit'])->name('customers.credit-limit');

    // Online Orders (public shop checkout management — previously no admin
    // UI existed anywhere for these; role-gated the same as invoices/
    // delivery-notes since it's a sales-fulfilment surface, not day-to-day
    // cashier work).
    Route::prefix('online-orders')->name('online-orders.')
        ->middleware('role:owner,manager,overall_manager')
        ->group(function () {
        Route::get('/', [OnlineOrderController::class, 'index'])->name('index');
        Route::get('/{onlineOrder}', [OnlineOrderController::class, 'show'])->name('show');
        Route::post('/{onlineOrder}/mark-paid', [OnlineOrderController::class, 'markPaid'])->name('mark-paid');
        Route::post('/{onlineOrder}/status', [OnlineOrderController::class, 'updateStatus'])->name('status');
        Route::post('/{onlineOrder}/cancel', [OnlineOrderController::class, 'cancel'])->name('cancel');
    });

    // Purchase Requisitions
    Route::resource('purchase-requisitions', PurchaseRequisitionController::class);
    Route::post('purchase-requisitions/{req}/approve', [PurchaseRequisitionController::class, 'approve'])->name('purchase-requisitions.approve');
    Route::post('purchase-requisitions/{req}/reject', [PurchaseRequisitionController::class, 'reject'])->name('purchase-requisitions.reject');
    Route::post('purchase-requisitions/{req}/convert', [PurchaseRequisitionController::class, 'convert'])->name('purchase-requisitions.convert');

    // Expense Claims
    Route::resource('expense-claims', ExpenseClaimController::class);
    Route::post('expense-claims/{claim}/approve', [ExpenseClaimController::class, 'approve'])->name('expense-claims.approve');
    Route::post('expense-claims/{claim}/reject', [ExpenseClaimController::class, 'reject'])->name('expense-claims.reject');
    Route::post('expense-claims/{claim}/pay', [ExpenseClaimController::class, 'pay'])->name('expense-claims.pay');
});
