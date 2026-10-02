<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Carries the entered code through the async payment round-trip
        // (STK push / Paystack hosted checkout) so the webhook/callback that
        // actually activates the subscription knows which code to redeem.
        Schema::table('mpesa_transactions', function (Blueprint $table) {
            $table->string('promo_code')->nullable()->after('api_ref');
        });

        Schema::table('paystack_transactions', function (Blueprint $table) {
            $table->string('promo_code')->nullable()->after('plan');
        });
    }

    public function down(): void
    {
        Schema::table('mpesa_transactions', function (Blueprint $table) {
            $table->dropColumn('promo_code');
        });

        Schema::table('paystack_transactions', function (Blueprint $table) {
            $table->dropColumn('promo_code');
        });
    }
};
