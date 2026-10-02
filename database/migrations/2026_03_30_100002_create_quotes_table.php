<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // ── quotes ─────────────────────────────────────────────────────────────
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')
                ->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')
                ->nullable()
                ->constrained()->onDelete('set null');
            $table->foreignId('user_id')
                ->constrained()->onDelete('cascade');
            $table->string('quote_number');
            $table->date('quote_date');
            $table->date('valid_until')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->enum('status', [
                'draft', 'sent', 'accepted',
                'rejected', 'expired', 'converted'
            ])->default('draft');
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->unsignedBigInteger('converted_to_sale_id')
                ->nullable();
            $table->timestamps();
            $table->softDeletes();

            // ── Indexes ────────────────────────────
            $table->index('business_id');
            $table->index('status');
            $table->index('customer_id');
            $table->unique(['business_id', 'quote_number']);
        });

        // ── quote_items ────────────────────────────────────────────────────────
        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')
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
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
    }
};
