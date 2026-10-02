<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('stock_adjustments', function (
            Blueprint $table
        ) {
            $table->id();
            $table->foreignId('business_id')
                ->constrained()->onDelete('cascade');
            $table->foreignId('product_id')
                ->constrained()->onDelete('cascade');
            $table->foreignId('user_id')
                ->constrained()->onDelete('cascade');
            // 'return_in' is also written by real code (SaleReturnController,
            // StockTransferController) but was missing here originally — only ever
            // added later via a MySQL-only ALTER (2026_08_16_000003_fix_stock_
            // adjustments_type_enum.php). sqlite bakes an enum() into a CHECK
            // constraint at CREATE TIME with no portable way to widen it after the
            // fact, so it has to be right here from the start; that later migration's
            // ALTER still runs on MySQL too, just reasserting the same list.
            $table->enum('type', [
                'addition', 'deduction', 'correction', 'return_in'
            ]);
            $table->integer('quantity_before');
            $table->integer('quantity_change');
            $table->integer('quantity_after');
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->string('reference')->nullable();
            $table->timestamps();

            // ── Indexes ────────────────────────────────────
            $table->index('business_id');
            $table->index('product_id');
            $table->index(
                ['business_id', 'created_at'],
                'stock_adjustments_business_date_idx'
            );
        });
    }

    public function down(): void {
        Schema::dropIfExists('stock_adjustments');
    }
};
