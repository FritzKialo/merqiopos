<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('customers', function (Blueprint $table) {
            // Security fix: portal_token previously never expired, so a leaked
            // reset/invite link stayed valid forever. Give it a real expiry to
            // match the staff-facing password-reset flow's 60-minute window.
            $table->timestamp('portal_token_expires_at')->nullable()->after('portal_token');
        });
    }

    public function down(): void {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('portal_token_expires_at');
        });
    }
};
