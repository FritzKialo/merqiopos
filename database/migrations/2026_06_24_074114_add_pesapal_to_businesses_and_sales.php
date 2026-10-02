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
        // Per-business Pesapal credentials
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('pesapal_consumer_key')->nullable()->after('mpesa_c2b_registered');
            $table->string('pesapal_consumer_secret')->nullable()->after('pesapal_consumer_key');
            $table->string('pesapal_environment')->default('sandbox')->after('pesapal_consumer_secret');
            $table->string('pesapal_ipn_id')->nullable()->after('pesapal_environment');
        });

        // Add 'card' and 'pesapal' to sales payment_method enum
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE sales MODIFY COLUMN payment_method ENUM('cash','mpesa','bank_transfer','credit','card','pesapal') DEFAULT 'cash'");
        }

        // Pesapal transactions table (one per sale payment attempt)
        Schema::create('pesapal_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_tracking_id')->nullable()->index();  // Pesapal's reference
            $table->string('merchant_reference')->unique();             // Our reference
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('KES');
            $table->enum('status', ['PENDING', 'COMPLETE', 'FAILED', 'INVALID'])->default('PENDING');
            $table->string('payment_method')->nullable();               // MPESA, VISA, etc. from Pesapal
            $table->string('confirmation_code')->nullable();            // Pesapal receipt
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pesapal_transactions');

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE sales MODIFY COLUMN payment_method ENUM('cash','mpesa','bank_transfer','credit') DEFAULT 'cash'");
        }

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['pesapal_consumer_key', 'pesapal_consumer_secret', 'pesapal_environment', 'pesapal_ipn_id']);
        });
    }
};
