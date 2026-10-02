<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->string('mpesa_conversation_id')->nullable()->after('status');
            $table->integer('mpesa_result_code')->nullable()->after('mpesa_conversation_id');
            $table->string('mpesa_result_desc')->nullable()->after('mpesa_result_code');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn(['mpesa_conversation_id', 'mpesa_result_code', 'mpesa_result_desc']);
        });
    }
};
