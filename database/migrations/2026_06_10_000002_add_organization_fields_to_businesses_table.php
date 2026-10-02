<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('id')
                ->constrained('organizations')
                ->onDelete('cascade');

            $table->string('business_type')->nullable()->after('industry');

            // JSON config for payroll: enabled deductions, pay cycle, employer KRA PIN
            $table->json('payroll_settings')->nullable()->after('business_type');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn(['organization_id', 'business_type', 'payroll_settings']);
        });
    }
};
