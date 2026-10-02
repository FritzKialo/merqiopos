<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Developer logs: what stores do, and what fails. Two tables.
//
//  error_logs    one row per (error, store) per day, with a running count — so a
//                bug hitting 14 tills shows as one line, not 14. Nothing the user
//                typed is stored: only the page, the error and the stack trace.
//  activity_logs one row per action (form submissions, logins, and anything that
//                failed), with who, where, the outcome and how long it took.
//
// Both are pruned automatically (see logs:prune).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->char('fingerprint', 40)->index();
            $table->unsignedBigInteger('business_id')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('role', 30)->nullable();
            $table->string('method', 10)->nullable();
            $table->string('path', 255)->nullable();
            $table->string('route_name', 120)->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('exception', 190);
            $table->text('message')->nullable();
            $table->string('file', 255)->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->text('trace')->nullable();
            $table->char('request_id', 12)->nullable()->index();
            $table->unsignedInteger('count')->default(1);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'last_seen_at']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('role', 30)->nullable();
            $table->string('kind', 20)->default('request');   // request | login | login_failed | logout
            $table->string('method', 10)->nullable();
            $table->string('path', 255)->nullable();
            $table->string('route_name', 120)->nullable();
            $table->unsignedSmallInteger('status')->nullable();
            $table->string('outcome', 20)->default('ok');     // ok | failed | validation | denied | error
            $table->string('message', 255)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('agent', 160)->nullable();
            $table->char('request_id', 12)->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->index(['business_id', 'created_at']);
            $table->index(['outcome', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('error_logs');
    }
};
