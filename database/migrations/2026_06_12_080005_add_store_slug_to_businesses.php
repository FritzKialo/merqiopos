<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('store_slug', 100)->unique()->nullable()->after('name');
            $table->boolean('store_public')->default(false)->after('store_slug');
            $table->text('store_description')->nullable()->after('store_public');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['store_slug', 'store_public', 'store_description']);
        });
    }
};
