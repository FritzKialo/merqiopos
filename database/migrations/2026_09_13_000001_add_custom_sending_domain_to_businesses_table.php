<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Lets a business send campaign email as their own domain
    // (promo@theirbusiness.com) instead of the platform's shared address,
    // once they've proven ownership by publishing a DKIM public key + SPF
    // include we generate for them. dkim_private_key is Crypt-encrypted at
    // rest via the model cast — never exposed anywhere past generation.
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('sending_domain')->nullable()->after('email');
            $table->string('dkim_selector')->nullable()->after('sending_domain');
            $table->text('dkim_private_key')->nullable()->after('dkim_selector');
            $table->text('dkim_public_key')->nullable()->after('dkim_private_key');
            $table->timestamp('domain_verified_at')->nullable()->after('dkim_public_key');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'sending_domain', 'dkim_selector', 'dkim_private_key',
                'dkim_public_key', 'domain_verified_at',
            ]);
        });
    }
};
