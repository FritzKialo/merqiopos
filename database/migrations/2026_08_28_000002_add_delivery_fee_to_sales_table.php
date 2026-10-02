<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Online-shop checkouts can now charge a delivery fee (see
        // OnlineOrder::delivery_fee), which SaleService::createSaleFromOnlineOrder
        // folds into total_amount — but until now there was no column to
        // record WHY total_amount exceeds subtotal on the actual invoice/
        // receipt, the way discount_amount and tax_amount already do.
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('delivery_fee', 12, 2)->default(0)->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('delivery_fee');
        });
    }
};
