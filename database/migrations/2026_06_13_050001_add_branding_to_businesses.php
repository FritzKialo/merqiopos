<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            if (!Schema::hasColumn('businesses', 'receipt_header')) {
                $table->string('receipt_header', 500)->nullable();
                $table->string('receipt_footer', 500)->nullable();
                $table->string('receipt_color', 7)->default('#000000');
                $table->string('invoice_terms', 1000)->nullable();
                $table->string('invoice_bank_details', 500)->nullable();
                $table->boolean('show_logo_on_receipt')->default(true);
                $table->boolean('show_logo_on_invoice')->default(true);
                $table->string('receipt_tagline', 200)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'receipt_header', 'receipt_footer', 'receipt_color',
                'invoice_terms', 'invoice_bank_details',
                'show_logo_on_receipt', 'show_logo_on_invoice', 'receipt_tagline',
            ]);
        });
    }
};
