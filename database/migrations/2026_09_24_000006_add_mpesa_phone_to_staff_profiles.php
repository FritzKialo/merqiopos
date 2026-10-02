<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('staff_profiles', 'mpesa_phone')) {
            Schema::table('staff_profiles', function (Blueprint $table) {
                // Where this employee's salary is sent when paid through M-Pesa.
                // Users have no phone number of their own, so payouts had nowhere to go.
                $table->string('mpesa_phone', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('staff_profiles', 'mpesa_phone')) {
            Schema::table('staff_profiles', function (Blueprint $table) {
                $table->dropColumn('mpesa_phone');
            });
        }
    }
};
