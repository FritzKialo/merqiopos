<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('product_waitlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('customer_name', 200);
            $table->string('customer_email', 200)->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->decimal('quantity_wanted', 10, 2)->default(1);
            $table->timestamp('notified_at')->nullable();
            $table->enum('status', ['waiting','notified','fulfilled','cancelled'])->default('waiting');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('product_waitlists');
    }
};
