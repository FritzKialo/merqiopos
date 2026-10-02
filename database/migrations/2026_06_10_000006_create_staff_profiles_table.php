<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();

            // Who and where
            $table->foreignId('user_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->foreignId('business_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // Compensation
            $table->enum('pay_type', ['retainer', 'commission', 'hybrid'])
                  ->default('retainer');
            $table->decimal('retainer_amount', 12, 2)->unsigned()
                  ->default(0);                          // monthly base salary
            $table->decimal('commission_rate', 5, 2)->unsigned()
                  ->default(0);                          // percentage of sales (0–100)

            // Statutory IDs — nullable; not every business is registered
            $table->string('kra_pin',   20)->nullable();   // for PAYE / P9
            $table->string('nssf_no',   20)->nullable();
            $table->string('shif_no',   20)->nullable();   // formerly NHIF
            $table->string('id_number', 20)->nullable();   // national ID / passport

            // HR details
            $table->string('job_title',  100)->nullable();
            $table->string('department', 100)->nullable();
            $table->date('employment_date')->nullable();
            $table->date('termination_date')->nullable();

            // Per-employee deduction overrides (null = follow business payroll_settings)
            // Stored as JSON: {"paye":true,"nssf":false,"shif":true}
            $table->json('deduction_overrides')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'business_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
    }
};
