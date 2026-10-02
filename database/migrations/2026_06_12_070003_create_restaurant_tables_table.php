<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Create restaurant_tables first (without current_order_id FK)
        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('number', 20);
            $table->string('name', 100)->nullable();
            $table->string('section', 100)->nullable();
            $table->integer('capacity')->default(4);
            $table->enum('status', ['available','occupied','reserved','cleaning'])->default('available');
            $table->unsignedBigInteger('current_order_id')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Then create table_orders (references restaurant_tables)
        Schema::create('table_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_table_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            // 'merged' is also written by real code (TableController::mergeTable) but was
            // only ever added later via a MySQL-only ALTER (2026_09_30_000001_add_
            // restaurant_extras.php) — sqlite bakes an enum() into a CHECK constraint at
            // CREATE TIME with no portable way to widen it after the fact (same shape as
            // stock_adjustments.type, see 2026_03_30_100001), so it has to be right here
            // from the start; that later migration's ALTER still runs on MySQL too, just
            // reasserting the same list.
            $table->enum('status', ['open','billed','paid','cancelled','merged'])->default('open');
            $table->text('notes')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->timestamps();
        });

        // Now add the FK from restaurant_tables -> table_orders
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->foreign('current_order_id')->references('id')->on('table_orders')->nullOnDelete();
        });

        // Table order items
        Schema::create('table_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('product_name', 200);
            $table->decimal('quantity', 8, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total', 12, 2);
            $table->text('notes')->nullable();
            $table->enum('status', ['pending','served','cancelled'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->dropForeign(['current_order_id']);
        });
        Schema::dropIfExists('table_order_items');
        Schema::dropIfExists('table_orders');
        Schema::dropIfExists('restaurant_tables');
    }
};
