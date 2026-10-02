<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('float_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            // Whose float this adjusts — the cashier who sold on cash and
            // owes it, or is being given more room to sell against.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // deposit = cash actually handed over (reduces what they owe).
            // refloat = manager raised their allowed limit for this shift
            // (does NOT count as money collected — excluded from the
            // settlement "amount owed" calculation, only raises the ceiling
            // before a cashier gets blocked from further cash sales).
            $table->enum('type', ['deposit', 'refloat']);
            $table->decimal('amount', 12, 2);
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['shift_id', 'user_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('float_adjustments');
    }
};
