<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('staff_profiles', function (Blueprint $table) {
            // Nullable = "use this business's default_credit_limit". Lives
            // on the per-(user,business) staff profile, not the user row
            // itself — the same person can be a cashier at one business and
            // an owner/manager at another, each with its own limit (or none).
            $table->decimal('credit_limit', 12, 2)->nullable();
        });
    }

    public function down(): void {
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->dropColumn('credit_limit');
        });
    }
};
