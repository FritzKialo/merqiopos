<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Step 1 — add organization_id to subscriptions, update plan enum
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('id')
                ->constrained('organizations')
                ->onDelete('cascade');
        });

        // Step 2 — populate organization_id from existing business subscriptions.
        // A portable per-row update rather than a MySQL-only JOIN-UPDATE, so this
        // migration can also run on sqlite (the automated test suite's DB) — the
        // data volume here is small (one-time backfill of existing subscriptions),
        // so the loop's extra round trips don't matter.
        DB::table('subscriptions as s')
            ->join('businesses as b', 'b.id', '=', 's.business_id')
            ->whereNotNull('b.organization_id')
            ->select('s.id', 'b.organization_id')
            ->get()
            ->each(fn ($row) => DB::table('subscriptions')->where('id', $row->id)->update(['organization_id' => $row->organization_id]));

        // Step 3 — update plan enum to include org-level plans. ENUM with a MODIFY
        // COLUMN is MySQL-only syntax; sqlite has no enum type to widen (any string
        // is already accepted), so there's nothing to do there.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE subscriptions
                MODIFY COLUMN plan ENUM('starter','business','enterprise','solo','growth','scale') NOT NULL
            ");
        }

        // Step 4 — add organization_id to mpesa_transactions for subscription payments
        Schema::table('mpesa_transactions', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('subscription_id')
                ->constrained('organizations')
                ->onDelete('cascade');
        });

        // Step 5 — populate organization_id on subscription-type mpesa_transactions (see Step 2 — same portability reason).
        DB::table('mpesa_transactions as mt')
            ->join('subscriptions as s', 's.id', '=', 'mt.subscription_id')
            ->where('mt.type', 'subscription')
            ->whereNotNull('s.organization_id')
            ->select('mt.id', 's.organization_id')
            ->get()
            ->each(fn ($row) => DB::table('mpesa_transactions')->where('id', $row->id)->update(['organization_id' => $row->organization_id]));
    }

    public function down(): void
    {
        Schema::table('mpesa_transactions', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE subscriptions
                MODIFY COLUMN plan ENUM('starter','business','enterprise') NOT NULL
            ");
        }
    }
};
