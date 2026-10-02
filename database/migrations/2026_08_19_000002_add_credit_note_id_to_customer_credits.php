<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// CreditNote::issue() only ever reduced a linked Invoice's balance_due — a
// credit note issued directly against a customer (no invoice_id, a real,
// separately-selectable option on the create form) did nothing at all: no
// change to Customer.credit_balance, no ledger entry. The document got
// created and marked "issued" but never actually credited anything. Adds the
// FK needed to log this the same way SaleReturnController already does for
// refund-issued store credit.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_credits', function (Blueprint $table) {
            $table->foreignId('credit_note_id')
                ->nullable()
                ->after('sale_return_id')
                ->constrained('credit_notes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_credits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credit_note_id');
        });
    }
};
