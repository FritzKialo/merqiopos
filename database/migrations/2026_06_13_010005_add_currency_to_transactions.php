<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'currency')) {
                $table->string('currency', 3)->default('KES');
                $table->decimal('exchange_rate', 10, 4)->default(1);
            }
        });

        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'currency')) {
                $table->string('currency', 3)->default('KES');
                $table->decimal('exchange_rate', 10, 4)->default(1);
            }
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'currency')) {
                $table->string('currency', 3)->default('KES');
                $table->decimal('exchange_rate', 10, 4)->default(1);
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['currency', 'exchange_rate']);
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['currency', 'exchange_rate']);
        });
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['currency', 'exchange_rate']);
        });
    }
};
