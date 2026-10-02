<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_float', 12, 2);
            $table->decimal('expected_closing', 12, 2)->default(0);
            $table->decimal('actual_closing', 12, 2)->nullable();
            $table->decimal('difference', 12, 2)->nullable();
            $table->enum('status', ['open','closed'])->default('open');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('cash_register_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_register_id')->constrained()->cascadeOnDelete();
            $table->enum('entry_type', ['sale','refund','expense','float_add','float_remove']);
            $table->decimal('amount', 12, 2);
            $table->string('description', 255);
            $table->string('reference', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('cash_register_entries');
        Schema::dropIfExists('cash_registers');
    }
};
