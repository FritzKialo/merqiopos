<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Step 1 — add organization_id to users (owners belong to an org, not a business)
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('business_id')
                ->constrained('organizations')
                ->onDelete('cascade');
        });

        // Step 2 — for each existing business, create an Organization record
        // owned by the business's owner user, then wire everything up.
        $businesses = DB::table('businesses')->get();

        foreach ($businesses as $business) {
            // Find the owner user for this business
            $owner = DB::table('users')
                ->where('business_id', $business->id)
                ->where('role', 'owner')
                ->first();

            if (!$owner) {
                // No owner found — skip org creation, just seed pivot for all users
                $this->seedPivotForBusiness($business->id);
                continue;
            }

            // Create an Organization for this business
            $orgId = DB::table('organizations')->insertGetId([
                'name'              => $business->name,
                'owner_user_id'     => $owner->id,
                'subscription_plan' => $this->mapLegacyPlan($business->subscription_plan),
                'status'            => $business->status,
                'trial_ends_at'     => $business->trial_ends_at,
                'created_at'        => $business->created_at,
                'updated_at'        => $business->updated_at,
            ]);

            // Link the business to this organization
            DB::table('businesses')
                ->where('id', $business->id)
                ->update(['organization_id' => $orgId]);

            // Set organization_id on the owner user
            DB::table('users')
                ->where('id', $owner->id)
                ->update(['organization_id' => $orgId]);

            // Seed pivot for all users of this business
            $this->seedPivotForBusiness($business->id);
        }

        // Step 3 — drop the old business_id FK and column from users
        // Only drop FK if it exists (it was added in the businesses migration).
        // information_schema (foreignKeyExists()) is MySQL-only, so it can't tell us
        // on other drivers — but on sqlite specifically, the FK clause added by
        // create_businesses_table is baked into the table definition regardless of
        // constraint naming, and dropColumn() below rebuilds the table keeping that
        // (now-dangling) FK clause unless it's dropped first. So: try the driver's
        // own information_schema check first (authoritative on MySQL); on any other
        // driver, or if that check can't run, just attempt the drop and ignore a
        // "no such constraint" failure rather than skip it outright.
        Schema::table('users', function (Blueprint $table) {
            if ($this->foreignKeyExists('users', 'users_business_id_foreign')) {
                $table->dropForeign(['business_id']);
            } elseif (DB::connection()->getDriverName() !== 'mysql') {
                try {
                    $table->dropForeign(['business_id']);
                } catch (\Throwable $e) {
                    // No such FK on this connection — nothing to drop.
                }
            }
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('business_id');
        });
    }

    public function down(): void
    {
        // Restore business_id column on users
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('business_id')->nullable()->after('id');
        });

        // Restore business_id values from pivot (take the first business per user)
        $pivotRows = DB::table('business_user')->get();
        foreach ($pivotRows as $row) {
            DB::table('users')
                ->where('id', $row->user_id)
                ->update(['business_id' => $row->business_id]);
        }

        // Remove organization_id from users
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });

        // Remove organization_id from businesses
        DB::table('businesses')->update(['organization_id' => null]);

        // Delete auto-created organizations
        DB::table('organizations')->truncate();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function seedPivotForBusiness(int $businessId): void
    {
        $users = DB::table('users')
            ->where('business_id', $businessId)
            ->get();

        foreach ($users as $user) {
            DB::table('business_user')->insertOrIgnore([
                'business_id' => $businessId,
                'user_id'     => $user->id,
                'role'        => $user->role,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    // Map legacy per-business plan to closest org-level plan
    private function mapLegacyPlan(string $plan): string
    {
        return match ($plan) {
            'enterprise' => 'scale',
            'business'   => 'growth',
            default      => 'solo',
        };
    }

    private function foreignKeyExists(string $table, string $fkName): bool
    {
        // information_schema is MySQL-only — this query threw on any other driver
        // (including the sqlite :memory: connection the automated test suite runs
        // migrations against), which meant every single test in the suite failed
        // before running a line of its own code. Other drivers don't name
        // constraints this way at all; dropColumn() below removes the column (and
        // any constraint on it) regardless, so skipping the explicit dropForeign
        // there is safe.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return false;
        }

        $database = DB::connection()->getDatabaseName();
        $count = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $fkName)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->count();
        return $count > 0;
    }
};
