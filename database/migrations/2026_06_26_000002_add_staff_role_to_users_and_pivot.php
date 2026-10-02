<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * The 'staff' role was used throughout the app (team management, dashboards,
     * payroll) but was never added to the role ENUMs, so adding a staff member
     * failed with "Data truncated for column 'role'".
     */
    public function up(): void {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('owner','overall_manager','manager','cashier','staff') NOT NULL DEFAULT 'owner'");
        }
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE business_user MODIFY COLUMN role ENUM('owner','manager','cashier','staff') NOT NULL DEFAULT 'cashier'");
        }
    }

    public function down(): void {
        // Demote any staff before shrinking the ENUMs so nothing truncates.
        DB::table('users')->where('role', 'staff')->update(['role' => 'cashier']);
        DB::table('business_user')->where('role', 'staff')->update(['role' => 'cashier']);
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('owner','overall_manager','manager','cashier') NOT NULL DEFAULT 'owner'");
        }
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE business_user MODIFY COLUMN role ENUM('owner','manager','cashier') NOT NULL DEFAULT 'cashier'");
        }
    }
};
