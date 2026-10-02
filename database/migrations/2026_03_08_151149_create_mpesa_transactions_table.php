<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mpesa_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')
                  ->constrained()
                  ->onDelete('cascade');
            $table->string('phone');
            $table->decimal('amount', 10, 2);
            $table->string('api_ref')->nullable();
            $table->string('checkout_id')->nullable();
            $table->string('mpesa_receipt')->nullable();
            $table->enum('status', [
                'PENDING',
                'COMPLETE',
                'FAILED'
            ])->default('PENDING');
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mpesa_transactions');
    }
};