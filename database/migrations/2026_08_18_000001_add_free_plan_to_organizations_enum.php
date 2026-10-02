<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Widens organizations.subscription_plan to include the new permanent
// 'free' tier (see config/plans.php). Uses the same raw ALTER pattern as
// the earlier subscriptions.plan enum widening migration in this codebase.
return new class extends Migration {
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("
            ALTER TABLE organizations
            MODIFY COLUMN subscription_plan ENUM('free','solo','growth','scale','enterprise') NOT NULL DEFAULT 'solo'
        ");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("
            ALTER TABLE organizations
            MODIFY COLUMN subscription_plan ENUM('solo','growth','scale','enterprise') NOT NULL DEFAULT 'solo'
        ");
        }
    }
};
