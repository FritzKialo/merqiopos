<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasColumn('loyalty_programs', 'tiers')) {
            Schema::table('loyalty_programs', function (Blueprint $table) {
                $table->json('tiers')->nullable()->after('points_per_shilling');
            });
        }
        if (!Schema::hasColumn('customers', 'loyalty_tier')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('loyalty_tier', 50)->nullable()->after('loyalty_points');
            });
        }
    }

    public function down(): void {
        Schema::table('loyalty_programs', function (Blueprint $table) {
            $table->dropColumn('tiers');
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('loyalty_tier');
        });
    }
};
