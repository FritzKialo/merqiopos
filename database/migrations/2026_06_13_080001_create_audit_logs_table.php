<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The audit_logs table already exists with different schema.
        // Add the new columns if they don't exist.
        Schema::table('audit_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('audit_logs', 'action')) {
                $table->string('action', 50)->nullable()->after('event');
            }
            if (!Schema::hasColumn('audit_logs', 'model_type')) {
                $table->string('model_type', 100)->nullable()->after('action');
            }
            if (!Schema::hasColumn('audit_logs', 'model_id')) {
                $table->unsignedBigInteger('model_id')->nullable()->after('model_type');
            }
            if (!Schema::hasColumn('audit_logs', 'description')) {
                $table->string('description', 500)->nullable()->after('model_id');
            }
            if (!Schema::hasColumn('audit_logs', 'old_values')) {
                $table->json('old_values')->nullable()->after('description');
            }
            if (!Schema::hasColumn('audit_logs', 'new_values')) {
                $table->json('new_values')->nullable()->after('old_values');
            }
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['action', 'model_type', 'model_id', 'description', 'old_values', 'new_values']);
        });
    }
};
