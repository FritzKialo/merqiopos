<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('expense_categories',
            function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')
                    ->constrained()
                    ->onDelete('cascade');
                $table->string('name');
                $table->string('description')
                    ->nullable();
                $table->timestamps();

                // Unique name per business
                $table->unique([
                    'business_id', 'name'
                ]);
            }
        );
    }

    public function down(): void {
        Schema::dropIfExists('expense_categories');
    }
};