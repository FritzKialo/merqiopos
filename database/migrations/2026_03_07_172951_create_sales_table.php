<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sales', function (
            Blueprint $table
        ) {
            $table->id();
            $table->foreignId('business_id')
                ->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')
                ->nullable()
                ->constrained()->onDelete('set null');
            $table->foreignId('user_id')
                ->constrained()->onDelete('cascade');
            $table->string('invoice_number')->unique();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)
                ->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance_due', 12, 2)->default(0);
            $table->enum('payment_method', [
                'cash', 'mpesa',
                'bank_transfer', 'credit'
            ])->default('cash');
            $table->string('mpesa_reference')->nullable();
            $table->enum('payment_status', [
                'paid', 'partial', 'unpaid'
            ])->default('unpaid');
            $table->enum('sale_status', [
                'completed', 'cancelled'
            ])->default('completed');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('sales');
    }
};