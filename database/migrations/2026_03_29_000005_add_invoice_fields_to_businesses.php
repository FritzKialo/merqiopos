<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('kra_pin', 20)->nullable()->after('logo');
            $table->text('payment_terms')->nullable()->after('kra_pin');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['kra_pin', 'payment_terms']);
        });
    }
};
