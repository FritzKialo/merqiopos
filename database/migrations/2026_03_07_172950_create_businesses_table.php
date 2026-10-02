<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('businesses', function (
            Blueprint $table
        ) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('industry')->nullable();
            $table->string('logo')->nullable();
            $table->enum('subscription_plan', [
                'starter', 'business', 'enterprise'
            ])->default('starter');
            $table->enum('status', [
                'active', 'suspended', 'trial'
            ])->default('trial');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
        });

        // Now that businesses exists, add the FK
        // to users.business_id
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('business_id')
                ->references('id')
                ->on('businesses')
                ->onDelete('cascade');
        });
    }

    public function down(): void {
        // Drop FK before dropping the table
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['business_id']);
        });
        Schema::dropIfExists('businesses');
    }
};