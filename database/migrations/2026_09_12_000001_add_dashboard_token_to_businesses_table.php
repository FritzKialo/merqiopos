<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A separate, deliberately weaker token from `api_token`. The REST API
// token can create/update products and customers — handing that out just
// so an owner can glance at today's sales from their phone is more power
// than the situation needs. This one only ever unlocks a single read-only
// page (see ManagerViewController) — it can never modify anything, no
// matter who ends up holding it.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('dashboard_token', 80)->nullable()->unique()->after('api_token')
                ->comment('Hashed token for the read-only, login-free manager dashboard view');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('dashboard_token');
        });
    }
};
