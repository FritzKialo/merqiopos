<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Marks which store(s) an org keeps active when it downgrades below its
    // current store count. Not used at all while store count <= the org's
    // current plan limit — Business::isLockedByPlan() only consults it once
    // the org is genuinely over limit, and it becomes irrelevant again the
    // moment they upgrade back (no cleanup needed either direction).
    public function up(): void {
        if (!Schema::hasColumn('businesses', 'is_default')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->boolean('is_default')->default(false)->after('status');
            });
        }
    }

    public function down(): void {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
