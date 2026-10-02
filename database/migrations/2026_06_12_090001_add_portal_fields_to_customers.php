<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('portal_password')->nullable()->after('notes');
            $table->timestamp('portal_last_login')->nullable()->after('portal_password');
            $table->string('portal_token', 100)->nullable()->unique()->after('portal_last_login');
        });
    }

    public function down(): void {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['portal_password', 'portal_last_login', 'portal_token']);
        });
    }
};
