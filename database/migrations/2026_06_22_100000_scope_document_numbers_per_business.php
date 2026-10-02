<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Document numbers (invoice/credit-note/etc.) are generated PER BRANCH —
     * each business starts its own sequence at 0001. But these tables had a
     * GLOBAL unique on the number column, so a second branch generating
     * "INV-YYYYMMDD-0001" collided with the first branch's number.
     *
     * Fix: replace the global unique with a composite unique scoped to the
     * owning entity (business, or organization for transfers).
     */
    private array $map = [
        ['sales',             'sales_invoice_number_unique',              'business_id',     'invoice_number'],
        ['invoices',          'invoices_invoice_number_unique',           'business_id',     'invoice_number'],
        ['credit_notes',      'credit_notes_number_unique',               'business_id',     'number'],
        ['delivery_notes',    'delivery_notes_number_unique',             'business_id',     'number'],
        ['proforma_invoices', 'proforma_invoices_proforma_number_unique', 'business_id',     'proforma_number'],
        ['stock_transfers',   'stock_transfers_transfer_number_unique',   'organization_id', 'transfer_number'],
    ];

    public function up(): void
    {
        foreach ($this->map as [$table, $oldIndex, $scope, $col]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $scope) || ! Schema::hasColumn($table, $col)) {
                continue;
            }
            // Drop the old global unique (guarded — skip if already gone).
            if ($this->indexExists($table, $oldIndex)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropUnique($oldIndex));
            }
            // Add the composite unique (guarded against re-runs).
            $newIndex = "{$table}_{$scope}_{$col}_unique";
            if (! $this->indexExists($table, $newIndex)) {
                Schema::table($table, fn (Blueprint $t) => $t->unique([$scope, $col], $newIndex));
            }
        }
    }

    public function down(): void
    {
        foreach ($this->map as [$table, $oldIndex, $scope, $col]) {
            if (! Schema::hasTable($table)) continue;
            $newIndex = "{$table}_{$scope}_{$col}_unique";
            if ($this->indexExists($table, $newIndex)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropUnique($newIndex));
            }
            // Best-effort restore of the global unique (may fail if cross-scope
            // duplicates now exist — that's expected and acceptable on rollback).
            try {
                if (! $this->indexExists($table, $oldIndex)) {
                    Schema::table($table, fn (Blueprint $t) => $t->unique([$col], $oldIndex));
                }
            } catch (\Throwable $e) {
                // leave it; rollback is best-effort
            }
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        // Schema::getIndexes() is Laravel's own portable introspection (no doctrine/dbal
        // needed) — works the same on MySQL and sqlite, unlike the raw "SHOW INDEX FROM"
        // this replaced, which only MySQL understands (broke the sqlite test suite).
        foreach (Schema::getIndexes($table) as $existing) {
            if ($existing['name'] === $index) {
                return true;
            }
        }
        return false;
    }
};
