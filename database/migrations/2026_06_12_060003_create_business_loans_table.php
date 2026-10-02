<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('business_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('lender_name');
            $table->enum('loan_type', ['bank', 'sacco', 'mobile', 'personal', 'other'])->default('bank');
            $table->decimal('principal_amount', 14, 2);
            $table->decimal('interest_rate', 5, 2); // annual %
            $table->date('disbursement_date');
            $table->date('repayment_start_date');
            $table->integer('term_months');
            $table->decimal('monthly_installment', 14, 2);
            $table->decimal('outstanding_balance', 14, 2);
            $table->enum('status', ['active', 'fully_paid', 'defaulted'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('loan_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_loan_id')->constrained('business_loans')->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->decimal('principal_portion', 14, 2);
            $table->decimal('interest_portion', 14, 2);
            $table->date('payment_date');
            $table->enum('payment_method', ['cash', 'bank_transfer', 'mpesa', 'cheque']);
            $table->string('reference', 100)->nullable();
            $table->decimal('balance_after', 14, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('loan_repayments');
        Schema::dropIfExists('business_loans');
    }
};
