<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('sms_provider')->nullable();
            $table->text('sms_api_key')->nullable();
            $table->string('sms_username')->nullable();
            $table->string('sms_sender_id')->nullable();
        });
    }

    public function down(): void {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'sms_provider',
                'sms_api_key',
                'sms_username',
                'sms_sender_id',
            ]);
        });
    }
};
