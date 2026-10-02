<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasColumn('online_orders', 'delivery_fee')) {
            Schema::table('online_orders', function (Blueprint $table) {
                $table->decimal('delivery_fee', 10, 2)->default(0)->after('subtotal');
            });
        }
        if (!Schema::hasColumn('businesses', 'delivery_fee')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->decimal('delivery_fee', 10, 2)->default(0);
                $table->text('delivery_zones')->nullable()->comment('JSON array of zone configs');
            });
        }
    }

    public function down(): void {
        if (Schema::hasColumn('online_orders', 'delivery_fee')) {
            Schema::table('online_orders', function (Blueprint $table) {
                $table->dropColumn('delivery_fee');
            });
        }
        if (Schema::hasColumn('businesses', 'delivery_fee')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->dropColumn(['delivery_fee', 'delivery_zones']);
            });
        }
    }
};
