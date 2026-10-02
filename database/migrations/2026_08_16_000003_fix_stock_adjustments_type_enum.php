<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Two separate migrations tried to create `stock_adjustments`:
 *   2026_03_30_100001 — actually creates the table, type enum:
 *       ('addition','deduction','correction')
 *   2026_06_12_015002 — guarded by `if (Schema::hasTable(...)) return;`,
 *       so on any environment where migrations ran in order (every real
 *       environment) this one is a silent no-op and its intended enum
 *       ('correction','damage','theft','wastage','recount','return_in')
 *       never actually applied.
 *
 * Net effect: the live `type` column only ever accepts addition/deduction/
 * correction. But real code inserts 'return_in' (SaleReturnController,
 * StockTransferController) — which throws a raw SQL exception under MySQL
 * strict mode, breaking sale returns, sale cancellations with a paid
 * refund, and inter-branch stock-transfer returns. 'damage'/'theft'/etc.
 * turned out to be reason-field UI labels, not `type` values, so they're
 * intentionally left out here — this only adds what code actually writes.
 */
return new class extends Migration {
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("
            ALTER TABLE stock_adjustments
            MODIFY COLUMN type ENUM('addition','deduction','correction','return_in') NOT NULL
        ");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("
            ALTER TABLE stock_adjustments
            MODIFY COLUMN type ENUM('addition','deduction','correction') NOT NULL
        ");
        }
    }
};
