<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            // Internal support/CRM notes — visible only to platform admins on
            // the admin/organizations/{organization} page, never surfaced to
            // the organization itself. Nullable, no default: most orgs will
            // never have one written.
            $table->text('admin_notes')->nullable()->after('trial_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('admin_notes');
        });
    }
};
