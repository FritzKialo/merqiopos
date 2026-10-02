<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('expenses',
            function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')
                    ->constrained()
                    ->onDelete('cascade');
                $table->foreignId('expense_category_id')
                    ->nullable()
                    ->constrained()
                    ->onDelete('set null');
                $table->foreignId('user_id')
                    ->constrained()
                    ->onDelete('cascade');
                $table->string('title');
                $table->text('description')
                    ->nullable();
                $table->decimal('amount', 12, 2);
                $table->enum('payment_method', [
                    'cash', 'mpesa',
                    'bank_transfer'
                ])->default('cash');
                $table->string('reference')
                    ->nullable();
                $table->date('expense_date');
                $table->string('receipt')
                    ->nullable();
                $table->timestamps();
            }
        );
    }

    public function down(): void {
        Schema::dropIfExists('expenses');
    }
};