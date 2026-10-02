<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Restaurant / food-point billing: the bill printed before payment, the guest
// name, what was handed over and the change given, the link to the finished
// sale (so its receipt can be reprinted), and which items the kitchen has
// already been sent.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_orders', function (Blueprint $table) {
            $table->string('customer_name', 100)->nullable()->after('customer_id');
            $table->unsignedBigInteger('sale_id')->nullable()->after('customer_name');
            $table->decimal('amount_tendered', 12, 2)->nullable()->after('total_amount');
            $table->string('payment_method', 20)->nullable()->after('amount_tendered');
            $table->timestamp('billed_at')->nullable()->after('closed_at');
            $table->index('sale_id');
        });

        Schema::table('table_order_items', function (Blueprint $table) {
            $table->timestamp('sent_to_kitchen_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('table_order_items', function (Blueprint $table) {
            $table->dropColumn('sent_to_kitchen_at');
        });

        Schema::table('table_orders', function (Blueprint $table) {
            $table->dropIndex(['sale_id']);
            $table->dropColumn(['customer_name', 'sale_id', 'amount_tendered', 'payment_method', 'billed_at']);
        });
    }
};
