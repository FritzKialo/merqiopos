<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->date('period_start');
            $table->date('period_end');

            // Snapshot of the pay cycle used when this period was created
            $table->enum('pay_cycle', ['weekly', 'bi_weekly', 'monthly'])
                  ->default('monthly');

            // draft → approved → paid
            $table->enum('status', ['draft', 'approved', 'paid'])
                  ->default('draft');

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            // Summary totals (updated when items are calculated)
            $table->decimal('total_gross',    14, 2)->unsigned()->default(0);
            $table->decimal('total_nssf_ee',  14, 2)->unsigned()->default(0); // employee NSSF
            $table->decimal('total_nssf_er',  14, 2)->unsigned()->default(0); // employer NSSF
            $table->decimal('total_shif_ee',  14, 2)->unsigned()->default(0); // employee SHIF
            $table->decimal('total_shif_er',  14, 2)->unsigned()->default(0); // employer SHIF
            $table->decimal('total_paye',     14, 2)->unsigned()->default(0);
            $table->decimal('total_net',      14, 2)->unsigned()->default(0);

            $table->text('notes')->nullable();

            $table->timestamps();

            // One period per business per date range
            $table->unique(['business_id', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};
