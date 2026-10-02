<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('etims_refunds')) return;

        Schema::create('etims_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            // sale_cancel | sale_return | credit_note
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id');
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            // pending | submitted | failed | skipped
            $table->string('status', 20)->default('pending');
            $table->string('cuin', 50)->nullable();
            $table->json('response')->nullable();
            $table->string('message', 500)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etims_refunds');
    }
};
