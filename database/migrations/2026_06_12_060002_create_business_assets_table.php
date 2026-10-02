<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('business_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category', 100);
            $table->text('description')->nullable();
            $table->date('purchase_date');
            $table->decimal('purchase_cost', 14, 2);
            $table->enum('depreciation_method', ['straight_line', 'none'])->default('straight_line');
            $table->integer('useful_life_years')->default(5);
            $table->decimal('residual_value', 14, 2)->default(0);
            $table->decimal('current_value', 14, 2);
            $table->enum('status', ['active', 'disposed', 'written_off'])->default('active');
            $table->date('disposal_date')->nullable();
            $table->decimal('disposal_proceeds', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('business_assets');
    }
};
