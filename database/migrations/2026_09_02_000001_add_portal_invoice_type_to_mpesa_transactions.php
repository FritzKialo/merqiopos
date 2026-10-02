<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    // Same widening as 2026_08_21_000004 did for 'payment_link' — customer
    // portal invoice payments via M-Pesa never created a reconcilable
    // MpesaTransaction row at all (no checkout_id saved anywhere), so the
    // payment could never be automatically matched and confirmed when
    // Safaricom's callback arrived. Adding 'portal_invoice' so this path
    // gets the same real transaction row every other payment path has.
    public function up(): void {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE mpesa_transactions MODIFY COLUMN type ENUM('sale','subscription','c2b','payment_link','portal_invoice') DEFAULT 'sale'");
        }
    }

    public function down(): void {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE mpesa_transactions MODIFY COLUMN type ENUM('sale','subscription','c2b','payment_link') DEFAULT 'sale'");
        }
    }
};
