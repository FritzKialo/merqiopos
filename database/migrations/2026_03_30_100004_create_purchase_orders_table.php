<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // ── purchase_orders ────────────────────────────────────────────────────
        Schema::create('purchase_orders', function (
            Blueprint $table
        ) {
            $table->id();
            $table->foreignId('business_id')
                ->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')
                ->nullable()
                ->constrained()->onDelete('set null');
            $table->foreignId('user_id')
                ->constrained()->onDelete('cascade');
            $table->string('po_number');
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->date('received_date')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->enum('status', [
                'draft', 'ordered', 'partially_received',
                'received', 'cancelled'
            ])->default('draft');
            $table->enum('payment_status', [
                'unpaid', 'partial', 'paid'
            ])->default('unpaid');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // ── Indexes ────────────────────────────
            $table->index('business_id');
            $table->index('status');
            $table->index('supplier_id');
        });

        // ── purchase_order_items ───────────────────────────────────────────────
        Schema::create('purchase_order_items', function (
            Blueprint $table
        ) {
            $table->id();
            $table->foreignId('purchase_order_id')
                ->constrained()->onDelete('cascade');
            $table->foreignId('product_id')
                ->nullable()
                ->constrained()->onDelete('set null');
            $table->string('product_name');
            $table->integer('quantity_ordered');
            $table->integer('quantity_received')->default(0);
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
