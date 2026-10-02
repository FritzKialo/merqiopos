<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // customers.credit_limit_enabled has never actually existed in the
    // database, on any environment (confirmed: missing on local dev and
    // the sme_manager_test DB, almost certainly missing on production
    // too since migration history is identical). The Customer model's
    // $fillable/$casts include it and CustomerController writes it on
    // every credit-limit update — but the migration meant to add it
    // (2026_06_13_070003_add_credit_limit_to_customers.php) was guarded
    // by `if (!Schema::hasColumn('customers', 'credit_limit'))`, and an
    // EARLIER migration (2026_06_12_015011_create_customer_credits_table)
    // had already added `credit_limit` (for a different purpose) — so
    // that guard silently skipped the entire migration body, including
    // the credit_limit_enabled column, while still being marked "Ran".
    // Net effect: submitting the "Credit Limit" form on a customer's
    // page has always thrown a fatal "Unknown column" SQL error, and
    // the credit-limit-exceeded summary block on that page has always
    // been silently dead (the attribute never persists). Discovered
    // while direct-rendering customers/show.blade.php for an unrelated
    // CSS verification.
    public function up(): void {
        if (!Schema::hasColumn('customers', 'credit_limit_enabled')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->boolean('credit_limit_enabled')->default(false)->after('credit_limit');
            });
        }
    }

    public function down(): void {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('credit_limit_enabled');
        });
    }
};
