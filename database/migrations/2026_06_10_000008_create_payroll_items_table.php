<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payroll_period_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->foreignId('user_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->foreignId('business_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // Snapshot of pay configuration at calculation time
            $table->enum('pay_type', ['retainer', 'commission', 'hybrid']);
            $table->decimal('retainer_amount',  12, 2)->unsigned()->default(0);
            $table->decimal('commission_rate',   5, 2)->unsigned()->default(0);
            $table->decimal('commission_sales', 14, 2)->unsigned()->default(0); // total sales in period
            $table->decimal('gross_pay',        14, 2)->unsigned()->default(0);

            // Deductions (employee side)
            $table->decimal('nssf_employee',  10, 2)->unsigned()->default(0);
            $table->decimal('shif_employee',  10, 2)->unsigned()->default(0);
            $table->decimal('paye',           10, 2)->unsigned()->default(0);
            $table->decimal('total_deductions', 10, 2)->unsigned()->default(0);

            // Employer cost (informational, not deducted from employee)
            $table->decimal('nssf_employer',  10, 2)->unsigned()->default(0);
            $table->decimal('shif_employer',  10, 2)->unsigned()->default(0);

            $table->decimal('net_pay', 14, 2)->unsigned()->default(0);

            // Full deduction breakdown for audit / P9 / payslip
            $table->json('deduction_details')->nullable();

            // pending → paid
            $table->enum('status', ['pending', 'paid'])->default('pending');

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['payroll_period_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
