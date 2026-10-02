<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('businesses', function (Blueprint $table) {
            $table->boolean('enable_digital_float')->default(false)->after('vat_rate');
            $table->decimal('default_credit_limit', 12, 2)->default(0)->after('enable_digital_float');
        });
    }

    public function down(): void {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['enable_digital_float', 'default_credit_limit']);
        });
    }
};
