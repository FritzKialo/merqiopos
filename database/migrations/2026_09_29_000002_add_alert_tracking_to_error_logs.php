<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('error_logs', function (Blueprint $table) {
            // How many occurrences had already been counted the last time an alert email went
            // out for this fingerprint — lets logs:check-error-spikes alert again once it climbs
            // by another threshold's worth, without re-alerting on every single occurrence.
            $table->unsignedInteger('alerted_count')->default(0)->after('count');
            $table->timestamp('alerted_at')->nullable()->after('alerted_count');
        });
    }

    public function down(): void
    {
        Schema::table('error_logs', function (Blueprint $table) {
            $table->dropColumn(['alerted_count', 'alerted_at']);
        });
    }
};
