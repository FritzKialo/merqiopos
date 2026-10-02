<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Builds and removes the throw-away "Try the demo" sandboxes.
 *
 * Every visitor gets their own private store (so nobody sees or breaks anyone
 * else's), filled with a small realistic shop: products, customers, a month of
 * sales, expenses. It has no payment or messaging credentials and the DemoGuard
 * middleware blocks anything that could reach the outside world. Purged after
 * LIFETIME_HOURS by `demo:purge`.
 */
class DemoStore
{
    public const LIFETIME_HOURS = 24;
    public const MAX_ACTIVE     = 150;

    public static function activeCount(): int
    {
        return (int) DB::table('organizations')->where('is_demo', true)->count();
    }

    /** Creates a sandbox and returns the owner's user id. */
    public static function create(): int
    {
        return DB::transaction(function () {
            $now   = now();
            $token = Str::lower(Str::random(8));

            $userId = DB::table('users')->insertGetId([
                'name' => 'Demo Owner', 'email' => "demo-{$token}@demo.invalid",
                'password' => Hash::make(Str::random(40)), 'role' => 'owner', 'is_active' => true,
                'two_factor_enabled' => false, 'is_super_admin' => false,
                'last_login_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);

            $orgId = DB::table('organizations')->insertGetId([
                'name' => 'Demo Mini-Mart', 'owner_user_id' => $userId, 'subscription_plan' => 'enterprise',
                'status' => 'trial', 'is_demo' => true, 'trial_ends_at' => $now->copy()->addDay(),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('users')->where('id', $userId)->update(['organization_id' => $orgId]);

            $bizId = DB::table('businesses')->insertGetId([
                'organization_id' => $orgId, 'name' => 'Demo Mini-Mart', 'email' => "demo-{$token}@demo.invalid",
                'phone' => '0700000000', 'city' => 'Nairobi', 'address' => 'Moi Avenue, Nairobi',
                'industry' => 'Retail', 'business_type' => 'retail', 'subscription_plan' => 'enterprise',
                'status' => 'trial', 'trial_ends_at' => $now->copy()->addDay(), 'is_default' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('business_user')->insert([
                'business_id' => $bizId, 'user_id' => $userId, 'role' => 'owner', 'created_at' => $now, 'updated_at' => $now,
            ]);

            self::fill($bizId, $userId, $token);

            return $userId;
        });
    }

    private static function fill(int $bizId, int $userId, string $token): void
    {
        $now = now();

        // ── products ─────────────────────────────────────────────────────────
        $catalogue = [
            'Groceries'     => [['Maize Flour 2kg', 120, 150], ['Wheat Flour 2kg', 130, 165], ['Sugar 1kg', 150, 185], ['Rice 2kg', 260, 320], ['Cooking Oil 1L', 280, 340], ['Salt 500g', 25, 40], ['Tea Leaves 250g', 95, 130], ['Eggs Tray (30)', 380, 450], ['Blue Band 500g', 150, 190]],
            'Beverages'     => [['Soda 500ml', 45, 70], ['Bottled Water 1L', 35, 60], ['Fruit Juice 1L', 110, 160], ['Fresh Milk 500ml', 50, 65], ['Bread 400g', 55, 70]],
            'Household'     => [['Laundry Detergent 1kg', 210, 270], ['Bar Soap', 60, 90], ['Toilet Paper 4pk', 150, 210], ['Matchbox', 5, 10], ['Dish Wash 500ml', 120, 165]],
            'Personal Care' => [['Toothpaste 100g', 120, 170], ['Body Lotion 400ml', 300, 400], ['Sanitary Pads', 90, 130]],
            'Stationery'    => [['Exercise Book A4', 40, 70], ['Ballpoint Pen', 10, 20]],
        ];
        $products = [];
        $n = 0;
        foreach ($catalogue as $cat => $rows) {
            $catId = DB::table('categories')->insertGetId(['business_id' => $bizId, 'name' => $cat, 'created_at' => $now, 'updated_at' => $now]);
            foreach ($rows as [$name, $buy, $sell]) {
                $n++;
                // A few items deliberately low or out, so the low-stock alerts have something to show.
                $stock = match (true) { $n === 4 => 0, in_array($n, [9, 13, 20], true) => mt_rand(2, 6), default => mt_rand(25, 140) };
                $id = DB::table('products')->insertGetId([
                    'business_id' => $bizId, 'category_id' => $catId, 'name' => $name, 'sku' => 'DEMO-' . str_pad($n, 3, '0', STR_PAD_LEFT),
                    'buying_price' => $buy, 'selling_price' => $sell, 'stock_qty' => $stock, 'reorder_level' => 10,
                    'unit' => 'pc', 'status' => 'active', 'is_featured' => $n <= 8, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $products[] = ['id' => $id, 'name' => $name, 'buy' => $buy, 'sell' => $sell];
            }
        }

        // ── customers (no email, invalid-looking numbers: nothing can ever reach a real person) ──
        $customerIds = [];
        foreach (['Wanjiku Mwangi', 'Otieno Odhiambo', 'Amina Hassan', 'Kevin Kiprop', 'Grace Njeri', 'Peter Mutua', 'Halima Ali', 'Joseph Kamau', 'Faith Achieng', 'Daniel Wekesa'] as $i => $name) {
            $customerIds[] = DB::table('customers')->insertGetId([
                'business_id' => $bizId, 'name' => $name, 'phone' => '07000000' . str_pad($i + 10, 2, '0', STR_PAD_LEFT),
                'email' => 'customer' . ($i + 1) . '-' . Str::lower(Str::random(5)) . '@demo.invalid',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach ([['Unga Distributors Ltd', 'Mary Wambui'], ['Kenya Fresh Foods', 'Samuel Rono'], ['City Wholesale', 'Aisha Mohamed']] as $i => [$name, $contact]) {
            DB::table('suppliers')->insert([
                'business_id' => $bizId, 'name' => $name, 'contact_person' => $contact, 'phone' => '07110000' . str_pad($i + 10, 2, '0', STR_PAD_LEFT),
                'is_active' => true, 'payable_balance' => 0, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // ── a month of sales, busier towards the present ─────────────────────
        $perDay = [];
        $owed   = [];
        $saleIds = [];
        for ($d = 59; $d >= 0; $d--) {
            $date  = $now->copy()->subDays($d);
            $count = mt_rand(2, 5) + (int) round((60 - $d) / 20);
            for ($k = 0; $k < $count; $k++) {
                $when   = $date->copy()->setTime(mt_rand(8, 19), mt_rand(0, 59), mt_rand(0, 59));
                if ($when->gt($now)) continue;
                $perDay[$date->format('Ymd')] = ($perDay[$date->format('Ymd')] ?? 0) + 1;
                $lines = [];
                foreach ((array) array_rand($products, mt_rand(1, 4)) as $pi) {
                    $p = $products[$pi]; $q = mt_rand(1, 3);
                    $lines[] = [$p, $q];
                }
                $total = array_sum(array_map(fn ($l) => $l[0]['sell'] * $l[1], $lines));
                $roll  = mt_rand(1, 100);
                $method = $roll <= 50 ? 'cash' : ($roll <= 88 ? 'mpesa' : 'credit');
                $cust   = ($method === 'credit' || mt_rand(1, 100) <= 40) ? $customerIds[array_rand($customerIds)] : null;
                if ($method === 'credit' && ! $cust) $cust = $customerIds[0];
                $status = $method === 'credit' ? 'unpaid' : 'paid';
                $paid   = $method === 'credit' ? 0 : $total;
                if ($method === 'credit') $owed[$cust] = ($owed[$cust] ?? 0) + $total;

                $saleId = DB::table('sales')->insertGetId([
                    'business_id' => $bizId, 'customer_id' => $cust, 'user_id' => $userId,
                    // Globally unique across every real business on the platform, so it must carry this demo's own token, not just the date.
                    'invoice_number' => 'INV-DEMO-' . $token . '-' . $date->format('Ymd') . '-' . str_pad($perDay[$date->format('Ymd')], 4, '0', STR_PAD_LEFT),
                    'subtotal' => $total, 'total_amount' => $total, 'paid_amount' => $paid, 'balance_due' => $total - $paid,
                    'payment_method' => $method, 'payment_status' => $status, 'sale_status' => 'completed',
                    'mpesa_reference' => $method === 'mpesa' ? 'DM' . strtoupper(Str::random(8)) : null,
                    'created_at' => $when, 'updated_at' => $when,
                ]);
                $saleIds[] = $saleId;
                foreach ($lines as [$p, $q]) {
                    DB::table('sale_items')->insert([
                        'sale_id' => $saleId, 'product_id' => $p['id'], 'product_name' => $p['name'], 'unit_price' => $p['sell'],
                        'buying_price' => $p['buy'], 'quantity' => $q, 'discount' => 0, 'subtotal' => $p['sell'] * $q,
                        'created_at' => $when, 'updated_at' => $when,
                    ]);
                }
            }
        }
        foreach ($owed as $cid => $amt) {
            DB::table('customers')->where('id', $cid)->update(['balance_owed' => $amt]);
        }

        // ── expenses ─────────────────────────────────────────────────────────
        $ecats = [];
        foreach (['Rent', 'Electricity', 'Transport', 'Salaries', 'Supplies'] as $name) {
            $ecats[$name] = DB::table('expense_categories')->insertGetId(['business_id' => $bizId, 'name' => $name, 'created_at' => $now, 'updated_at' => $now]);
        }
        $expenses = [['Rent', 'Shop rent', 25000, 58], ['Electricity', 'KPLC tokens', 3000, 50], ['Transport', 'Delivery from wholesaler', 2100, 44], ['Rent', 'Shop rent', 25000, 28], ['Electricity', 'KPLC tokens', 3200, 21], ['Transport', 'Delivery from wholesaler', 1800, 14],
                     ['Supplies', 'Carrier bags and receipts roll', 950, 9], ['Electricity', 'KPLC tokens', 2800, 5], ['Transport', 'Fuel for deliveries', 1500, 2]];
        foreach ($expenses as [$cat, $title, $amount, $daysAgo]) {
            $date = $now->copy()->subDays($daysAgo);
            DB::table('expenses')->insert([
                'business_id' => $bizId, 'expense_category_id' => $ecats[$cat], 'user_id' => $userId, 'title' => $title, 'amount' => $amount,
                'payment_method' => 'cash', 'expense_date' => $date->toDateString(), 'created_at' => $date, 'updated_at' => $date,
            ]);
        }

        DemoExtras::fill($bizId, $userId, $products, $customerIds, $saleIds, $ecats, $token);
    }

    /** Deletes sandboxes older than LIFETIME_HOURS (or a single one). Returns how many were removed. */
    public static function purge(?int $organizationId = null): int
    {
        $q = DB::table('organizations')->where('is_demo', true);
        $organizationId
            ? $q->where('id', $organizationId)
            : $q->where('created_at', '<', now()->subHours(self::LIFETIME_HOURS));
        $orgIds = $q->pluck('id')->all();
        if (! $orgIds) return 0;

        Schema::disableForeignKeyConstraints();
        try {
            foreach ($orgIds as $orgId) {
                DB::transaction(function () use ($orgId) {
                    $bizIds  = DB::table('businesses')->where('organization_id', $orgId)->pluck('id')->all();
                    $userIds = DB::table('users')->where('organization_id', $orgId)->where('is_super_admin', false)->pluck('id')->all();

                    if ($bizIds) {
                        DB::table('sale_items')->whereIn('sale_id', DB::table('sales')->whereIn('business_id', $bizIds)->select('id'))->delete();
                    }
                    foreach (Schema::getTableListing(null, false) as $table) {
                        if (in_array($table, ['organizations', 'businesses', 'users', 'migrations'], true)) continue;
                        if ($bizIds && Schema::hasColumn($table, 'business_id')) DB::table($table)->whereIn('business_id', $bizIds)->delete();
                        if (Schema::hasColumn($table, 'organization_id')) DB::table($table)->where('organization_id', $orgId)->delete();
                        if ($userIds && Schema::hasColumn($table, 'user_id')) DB::table($table)->whereIn('user_id', $userIds)->delete();
                    }
                    if ($bizIds) DB::table('businesses')->whereIn('id', $bizIds)->delete();
                    if ($userIds) DB::table('users')->whereIn('id', $userIds)->delete();
                    DB::table('organizations')->where('id', $orgId)->delete();
                });
            }
            self::sweepOrphans();
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return count($orgIds);
    }

    // Child rows (invoice lines, tag links ...) have no business_id, so removing their parents leaves them
    // behind. Sweep them by their foreign keys, a couple of passes deep, plus a few tables with no FK.
    private static function sweepOrphans(): void
    {
        $links = [
            ['customer_tag_pivot', 'customer_id', 'customers'], ['customer_tag_pivot', 'customer_tag_id', 'customer_tags'],
            ['sale_items', 'sale_id', 'sales'], ['product_variants', 'product_id', 'products'],
            ['sale_return_items', 'sale_return_id', 'sale_returns'], ['quote_items', 'quote_id', 'quotes'], ['invoice_items', 'invoice_id', 'invoices'],
            ['invoice_payments', 'invoice_id', 'invoices'], ['purchase_order_items', 'purchase_order_id', 'purchase_orders'],
            ['stock_receive_items', 'stock_receive_id', 'stock_receives'], ['credit_note_items', 'credit_note_id', 'credit_notes'],
            ['proforma_invoice_items', 'proforma_invoice_id', 'proforma_invoices'], ['online_order_items', 'online_order_id', 'online_orders'],
            ['expense_claim_items', 'expense_claim_id', 'expense_claims'], ['table_order_items', 'table_order_id', 'table_orders'],
            ['product_bundle_items', 'product_bundle_id', 'product_bundles'], ['payroll_items', 'payroll_period_id', 'payroll_periods'],
            ['stock_count_items', 'stock_count_id', 'stock_counts'], ['stock_transfer_items', 'stock_transfer_id', 'stock_transfers'],
            ['delivery_note_items', 'delivery_note_id', 'delivery_notes'], ['recurring_invoice_items', 'recurring_invoice_id', 'recurring_invoices'],
            ['supplier_credit_note_items', 'supplier_credit_note_id', 'supplier_credit_notes'], ['product_images', 'product_id', 'products'],
            ['product_suppliers', 'product_id', 'products'], ['serial_numbers', 'product_id', 'products'],
        ];
        // information_schema is MySQL-only — the hardcoded $links list above already covers
        // every table that actually matters for demo cleanup, so this dynamic discovery
        // (which only widens coverage further) is skipped on any other driver rather than
        // crashing the whole purge (as it did on the sqlite test suite).
        if (DB::connection()->getDriverName() === 'mysql') {
            $db = DB::getDatabaseName();
            $fks = DB::select('select TABLE_NAME t, COLUMN_NAME c, REFERENCED_TABLE_NAME p, REFERENCED_COLUMN_NAME rc from information_schema.KEY_COLUMN_USAGE where TABLE_SCHEMA = ? and REFERENCED_TABLE_NAME is not null', [$db]);
            foreach ($fks as $fk) $links[] = [$fk->t, $fk->c, $fk->p, $fk->rc];
        }

        for ($pass = 0; $pass < 3; $pass++) {
            foreach ($links as $l) {
                [$child, $col, $parent] = $l;
                $pcol = $l[3] ?? 'id';
                if (! Schema::hasTable($child) || ! Schema::hasTable($parent) || $child === $parent) continue;
                // A correlated NOT IN rather than a multi-table DELETE...JOIN (MySQL-only
                // syntax — broke on sqlite, the automated test suite's DB) so this runs on
                // any driver; demo cleanup's data volumes are small enough that this
                // being less efficient than a join never matters in practice.
                DB::statement("delete from `$child` where `$col` is not null and `$col` not in (select `$pcol` from `$parent`)");
            }
        }
    }
}
