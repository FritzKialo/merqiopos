<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->boolean('vat_registered')->default(false)->after('kra_pin');
            $table->string('vat_number', 30)->nullable()->after('vat_registered');
            $table->decimal('vat_rate', 5, 2)->default(16.00)->after('vat_number'); // Kenya standard 16%
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['vat_registered', 'vat_number', 'vat_rate']);
        });
    }
};
