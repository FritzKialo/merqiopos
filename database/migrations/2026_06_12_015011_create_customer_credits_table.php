<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // credit_limit and outstanding per customer per business
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('credit_limit', 12, 2)->default(0)->after('email');
            $table->decimal('credit_balance', 12, 2)->default(0)->after('credit_limit'); // amount owed to business
        });

        // ledger: every credit sale, repayment, or store-credit issuance
        Schema::create('customer_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_return_id')->nullable()->constrained()->nullOnDelete();
            // debit = customer owes more (credit sale), credit = customer paid/received store credit
            $table->enum('type', ['credit_sale', 'repayment', 'store_credit', 'adjustment']);
            $table->decimal('amount', 12, 2);          // always positive
            $table->decimal('balance_after', 12, 2);   // running balance
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_credits');
        Schema::table('customers', function (\Illuminate\Database\Schema\Blueprint $t) {
            $t->dropColumn(['credit_limit', 'credit_balance']);
        });
    }
};
