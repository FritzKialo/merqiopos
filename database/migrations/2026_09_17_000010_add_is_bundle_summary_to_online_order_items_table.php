<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_order_items', function (Blueprint $table) {
            $table->boolean('is_bundle_summary')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('online_order_items', function (Blueprint $table) {
            $table->dropColumn('is_bundle_summary');
        });
    }
};
