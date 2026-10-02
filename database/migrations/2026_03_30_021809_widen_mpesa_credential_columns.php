<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen mpesa_consumer_key and mpesa_consumer_secret from VARCHAR(255) to TEXT.
     *
     * Laravel's encrypt() output is ~350 characters (base64-encoded JSON payload),
     * which exceeds the 255-char limit and causes a MySQL "Data too long" error
     * whenever M-Pesa settings are saved. TEXT has no such limit.
     * mpesa_passkey was already TEXT, so it is unaffected.
     */
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->text('mpesa_consumer_key')->nullable()->change();
            $table->text('mpesa_consumer_secret')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('mpesa_consumer_key')->nullable()->change();
            $table->string('mpesa_consumer_secret')->nullable()->change();
        });
    }
};
