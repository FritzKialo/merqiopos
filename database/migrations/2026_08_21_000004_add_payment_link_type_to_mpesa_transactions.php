<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    // Widen mpesa_transactions.type so Payment Link STK pushes get a real,
    // reconcilable transaction row like every other payment path already
    // does — see PaymentLinkController::initiate()/mpesaCallback(), fixed
    // alongside this to also stop routing the money to the platform's own
    // M-Pesa shortcode instead of the business's.
    public function up(): void {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE mpesa_transactions MODIFY COLUMN type ENUM('sale','subscription','c2b','payment_link') DEFAULT 'sale'");
        }
    }

    public function down(): void {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE mpesa_transactions MODIFY COLUMN type ENUM('sale','subscription','c2b') DEFAULT 'sale'");
        }
    }
};
