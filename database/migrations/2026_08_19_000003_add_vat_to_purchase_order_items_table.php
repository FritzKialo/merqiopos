<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Purchase orders have never captured input VAT: purchase_orders.tax_amount
    // exists but PurchaseOrderController::store() has always hardcoded it to 0,
    // and purchase_order_items has no VAT columns at all — there was nowhere
    // to even enter a rate. This adds the same vat_rate/vat_amount pair
    // invoice_items already uses, so input VAT can finally be captured per
    // line item the same way output VAT already is on invoices/sales.
    public function up(): void {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('vat_rate', 5, 2)->default(0)->after('unit_cost');   // e.g. 16.00 for 16%
            $table->decimal('vat_amount', 12, 2)->default(0)->after('subtotal');
        });
    }

    public function down(): void {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn(['vat_rate', 'vat_amount']);
        });
    }
};
