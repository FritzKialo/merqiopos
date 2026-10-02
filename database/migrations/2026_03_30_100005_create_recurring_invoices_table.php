<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // ── recurring_invoices ─────────────────────────────────────────────────
        Schema::create('recurring_invoices', function (
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
            $table->string('title');
            $table->enum('frequency', [
                'weekly', 'monthly', 'quarterly', 'yearly'
            ])->default('monthly');
            $table->date('next_run_date');
            $table->date('last_run_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->integer('run_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // ── recurring_invoice_items ────────────────────────────────────────────
        Schema::create('recurring_invoice_items', function (
            Blueprint $table
        ) {
            $table->id();
            $table->foreignId('recurring_invoice_id')
                ->constrained()->onDelete('cascade');
            $table->foreignId('product_id')
                ->nullable()
                ->constrained()->onDelete('set null');
            $table->string('product_name');
            $table->text('description')->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->integer('quantity')->default(1);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('recurring_invoice_items');
        Schema::dropIfExists('recurring_invoices');
    }
};
