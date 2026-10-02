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
        // Track whether C2B URLs have been registered with Safaricom for this business
        Schema::table('businesses', function (Blueprint $table) {
            $table->boolean('mpesa_c2b_registered')->default(false)->after('mpesa_environment');
        });

        Schema::table('mpesa_transactions', function (Blueprint $table) {
            // Which business received this payment (needed for C2B shortcode matching)
            $table->foreignId('business_id')
                  ->nullable()
                  ->constrained('businesses')
                  ->nullOnDelete()
                  ->after('id');

            // For C2B: account reference the customer typed (invoice number for Paybill)
            $table->string('bill_ref_number')->nullable()->after('mpesa_receipt');

            // For C2B: the shortcode that received the payment
            $table->string('shortcode')->nullable()->after('bill_ref_number');
        });

        // Widen type column to include 'c2b' without breaking existing enum values
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE mpesa_transactions MODIFY COLUMN type ENUM('sale','subscription','c2b') DEFAULT 'sale'");
        }
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('mpesa_c2b_registered');
        });

        Schema::table('mpesa_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_id');
            $table->dropColumn(['bill_ref_number', 'shortcode']);
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE mpesa_transactions MODIFY COLUMN type ENUM('sale','subscription') DEFAULT 'sale'");
        }
    }
};
