<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── Sales indexes ─────────────────────────────────────────────────────
        Schema::table('sales', function (Blueprint $table) {
            $table->index('sale_status');
            $table->index('payment_status');
            $table->index('created_at');
            // Compound index for dashboard aggregations
            $table->index(
                ['business_id', 'sale_status', 'created_at'],
                'sales_business_status_date_idx'
            );
        });

        // ── Expenses indexes ──────────────────────────────────────────────────
        Schema::table('expenses', function (Blueprint $table) {
            $table->index('expense_date');
            $table->index(
                ['business_id', 'expense_date'],
                'expenses_business_date_idx'
            );
        });

        // ── Products indexes ──────────────────────────────────────────────────
        Schema::table('products', function (Blueprint $table) {
            $table->index('status');
            // Covers low stock queries: WHERE business_id = ? AND status = 'active'
            //   AND stock_qty <= reorder_level
            $table->index(
                ['business_id', 'status'],
                'products_business_status_idx'
            );
        });

        // ── Customers indexes ─────────────────────────────────────────────────
        Schema::table('customers', function (Blueprint $table) {
            // Covers withDebt() scope: WHERE balance_owed > 0
            $table->index('balance_owed');
        });

        // ── Audit logs compound index ─────────────────────────────────────────
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(
                ['business_id', 'event', 'created_at'],
                'audit_logs_business_event_date_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['sale_status']);
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['created_at']);
            $table->dropIndex('sales_business_status_date_idx');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['expense_date']);
            $table->dropIndex('expenses_business_date_idx');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex('products_business_status_idx');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['balance_owed']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_business_event_date_idx');
        });
    }
};
