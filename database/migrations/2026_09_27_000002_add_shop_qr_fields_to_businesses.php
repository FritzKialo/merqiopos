<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The shop QR code: how many visits arrived by scanning it, and whether the
// receipt should print it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->unsignedInteger('shop_qr_scans')->default(0);
            $table->boolean('show_shop_qr_on_receipt')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['shop_qr_scans', 'show_shop_qr_on_receipt']);
        });
    }
};
