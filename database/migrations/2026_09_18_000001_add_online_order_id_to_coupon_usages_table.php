<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->unsignedBigInteger('online_order_id')->nullable()->after('invoice_id');
        });
    }

    public function down(): void {
        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->dropColumn('online_order_id');
        });
    }
};
