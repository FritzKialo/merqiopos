<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'image')) {
                $table->string('image', 255)->nullable()->after('description');
            }
            if (!Schema::hasColumn('products', 'brand')) {
                $table->string('brand', 100)->nullable()->after('category_id');
            }
            if (!Schema::hasColumn('products', 'barcode_symbology')) {
                $table->string('barcode_symbology', 30)->default('CODE128')->after('barcode');
            }
            if (!Schema::hasColumn('products', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('status');
            }
            if (!Schema::hasColumn('products', 'hide_in_pos')) {
                $table->boolean('hide_in_pos')->default(false)->after('is_featured');
            }
            if (!Schema::hasColumn('products', 'hide_in_shop')) {
                $table->boolean('hide_in_shop')->default(false)->after('hide_in_pos');
            }
            // Buy unit vs sell unit — stock_qty stays in the existing `unit`
            // column's meaning (the sell unit, e.g. "piece") everywhere it
            // already is (POS, reports, low-stock alerts) — nothing about
            // existing stock math changes. buy_unit/units_per_buy_unit are
            // purely for the receiving side: "1 Carton = 24 pieces" lets
            // Stock Receive accept a quantity in the buy unit and convert
            // it to sell-unit pieces before touching stock_qty, instead of
            // the recorder doing that arithmetic by hand.
            if (!Schema::hasColumn('products', 'buy_unit')) {
                $table->string('buy_unit', 40)->nullable()->after('unit');
            }
            if (!Schema::hasColumn('products', 'units_per_buy_unit')) {
                $table->unsignedInteger('units_per_buy_unit')->default(1)->after('buy_unit');
            }
        });

        // Sub-category — self-referencing, one level deep (a sub-category's
        // own parent_id is always null; nothing here builds or expects a
        // deeper tree than that).
        if (!Schema::hasColumn('categories', 'parent_id')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->foreignId('parent_id')->nullable()->after('business_id')
                    ->constrained('categories')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('product_images')) {
            Schema::create('product_images', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->string('path', 255);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('product_suppliers')) {
            Schema::create('product_suppliers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
                $table->string('supplier_part_no', 100)->nullable();
                $table->decimal('supplier_price', 12, 2)->nullable();
                $table->timestamps();
                $table->unique(['product_id', 'supplier_id']);
            });
        }
    }

    public function down(): void {
        Schema::dropIfExists('product_suppliers');
        Schema::dropIfExists('product_images');

        if (Schema::hasColumn('categories', 'parent_id')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropConstrainedForeignId('parent_id');
            });
        }

        Schema::table('products', function (Blueprint $table) {
            $cols = ['image', 'brand', 'barcode_symbology', 'is_featured', 'hide_in_pos', 'hide_in_shop', 'buy_unit', 'units_per_buy_unit'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('products', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
