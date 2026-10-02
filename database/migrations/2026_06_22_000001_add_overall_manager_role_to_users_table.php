<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add the organization-level "overall_manager" role.
     *
     * An overall manager can do everything an owner can across all branches
     * (dashboard, branch management, inter-branch transfers, cross-branch
     * reports) EXCEPT cancelling the subscription or deleting the organization.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('owner','overall_manager','manager','cashier') NOT NULL DEFAULT 'owner'");
        }
    }

    public function down(): void
    {
        // Demote any overall managers before shrinking the enum
        DB::table('users')->where('role', 'overall_manager')->update(['role' => 'manager']);
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('owner','manager','cashier') NOT NULL DEFAULT 'owner'");
        }
    }
};
