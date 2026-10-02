<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Restaurant round two: a service charge (a default per business, adjustable per
// order), a tip recorded separately from the sale, and split bills — each
// payment is its own sale covering only the items that guest is paying for, so
// every sale now points back at its table order and records its own service
// charge, tip, cash handed over and guest name.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->decimal('service_charge_percent', 5, 2)->default(0);
        });

        Schema::table('table_orders', function (Blueprint $table) {
            $table->decimal('service_charge_percent', 5, 2)->default(0);
            $table->decimal('service_charge_amount', 12, 2)->default(0);
        });

        Schema::table('table_order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('sale_id')->nullable()->after('sent_to_kitchen_at');
            $table->index('sale_id');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedBigInteger('table_order_id')->nullable();
            $table->decimal('service_charge_amount', 12, 2)->default(0);
            $table->decimal('tip_amount', 12, 2)->default(0);
            $table->decimal('amount_tendered', 12, 2)->nullable();
            $table->string('table_guest_name', 100)->nullable();
            $table->index('table_order_id');
        });

        // Table sales made before this change were linked from the order side
        // (table_orders.sale_id). Copy that link — and what the guest handed
        // over and their name — onto the sale so their receipts keep working.
        // A portable per-row update rather than a MySQL-only JOIN-UPDATE (see the
        // same fix in 2026_06_10_000005) so this also runs on the sqlite test DB.
        DB::table('table_orders')->whereNotNull('sale_id')->select('id', 'sale_id', 'amount_tendered', 'customer_name')->get()
            ->each(fn ($o) => DB::table('sales')->where('id', $o->sale_id)->update([
                'table_order_id' => $o->id, 'amount_tendered' => $o->amount_tendered, 'table_guest_name' => $o->customer_name,
            ]));
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['table_order_id']);
            $table->dropColumn(['table_order_id', 'service_charge_amount', 'tip_amount', 'amount_tendered', 'table_guest_name']);
        });
        Schema::table('table_order_items', function (Blueprint $table) {
            $table->dropIndex(['sale_id']);
            $table->dropColumn('sale_id');
        });
        Schema::table('table_orders', function (Blueprint $table) {
            $table->dropColumn(['service_charge_percent', 'service_charge_amount']);
        });
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('service_charge_percent');
        });
    }
};
