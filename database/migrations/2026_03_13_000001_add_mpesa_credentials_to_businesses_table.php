<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each business stores its own Daraja API credentials so that
     * customer sale payments go directly to the shop owner's M-Pesa
     * account, while platform subscription payments continue to use
     * the platform-level credentials in .env.
     */
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('mpesa_shortcode')->nullable()
                ->after('trial_ends_at')
                ->comment('Paybill or till number that receives customer payments');
            $table->string('mpesa_consumer_key')->nullable()
                ->after('mpesa_shortcode');
            $table->string('mpesa_consumer_secret')->nullable()
                ->after('mpesa_consumer_key');
            $table->text('mpesa_passkey')->nullable()
                ->after('mpesa_consumer_secret')
                ->comment('Lipa Na M-Pesa Online passkey');
            $table->string('mpesa_till_number')->nullable()
                ->after('mpesa_passkey')
                ->comment('Set only when using Buy Goods (till) instead of Pay Bill');
            $table->string('mpesa_environment', 20)->default('sandbox')
                ->after('mpesa_till_number');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'mpesa_shortcode',
                'mpesa_consumer_key',
                'mpesa_consumer_secret',
                'mpesa_passkey',
                'mpesa_till_number',
                'mpesa_environment',
            ]);
        });
    }
};
