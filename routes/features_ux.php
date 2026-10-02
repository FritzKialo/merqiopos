<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\NotificationController;

Route::middleware(['auth', '2fa', 'verified'])->group(function () {
    // Audit Log — meant to be owner-only (there's a second, dead
    // registration of this exact URI in routes/web.php with role:owner —
    // silently overwritten by this one being the later-loaded require(),
    // per the same last-registration-wins behavior documented for the VAT
    // return route collision earlier this session). This live registration
    // had no role restriction at all and neither does the controller, so
    // any authenticated user — cashier included — could read the full
    // audit trail. Restored the intended restriction here.
    Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index')->middleware('role:owner');
    Route::get('audit-log/export', [AuditLogController::class, 'export'])->name('audit-log.export')->middleware('role:owner');

    // Activity log: what people in the owner's stores did, and what failed (rejected forms,
    // refused actions, sign-ins). Complements the audit log; answers "who did this?" and
    // "why didn't that work?". Owner only.
    Route::get('activity-log', [\App\Http\Controllers\ActivityLogController::class, 'index'])->name('activity-log.index')->middleware('role:owner');

    // POS / table-order product lookup — session-authenticated. Same URIs the
    // front-end already calls; previously registered in routes/api.php behind
    // 'auth:sanctum', a guard that doesn't exist in this app.
    // Ask Safaricom to confirm a typed-in M-Pesa code on a sale.
    Route::post('sales/{sale}/verify-mpesa', [App\Http\Controllers\MpesaStatusController::class, 'verify'])->name('sales.verify-mpesa');

    Route::get('api/products/lookup', [App\Http\Controllers\Api\BarcodeController::class, 'lookup'])->name('api.products.lookup');
    Route::get('api/products/search', [App\Http\Controllers\Api\BarcodeController::class, 'search'])->name('api.products.search');

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::delete('notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    // Document Attachments (API endpoint - used via AJAX)
    Route::post('attachments', [App\Http\Controllers\AttachmentController::class, 'store'])->name('attachments.store');
    Route::delete('attachments/{attachment}', [App\Http\Controllers\AttachmentController::class, 'destroy'])->name('attachments.destroy');
    Route::get('attachments/{attachment}/download', [App\Http\Controllers\AttachmentController::class, 'download'])->name('attachments.download');

    // Warehouses
    Route::middleware('role:owner,manager')->group(function () {
    Route::resource('warehouses', App\Http\Controllers\WarehouseController::class)->except(['edit', 'update']);
        Route::post('warehouses/{warehouse}/stock', [App\Http\Controllers\WarehouseController::class, 'updateStock'])->name('warehouses.stock');
        Route::get('warehouses/{warehouse}/bins', [App\Http\Controllers\WarehouseController::class, 'bins'])->name('warehouses.bins');
        Route::post('warehouses/{warehouse}/bins/move', [App\Http\Controllers\WarehouseController::class, 'moveBin'])->name('warehouses.bins.move');
        Route::post('warehouses/{warehouse}/bins/clear', [App\Http\Controllers\WarehouseController::class, 'clearBin'])->name('warehouses.bins.clear');
    });
});
