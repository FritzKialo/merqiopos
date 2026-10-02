<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mpesa_transactions', function (Blueprint $table) {
            // Drop the existing non-nullable FK on sale_id
            $table->dropForeign(['sale_id']);

            // Make sale_id nullable (subscription payments have no sale)
            $table->unsignedBigInteger('sale_id')
                  ->nullable()
                  ->change();

            // Re-add FK with nullOnDelete
            $table->foreign('sale_id')
                  ->references('id')
                  ->on('sales')
                  ->nullOnDelete();

            // Distinguish between sale payments and subscription payments
            $table->enum('type', ['sale', 'subscription'])
                  ->default('sale')
                  ->after('sale_id');

            // Link to subscriptions table once payment completes
            $table->foreignId('subscription_id')
                  ->nullable()
                  ->constrained('subscriptions')
                  ->nullOnDelete()
                  ->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('mpesa_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_id');
            $table->dropColumn('type');

            $table->dropForeign(['sale_id']);
            $table->unsignedBigInteger('sale_id')
                  ->nullable(false)
                  ->change();
            $table->foreign('sale_id')
                  ->references('id')
                  ->on('sales')
                  ->cascadeOnDelete();
        });
    }
};
