<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('low_stock_alert_sent_at')->nullable()->after('status');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('payment_reminder_sent_at')->nullable()->after('balance_owed');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('low_stock_alert_sent_at');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('payment_reminder_sent_at');
        });
    }
};
