<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 50)->unique();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('KES');
            $table->enum('payment_method', ['cash','mpesa','bank','card'])->default('cash');
            $table->string('mpesa_code', 50)->nullable();
            $table->date('received_at');
            $table->text('notes')->nullable();
            $table->enum('status', ['available','partially_used','fully_used'])->default('available');
            $table->decimal('used_amount', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('deposit_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_deposit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount_used', 12, 2);
            $table->timestamp('used_at')->useCurrent();
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_usages');
        Schema::dropIfExists('customer_deposits');
    }
};
