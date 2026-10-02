<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('price_tier_id')->nullable()->constrained('price_tiers')->nullOnDelete()->after('notes');
        });
    }

    public function down(): void {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['price_tier_id']);
            $table->dropColumn('price_tier_id');
        });
    }
};
