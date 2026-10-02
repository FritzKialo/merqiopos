<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_items', 'housing_levy_employee')) {
                $table->decimal('housing_levy_employee', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('payroll_items', 'housing_levy_employer')) {
                $table->decimal('housing_levy_employer', 10, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn(['housing_levy_employee', 'housing_levy_employer']);
        });
    }
};
