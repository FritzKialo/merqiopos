<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VatReturnController;
use App\Http\Controllers\WithholdingTaxController;
use App\Http\Controllers\PayrollController;

Route::middleware(['auth', '2fa', 'verified'])->group(function () {
    // VAT Returns — the whole business's output/input VAT position: managers
    // and owners only. Both routes were open to any signed-in user, so a
    // cashier who typed the address could read and download the VAT return.
    Route::middleware('role:owner,manager')->group(function () {
        Route::get('reports/vat-return', [VatReturnController::class, 'index'])->name('reports.vat-return');
        Route::get('reports/vat-return/export', [VatReturnController::class, 'export'])->name('reports.vat-return.export');
    });

    // Statutory Remittance Reports — Growth+ only per config/plans.php
    // ('payroll_statutory'), and clearly meant to be owner-only like the
    // rest of Payroll (dumps every employee's PAYE/NSSF/SHIF deductions in
    // one page). Had neither check: any authenticated staff member on any
    // plan, cashier included, could load these — no route middleware and
    // no controller-side check either. Same missing-feature-gating shape as
    // the P9 routes fixed earlier this session.
    Route::middleware(['role:owner', 'feature:payroll_statutory'])->group(function () {
        Route::get('payroll/remittance/paye', [PayrollController::class, 'payeRemittance'])->name('payroll.paye-remittance');
        Route::get('payroll/remittance/nssf', [PayrollController::class, 'nssfRemittance'])->name('payroll.nssf-remittance');
        Route::get('payroll/remittance/shif', [PayrollController::class, 'shifRemittance'])->name('payroll.shif-remittance');
        Route::get('payroll/remittance/housing-levy', [PayrollController::class, 'housingLevyRemittance'])->name('payroll.housing-levy-remittance');
    });

    // Withholding Tax
    // NOTE: 'export' must be registered before the '{wht}' wildcard route, or
    // Laravel matches GET /withholding-tax/export as {wht}='export' first —
    // that's exactly what was happening here (the Export CSV button on the
    // index page has never worked, always 404ing on route-model binding).
    Route::middleware('role:owner,manager')->group(function () {
    Route::get('withholding-tax', [WithholdingTaxController::class, 'index'])->name('withholding-tax.index');
        Route::get('withholding-tax/create', [WithholdingTaxController::class, 'create'])->name('withholding-tax.create');
        Route::post('withholding-tax', [WithholdingTaxController::class, 'store'])->name('withholding-tax.store');
        Route::get('withholding-tax/export', [WithholdingTaxController::class, 'export'])->name('withholding-tax.export');
        Route::get('withholding-tax/{wht}', [WithholdingTaxController::class, 'show'])->name('withholding-tax.show');
        Route::delete('withholding-tax/{wht}', [WithholdingTaxController::class, 'destroy'])->name('withholding-tax.destroy');
    });
});
