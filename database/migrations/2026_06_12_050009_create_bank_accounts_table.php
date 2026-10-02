<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('account_number', 50)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->decimal('current_balance', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('bank_statement_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('filename', 255);
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamp('imported_at');
            $table->decimal('total_credits', 14, 2)->default(0);
            $table->decimal('total_debits', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_statement_import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->date('transaction_date');
            $table->string('description', 500);
            $table->string('reference', 100)->nullable();
            $table->decimal('debit', 14, 2)->nullable();
            $table->decimal('credit', 14, 2)->nullable();
            $table->decimal('balance', 14, 2)->nullable();
            $table->string('matched_type', 50)->nullable();
            $table->unsignedBigInteger('matched_id')->nullable();
            $table->boolean('is_reconciled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_statement_imports');
        Schema::dropIfExists('bank_accounts');
    }
};
