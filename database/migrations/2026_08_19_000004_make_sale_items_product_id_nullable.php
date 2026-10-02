<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Recurring invoices legitimately support product-less service/retainer
    // line items (recurring_invoice_items.product_id is already nullable),
    // but sale_items.product_id has always been NOT NULL — so any recurring
    // invoice with even one product-less item has never been generatable at
    // all, through either the manual "Run Now" path or the scheduled command.
    // Both RecurringInvoiceController::generateSale() and
    // ProcessRecurringInvoices::handle() already pass product_id through
    // (which can be null) and already null-guard the stock-decrement step —
    // they were written correctly in anticipation of this; the DB
    // constraint was the only thing actually blocking it.
    public function up(): void {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->change();
        });
    }

    public function down(): void {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable(false)->change();
        });
    }
};
