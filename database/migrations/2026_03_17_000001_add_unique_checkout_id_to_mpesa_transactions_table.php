<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mpesa_transactions', function (Blueprint $table) {
            // Prevent duplicate callback processing if Daraja retries the webhook
            $table->unique('checkout_id', 'mpesa_transactions_checkout_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('mpesa_transactions', function (Blueprint $table) {
            $table->dropUnique('mpesa_transactions_checkout_id_unique');
        });
    }
};
