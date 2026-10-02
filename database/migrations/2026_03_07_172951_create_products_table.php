<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('products', function (
            Blueprint $table
        ) {
            $table->id();
            $table->foreignId('business_id')
                ->constrained()->onDelete('cascade');
            $table->foreignId('category_id')
                ->nullable()
                ->constrained()->onDelete('set null');
            $table->string('name');
            $table->string('sku')->nullable();
            $table->text('description')->nullable();
            $table->decimal('buying_price', 12, 2)
                ->default(0);
            $table->decimal('selling_price', 12, 2)
                ->default(0);
            $table->integer('stock_qty')->default(0);
            $table->integer('reorder_level')->default(5);
            $table->string('unit')->default('piece');
            $table->enum('status', ['active', 'inactive'])
                ->default('active');
            $table->timestamps();
            $table->unique(['business_id', 'sku']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('products');
    }
};