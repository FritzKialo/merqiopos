<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->enum('period_type', ['monthly', 'quarterly', 'yearly'])->default('monthly');
            $table->integer('year');
            $table->integer('month')->nullable(); // 1-12
            $table->integer('quarter')->nullable(); // 1-4
            $table->decimal('amount', 14, 2);
            $table->timestamps();

            $table->unique(['business_id', 'expense_category_id', 'year', 'month'], 'budgets_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('budgets');
    }
};
