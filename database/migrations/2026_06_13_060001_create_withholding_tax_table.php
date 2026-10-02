<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withholding_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payee_name', 200);
            $table->string('payee_kra_pin', 20)->nullable();
            $table->enum('wht_type', ['consultancy','rent','management_fee','royalty','interest','dividend','other'])->default('consultancy');
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('wht_rate', 5, 2)->comment('e.g. 5, 10, 15, 20');
            $table->decimal('wht_amount', 12, 2);
            $table->decimal('net_amount', 12, 2);
            $table->date('payment_date');
            $table->string('certificate_number', 50)->nullable();
            $table->string('period_month')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withholding_taxes');
    }
};
