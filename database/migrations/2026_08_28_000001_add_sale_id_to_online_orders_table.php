<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Online orders previously never produced a matching Sale record —
        // paid orders were invisible in the Sales list, dashboard revenue,
        // and every report (P&L, gross margin, etc.), all of which read
        // exclusively from the sales table. This links a paid online order
        // to the Sale generated for it, and doubles as an idempotency guard
        // so a duplicate M-Pesa webhook delivery can't create a second Sale
        // for the same order.
        Schema::table('online_orders', function (Blueprint $table) {
            $table->foreignId('sale_id')->nullable()->after('status')
                ->constrained('sales')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('online_orders', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
            $table->dropColumn('sale_id');
        });
    }
};
