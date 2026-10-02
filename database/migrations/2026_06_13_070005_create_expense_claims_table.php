<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('expense_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->comment('Staff who submitted');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference', 60)->unique();
            $table->string('title', 200);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->enum('status', ['draft','submitted','approved','rejected','paid'])->default('draft');
            $table->date('submitted_at')->nullable();
            $table->date('paid_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('expense_claim_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_claim_id')->constrained()->cascadeOnDelete();
            $table->string('description', 300);
            $table->date('expense_date');
            $table->string('category', 100)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('receipt_path', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('expense_claim_items');
        Schema::dropIfExists('expense_claims');
    }
};
