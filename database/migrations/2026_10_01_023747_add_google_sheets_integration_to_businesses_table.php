<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            // Same encrypted-column pattern as the existing mpesa_* /
            // pesapal_* credentials on this table.
            $table->text('google_sheets_access_token')->nullable();
            $table->text('google_sheets_refresh_token')->nullable();
            $table->timestamp('google_sheets_token_expires_at')->nullable();
            $table->string('google_sheets_spreadsheet_id')->nullable();
            $table->unsignedBigInteger('google_sheets_connected_by')->nullable();
            $table->timestamp('google_sheets_connected_at')->nullable();
            $table->timestamp('google_sheets_last_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'google_sheets_access_token',
                'google_sheets_refresh_token',
                'google_sheets_token_expires_at',
                'google_sheets_spreadsheet_id',
                'google_sheets_connected_by',
                'google_sheets_connected_at',
                'google_sheets_last_synced_at',
            ]);
        });
    }
};
