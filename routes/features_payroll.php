<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\P9Controller;
use App\Http\Controllers\CommissionController;

Route::middleware(['auth', '2fa', 'verified'])->group(function () {
    // P9 Tax Certificates — owner-only (these expose every employee's full
    // annual pay/tax detail). Previously only 'auth,2fa,verified', so any
    // logged-in staff member could browse or PDF-download a coworker's P9
    // just by knowing/guessing the URL — the index page itself is only
    // linked from a canActAsOwner()-gated button, but that's a UI nicety,
    // not access control. Matches the role:owner + feature:p9_forms
    // restriction already enforced on the older, parallel payroll.p9 route
    // in routes/web.php.
    Route::middleware(['role:owner', 'feature:p9_forms'])->group(function () {
        Route::get('payroll/p9', [P9Controller::class, 'index'])->name('payroll.p9.index');
        Route::get('payroll/p9/{staffProfile}', [P9Controller::class, 'show'])->name('payroll.p9.show');
        Route::get('payroll/p9/{staffProfile}/pdf', [P9Controller::class, 'pdf'])->name('payroll.p9.pdf');
    });

    // HELB Remittance — same statutory-remittance shape (and the same
    // missing-gate bug) as the PAYE/NSSF/SHIF reports fixed alongside this
    // in routes/features_tax.php: Growth+/owner-only per config/plans.php,
    // previously enforced nowhere.
    Route::middleware(['role:owner', 'feature:payroll_statutory'])->group(function () {
        Route::get('payroll/helb-remittance', [App\Http\Controllers\PayrollController::class, 'helbRemittance'])->name('payroll.helb-remittance');
    });

    // Staff Commissions — owner/manager only. These routes sat behind login
    // alone, so a cashier could create a commission rule for themselves,
    // run the calculation, and read every colleague's earnings.
    Route::middleware('role:owner,manager')->group(function () {
    Route::get('staff/commissions', [CommissionController::class, 'index'])->name('staff.commissions.index');
    Route::get('staff/commissions/create', [CommissionController::class, 'create'])->name('staff.commissions.create');
    Route::post('staff/commissions', [CommissionController::class, 'store'])->name('staff.commissions.store');
    Route::put('staff/commissions/{rule}', [CommissionController::class, 'update'])->name('staff.commissions.update');
    Route::delete('staff/commissions/{rule}', [CommissionController::class, 'destroy'])->name('staff.commissions.destroy');
    Route::get('staff/commissions/earnings', [CommissionController::class, 'earnings'])->name('staff.commissions.earnings');
    Route::post('staff/commissions/calculate', [CommissionController::class, 'calculate'])->name('staff.commissions.calculate');
    Route::post('staff/commissions/earnings/{earning}/approve', [CommissionController::class, 'approve'])->name('staff.commissions.approve');
    });
});
