<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('reviewer_name', 200);
            $table->string('reviewer_email', 200)->nullable();
            $table->tinyInteger('rating')->unsigned()->comment('1-5');
            $table->text('review_body')->nullable();
            $table->enum('status', ['pending','approved','rejected'])->default('pending');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('product_reviews');
    }
};
