<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // payroll_items.status has always been enum('pending','paid') — but
    // PayrollItem::isPendingPayment()/isFailed() check for 'pending_payment'
    // and 'failed', and 3 real code paths actually write those values:
    // PayrollController::markPaid() sets 'pending_payment' right after
    // initiating an M-Pesa B2C payout, and both SendMpesaPayment (the queued
    // job) and MpesaB2CController (the Safaricom result/timeout callbacks)
    // write 'failed' on any B2C failure. Under MySQL strict mode (the
    // default here) writing either value throws a fatal "Data truncated for
    // column 'status'" error — meaning every M-Pesa payroll disbursement
    // has been crashing the instant it's initiated, and every failure
    // callback from Safaricom has also been crashing instead of marking the
    // item failed for retry. Discovered while direct-rendering payroll.show
    // for an unrelated CSS verification.
    public function up(): void {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->enum('status', ['pending', 'pending_payment', 'paid', 'failed'])->default('pending')->change();
        });
    }

    public function down(): void {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->enum('status', ['pending', 'paid'])->default('pending')->change();
        });
    }
};
