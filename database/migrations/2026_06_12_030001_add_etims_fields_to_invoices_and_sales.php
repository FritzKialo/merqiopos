<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('etims_cuin', 50)->nullable()->after('payment_terms');
            $table->enum('etims_status', ['pending', 'submitted', 'failed'])->nullable()->after('etims_cuin');
            $table->json('etims_response')->nullable()->after('etims_status');
            $table->timestamp('etims_submitted_at')->nullable()->after('etims_response');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->string('etims_cuin', 50)->nullable();
            $table->enum('etims_status', ['pending', 'submitted', 'failed'])->nullable();
            $table->json('etims_response')->nullable();
            $table->timestamp('etims_submitted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['etims_cuin', 'etims_status', 'etims_response', 'etims_submitted_at']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['etims_cuin', 'etims_status', 'etims_response', 'etims_submitted_at']);
        });
    }
};
