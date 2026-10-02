<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            if (!Schema::hasColumn('businesses', 'mpesa_initiator_name')) {
                $table->string('mpesa_initiator_name', 100)->nullable();
                // The initiator's security credential (its password already
                // encrypted with Safaricom's certificate), stored encrypted.
                $table->text('mpesa_security_credential')->nullable();
            }
        });

        if (!Schema::hasTable('mpesa_status_checks')) {
            Schema::create('mpesa_status_checks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('sale_id')->nullable();
                $table->string('receipt', 30);
                $table->decimal('claimed_amount', 12, 2)->default(0);
                // pending | confirmed | mismatch | not_found | failed
                $table->string('status', 20)->default('pending');
                $table->string('conversation_id', 100)->nullable();
                $table->string('result_desc', 500)->nullable();
                $table->decimal('confirmed_amount', 12, 2)->nullable();
                $table->json('response')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['sale_id']);
                $table->index(['conversation_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mpesa_status_checks');
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['mpesa_initiator_name', 'mpesa_security_credential']);
        });
    }
};
