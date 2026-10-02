<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('businesses', 'allow_unverified_mpesa_codes')) {
            Schema::table('businesses', function (Blueprint $table) {
                // true = current behaviour: anyone may confirm a hand-typed code
                // (unverified ones are flagged on the sale).
                $table->boolean('allow_unverified_mpesa_codes')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('businesses', 'allow_unverified_mpesa_codes')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->dropColumn('allow_unverified_mpesa_codes');
            });
        }
    }
};
