<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('businesses', function (Blueprint $table) {
            $table->boolean('portal_enabled')->default(true)->after('whatsapp_enabled');
            $table->text('portal_welcome_message')->nullable()->after('portal_enabled');
        });
    }

    public function down(): void {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['portal_enabled', 'portal_welcome_message']);
        });
    }
};
