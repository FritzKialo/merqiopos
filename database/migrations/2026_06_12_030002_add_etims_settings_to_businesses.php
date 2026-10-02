<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->boolean('etims_enabled')->default(false)->after('vat_rate');
            $table->string('etims_device_serial', 50)->nullable()->after('etims_enabled');
            $table->string('etims_api_key', 255)->nullable()->after('etims_device_serial');
            $table->enum('etims_environment', ['sandbox', 'production'])->default('sandbox')->after('etims_api_key');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['etims_enabled', 'etims_device_serial', 'etims_api_key', 'etims_environment']);
        });
    }
};
