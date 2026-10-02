<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Products are independent per-store rows with no shared identifier across
 * stores — the same conceptual item has a completely different id at each
 * branch. receive() was looking up the destination store's stock using the
 * SOURCE store's product_id, which essentially never matches a real product
 * at the destination — the increment silently never ran, and the transfer
 * still completed as "received". Net effect: every inter-branch transfer
 * decremented the source correctly and then permanently lost that stock.
 *
 * Fix: capture an explicit destination-store product at transfer-creation
 * time (a real mapping step, not automatic ID/SKU matching) and use that at
 * receive() instead of guessing.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->foreignId('dest_product_id')
                ->nullable()
                ->after('product_id')
                ->constrained('products')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dest_product_id');
        });
    }
};
