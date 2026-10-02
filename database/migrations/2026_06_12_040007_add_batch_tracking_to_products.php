<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('track_batches')->default(false)->after('has_variants');
            $table->integer('expiry_alert_days')->default(30)->after('track_batches');
        });
    }

    public function down(): void {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['track_batches', 'expiry_alert_days']);
        });
    }
};
