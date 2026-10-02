<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tax_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 20);
            $table->decimal('rate', 5, 2);
            // 1 = base rate, 2+ reserved for future compounding support —
            // this pass only applies priority-1, inclusive rules (see
            // SaleService::calculateItemTax()). Stored now so rules created
            // today don't need re-entry once compounding ships.
            $table->unsignedTinyInteger('priority')->default(1);
            $table->enum('tax_category', ['standard', 'reduced', 'zero_rated', 'exempt'])->default('standard');
            $table->boolean('inclusive')->default(true);
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['business_id', 'enabled', 'tax_category']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('tax_rules');
    }
};
