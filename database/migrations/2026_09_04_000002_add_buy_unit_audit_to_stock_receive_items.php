<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('stock_receive_items', function (Blueprint $table) {
            // quantity_received/unit_cost stay in the product's sell unit,
            // exactly as before — nothing downstream (reports, PO
            // reconciliation, stock_qty increment) needs to change. These
            // two columns just remember what was actually typed in, for
            // the receipt's own display — "3 Cartons (72 pieces)" instead
            // of only ever showing the converted 72.
            if (!Schema::hasColumn('stock_receive_items', 'received_unit')) {
                $table->string('received_unit', 40)->nullable()->after('unit_cost');
            }
            if (!Schema::hasColumn('stock_receive_items', 'received_qty_in_unit')) {
                $table->integer('received_qty_in_unit')->nullable()->after('received_unit');
            }
        });
    }

    public function down(): void {
        Schema::table('stock_receive_items', function (Blueprint $table) {
            foreach (['received_unit', 'received_qty_in_unit'] as $col) {
                if (Schema::hasColumn('stock_receive_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
