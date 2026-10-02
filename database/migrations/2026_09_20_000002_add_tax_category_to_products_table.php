<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('products', function (Blueprint $table) {
            // Nullable = "use the business default (standard)". Left the
            // existing vat_rate column untouched — it was never actually
            // wired into any tax calculation (Product::effectiveVatRate()
            // is dead code, confirmed unused anywhere in the app), so there
            // is no real data to migrate off of it.
            $table->enum('tax_category', ['standard', 'reduced', 'zero_rated', 'exempt'])
                ->nullable()
                ->after('vat_rate');
        });
    }

    public function down(): void {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('tax_category');
        });
    }
};
