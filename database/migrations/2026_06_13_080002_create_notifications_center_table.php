<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('app_notifications')) {
            Schema::create('app_notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('type', 100)->comment('low_stock,overdue_invoice,payroll_due,pending_approval');
                $table->string('title', 200);
                $table->text('message');
                $table->string('action_url', 500)->nullable();
                $table->string('icon', 50)->default('bell')->comment('bell,warning,money,package,people');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->index(['business_id', 'user_id', 'read_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
