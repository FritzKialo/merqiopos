<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Restaurant round three: merging two tables' orders into one, a per-line discount
// on a table order item, and QR self-ordering (a customer scans their table's own
// code, browses the menu, and sends a request a staff member approves onto the
// real order — never writing the live order directly).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_orders', function (Blueprint $table) {
            // Which order this one's items were folded into, when two tables are merged
            // (e.g. a big party spilling across two tables). Kept for the history/receipt
            // trail rather than deleting the merged-away order outright.
            $table->unsignedBigInteger('merged_into_id')->nullable()->after('sale_id')->index();
        });

        // 'merged' alongside the existing open/billed/paid/cancelled — MySQL-only ENUM
        // widening (see the same pattern elsewhere in this codebase, e.g.
        // 2026_08_16_000003_fix_stock_adjustments_type_enum.php); sqlite has no enum
        // type to widen at all, so there's nothing to do there.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE table_orders MODIFY COLUMN status ENUM('open','billed','paid','cancelled','merged') NOT NULL DEFAULT 'open'");
        }

        Schema::table('table_order_items', function (Blueprint $table) {
            // A flat KSh amount off this one line — e.g. a manager comping a dish, or a
            // happy-hour price — same shape as sale_items.discount.
            $table->decimal('discount', 12, 2)->default(0)->after('unit_price');
        });

        Schema::table('restaurant_tables', function (Blueprint $table) {
            // An unguessable per-table code for the QR self-ordering page — never the
            // numeric id, so a customer can't just increment it to see another table's order.
            $table->string('qr_token', 40)->nullable()->unique()->after('sort_order');
        });

        // A customer's scan-to-order request, held for staff to approve before it becomes
        // a real line on the bill and reaches the kitchen — the same moderate-then-apply
        // pattern the online shop and product waitlist already use, so a customer's phone
        // never writes directly into a live order.
        Schema::create('table_order_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_table_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name', 200);
            $table->decimal('quantity', 8, 2);
            $table->string('notes', 255)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['restaurant_table_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_order_requests');

        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->dropColumn('qr_token');
        });

        Schema::table('table_order_items', function (Blueprint $table) {
            $table->dropColumn('discount');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE table_orders MODIFY COLUMN status ENUM('open','billed','paid','cancelled') NOT NULL DEFAULT 'open'");
        }

        Schema::table('table_orders', function (Blueprint $table) {
            $table->dropColumn('merged_into_id');
        });
    }
};
