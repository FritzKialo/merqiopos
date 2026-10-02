<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('businesses', function (Blueprint $table) {
            $table->boolean('whatsapp_enabled')->default(false)->after('sms_sender_id');
        });
    }

    public function down(): void {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('whatsapp_enabled');
        });
    }
};
