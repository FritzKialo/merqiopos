<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('staff_profiles', 'helb_deduction')) {
            Schema::table('staff_profiles', function (Blueprint $table) {
                $table->decimal('helb_deduction', 10, 2)->default(0);
                $table->string('helb_account_number', 50)->nullable();
            });
        }
        if (!Schema::hasColumn('payroll_items', 'helb')) {
            Schema::table('payroll_items', function (Blueprint $table) {
                $table->decimal('helb', 10, 2)->default(0)->after('shif_employee');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('staff_profiles', 'helb_deduction')) {
            Schema::table('staff_profiles', function (Blueprint $table) {
                $table->dropColumn(['helb_deduction', 'helb_account_number']);
            });
        }
        if (Schema::hasColumn('payroll_items', 'helb')) {
            Schema::table('payroll_items', function (Blueprint $table) {
                $table->dropColumn('helb');
            });
        }
    }
};
