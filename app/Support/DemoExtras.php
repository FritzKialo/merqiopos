<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The rest of the demo store: every module gets a few realistic records, so a visitor
 * opening any page finds something to look at, click into and change.
 */
class DemoExtras
{
    private static function ins(string $table, array $row, $at = null): int
    {
        $at = $at ?? now();
        return DB::table($table)->insertGetId($row + ['created_at' => $at, 'updated_at' => $at]);
    }

    public static function fill(int $biz, int $owner, array $products, array $customers, array $saleIds, array $ecats, string $token): void
    {
        $now = now();
        $day = fn (int $ago) => $now->copy()->subDays($ago);
        $pick = fn (int $i) => $products[$i % count($products)];
        $orgId = DB::table('businesses')->where('id', $biz)->value('organization_id');
        $suppliers = DB::table('suppliers')->where('business_id', $biz)->pluck('id')->all();

        // ── purchasing: one received, one part-received, one ordered ─────────
        foreach ([[0, 'received', 'paid', 20], [1, 'partially_received', 'partial', 6], [2, 'ordered', 'unpaid', 2]] as $n => [$si, $status, $pay, $ago]) {
            $lines = [[$pick($n * 3), 40], [$pick($n * 3 + 1), 30], [$pick($n * 3 + 2), 24]];
            $sub = array_sum(array_map(fn ($l) => $l[0]['buy'] * $l[1], $lines));
            $poId = self::ins('purchase_orders', [
                'business_id' => $biz, 'supplier_id' => $suppliers[$si], 'user_id' => $owner, 'po_number' => 'PO-' . $token . '-' . ($n + 1),
                'order_date' => $day($ago)->toDateString(), 'expected_date' => $day($ago - 3)->toDateString(), 'subtotal' => $sub, 'total' => $sub,
                'amount_paid' => $pay === 'paid' ? $sub : ($pay === 'partial' ? round($sub / 2) : 0), 'status' => $status, 'payment_status' => $pay,
                'received_date' => $status === 'received' ? $day($ago - 2)->toDateString() : null,
            ], $day($ago));
            $itemIds = [];
            foreach ($lines as [$p, $q]) {
                $rec = $status === 'received' ? $q : ($status === 'partially_received' ? (int) ($q / 2) : 0);
                $itemIds[] = [self::ins('purchase_order_items', [
                    'purchase_order_id' => $poId, 'product_id' => $p['id'], 'product_name' => $p['name'], 'quantity_ordered' => $q,
                    'quantity_received' => $rec, 'unit_cost' => $p['buy'], 'subtotal' => $p['buy'] * $q,
                ], $day($ago)), $p, $rec];
            }
            if ($status !== 'ordered') {
                $rid = self::ins('stock_receives', [
                    'business_id' => $biz, 'purchase_order_id' => $poId, 'supplier_id' => $suppliers[$si], 'user_id' => $owner,
                    'receive_number' => 'GRN-' . $token . '-' . ($n + 1), 'received_date' => $day($ago - 2)->toDateString(), 'total_cost' => $sub,
                ], $day($ago - 2));
                foreach ($itemIds as [$iid, $p, $rec]) {
                    self::ins('stock_receive_items', ['stock_receive_id' => $rid, 'product_id' => $p['id'], 'purchase_order_item_id' => $iid, 'product_name' => $p['name'],
                        'quantity_received' => $rec, 'unit_cost' => $p['buy'], 'subtotal' => $p['buy'] * $rec], $day($ago - 2));
                }
            }
        }

        // ── quotes, proformas, invoices, credit note ─────────────────────────
        foreach ([['draft', 3], ['sent', 8], ['accepted', 15], ['expired', 50]] as $n => [$st, $ago]) {
            $lines = [[$pick($n + 4), 10 + $n * 5], [$pick($n + 9), 6]];
            $sub = array_sum(array_map(fn ($l) => $l[0]['sell'] * $l[1], $lines));
            $qid = self::ins('quotes', [
                'business_id' => $biz, 'customer_id' => $customers[$n], 'user_id' => $owner, 'quote_number' => 'QT-' . $token . '-' . ($n + 1),
                'quote_date' => $day($ago)->toDateString(), 'valid_until' => $day($ago - 14)->toDateString(), 'subtotal' => $sub, 'total' => $sub, 'status' => $st,
                'terms' => 'Prices valid for 14 days. Payment on delivery.',
            ], $day($ago));
            foreach ($lines as [$p, $q]) self::ins('quote_items', ['quote_id' => $qid, 'product_id' => $p['id'], 'product_name' => $p['name'], 'unit_price' => $p['sell'], 'quantity' => $q, 'subtotal' => $p['sell'] * $q], $day($ago));
        }
        foreach ([['sent', 5], ['accepted', 12]] as $n => [$st, $ago]) {
            $pid = self::ins('proforma_invoices', [
                'business_id' => $biz, 'customer_id' => $customers[$n + 4], 'proforma_number' => 'PF-' . $token . '-' . ($n + 1), 'issue_date' => $day($ago)->toDateString(),
                'valid_until' => $day($ago - 14)->toDateString(), 'status' => $st, 'subtotal' => 24000, 'total_amount' => 24000,
            ], $day($ago));
            self::ins('proforma_invoice_items', ['proforma_invoice_id' => $pid, 'description' => 'Bulk grocery order', 'quantity' => 8, 'unit_price' => 3000, 'tax_rate' => 0, 'total' => 24000], $day($ago));
        }
        $firstInvoice = null;
        foreach ([['paid', 30, 1.0], ['partial', 18, 0.5], ['sent', 6, 0], ['overdue', 40, 0], ['draft', 1, 0], ['paid', 12, 1.0]] as $n => [$st, $ago, $paidFrac]) {
            $p1 = $pick($n + 2); $p2 = $pick($n + 6); $q1 = 12 + $n * 3; $q2 = 5 + $n;
            $total = $p1['sell'] * $q1 + $p2['sell'] * $q2; $paid = round($total * $paidFrac);
            $iid = self::ins('invoices', [
                'business_id' => $biz, 'customer_id' => $customers[$n % count($customers)], 'user_id' => $owner, 'invoice_number' => 'INV-DEMO-' . $token . '-' . ($n + 1),
                'issue_date' => $day($ago)->toDateString(), 'due_date' => $day($ago - 14)->toDateString(), 'subtotal' => $total, 'total' => $total,
                'amount_paid' => $paid, 'balance_due' => $total - $paid, 'status' => $st, 'payment_terms' => 'Net 14 days',
            ], $day($ago));
            $firstInvoice ??= $iid;
            self::ins('invoice_items', ['invoice_id' => $iid, 'product_id' => $p1['id'], 'description' => $p1['name'], 'quantity' => $q1, 'unit_price' => $p1['sell'], 'subtotal' => $p1['sell'] * $q1], $day($ago));
            self::ins('invoice_items', ['invoice_id' => $iid, 'product_id' => $p2['id'], 'description' => $p2['name'], 'quantity' => $q2, 'unit_price' => $p2['sell'], 'subtotal' => $p2['sell'] * $q2], $day($ago));
            if ($paid > 0) self::ins('invoice_payments', ['invoice_id' => $iid, 'business_id' => $biz, 'user_id' => $owner, 'amount' => $paid, 'method' => 'mpesa', 'paid_date' => $day($ago - 5)->toDateString(), 'reference' => 'DM' . strtoupper(Str::random(8))], $day($ago - 5));
        }
        $cn = self::ins('credit_notes', ['business_id' => $biz, 'invoice_id' => $firstInvoice, 'customer_id' => $customers[0], 'user_id' => $owner, 'number' => 'CN-' . $token,
            'reason' => 'Damaged goods returned', 'status' => 'issued', 'subtotal' => 1500, 'vat_amount' => 0, 'total' => 1500, 'issued_at' => $day(20)], $day(20));
        self::ins('credit_note_items', ['credit_note_id' => $cn, 'description' => 'Damaged goods returned', 'quantity' => 1, 'unit_price' => 1500, 'vat_rate' => 0, 'vat_amount' => 0, 'total' => 1500], $day(20));

        // ── sales return on a real sale ──────────────────────────────────────
        $sale = DB::table('sales')->where('id', $saleIds[10])->first();
        $item = DB::table('sale_items')->where('sale_id', $sale->id)->first();
        $ret = self::ins('sale_returns', ['business_id' => $biz, 'sale_id' => $sale->id, 'user_id' => $owner, 'customer_id' => $sale->customer_id, 'return_number' => 'RET-' . $token,
            'total_refund' => $item->unit_price, 'stock_action' => 'restock', 'refund_method' => 'cash', 'reason' => 'Customer changed their mind'], $day(40));
        self::ins('sale_return_items', ['sale_return_id' => $ret, 'sale_item_id' => $item->id, 'product_id' => $item->product_id, 'product_name' => $item->product_name, 'quantity_returned' => 1, 'unit_price' => $item->unit_price, 'subtotal' => $item->unit_price], $day(40));

        // ── promotions, loyalty, customer tags ───────────────────────────────
        self::ins('discounts', ['business_id' => $biz, 'name' => 'Weekend Special 10%', 'code' => 'WEEKEND10-' . $token, 'type' => 'percentage', 'value' => 10, 'is_active' => true]);
        self::ins('discounts', ['business_id' => $biz, 'name' => 'KSh 50 off big baskets', 'code' => 'BIG50-' . $token, 'type' => 'fixed', 'value' => 50, 'min_order_amount' => 1000, 'is_active' => true]);
        self::ins('coupons', ['business_id' => $biz, 'code' => 'WELCOME5-' . $token, 'name' => 'Welcome 5% off', 'discount_type' => 'percentage', 'discount_value' => 5, 'used_count' => 3, 'is_active' => true]);
        self::ins('loyalty_programs', ['business_id' => $biz, 'name' => 'Mini-Mart Rewards', 'points_per_shilling' => 0.01, 'redemption_rate' => 1, 'min_redemption_points' => 100, 'is_active' => true]);
        foreach ([['Regulars', '#16a34a', [0, 1, 2]], ['Wholesale', '#2563eb', [3, 4]], ['Credit customers', '#dc2626', [5, 6, 7]]] as [$name, $color, $members]) {
            $tid = self::ins('customer_tags', ['business_id' => $biz, 'name' => $name, 'color' => $color]);
            foreach ($members as $m) DB::table('customer_tag_pivot')->insert(['customer_id' => $customers[$m], 'customer_tag_id' => $tid, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach ([0 => 320, 1 => 210, 2 => 145, 3 => 60] as $ci => $pts) {
            DB::table('customers')->where('id', $customers[$ci])->update(['loyalty_points' => $pts, 'loyalty_tier' => $pts > 300 ? 'Gold' : ($pts > 150 ? 'Silver' : 'Bronze')]);
            self::ins('loyalty_transactions', ['business_id' => $biz, 'customer_id' => $customers[$ci], 'user_id' => $owner, 'type' => 'earn', 'points' => $pts, 'balance_after' => $pts, 'description' => 'Points from purchases'], $day(10));
        }

        // ── variants and batches ─────────────────────────────────────────────
        $soda = collect($products)->firstWhere('name', 'Soda 500ml');
        foreach ([['Cola', 40], ['Orange', 35], ['Lemon', 28]] as $i => [$vn, $st]) {
            self::ins('product_variants', ['product_id' => $soda['id'], 'name' => $vn, 'sku' => 'DEMO-' . strtoupper(Str::random(8)), 'price' => 70, 'cost_price' => 45, 'stock_qty' => $st, 'reorder_level' => 10, 'is_active' => true, 'sort_order' => $i]);
        }
        DB::table('products')->where('id', $soda['id'])->update(['has_variants' => true]);
        $milk = collect($products)->firstWhere('name', 'Fresh Milk 500ml');
        DB::table('products')->where('id', $milk['id'])->update(['track_batches' => true]);
        foreach ([[6, 40], [45, 30]] as $i => [$exp, $qty]) {
            self::ins('product_batches', ['business_id' => $biz, 'product_id' => $milk['id'], 'batch_number' => 'MILK-B' . ($i + 1), 'expiry_date' => $day(-$exp)->toDateString(), 'quantity' => $qty, 'cost_price' => 50, 'received_date' => $day(4)->toDateString()]);
        }
        foreach ([[$pick(0), 'addition', 50, 'Stock received'], [$pick(5), 'deduction', -3, 'Damaged in store'], [$pick(7), 'correction', 4, 'Stock count correction']] as $i => [$p, $type, $chg, $why]) {
            self::ins('stock_adjustments', ['business_id' => $biz, 'product_id' => $p['id'], 'user_id' => $owner, 'type' => $type, 'quantity_before' => 20, 'quantity_change' => $chg, 'quantity_after' => 20 + $chg, 'reason' => $why], $day(3 + $i));
        }

        // ── team: staff, leave, attendance, advances, commission ─────────────
        $leaveTypes = [];
        foreach ([['Annual Leave', 21], ['Sick Leave', 14], ['Compassionate Leave', 5]] as [$n, $d]) {
            $leaveTypes[] = self::ins('leave_types', ['business_id' => $biz, 'name' => $n, 'days_per_year' => $d, 'is_paid' => true, 'requires_approval' => true]);
        }
        $staff = [];
        foreach ([['Faith Wanjiru', 'manager', 'Shop Manager', 45000], ['Brian Otieno', 'cashier', 'Cashier', 28000], ['Mercy Chebet', 'cashier', 'Cashier', 26000]] as $i => [$name, $role, $title, $pay]) {
            $uid = self::ins('users', ['organization_id' => $orgId, 'name' => $name, 'email' => 'staff-' . Str::lower(Str::random(8)) . '@demo.invalid', 'password' => Hash::make(Str::random(40)), 'role' => $role, 'is_active' => true, 'two_factor_enabled' => false, 'is_super_admin' => false]);
            DB::table('business_user')->insert(['business_id' => $biz, 'user_id' => $uid, 'role' => $role, 'created_at' => $now, 'updated_at' => $now]);
            $spid = self::ins('staff_profiles', ['user_id' => $uid, 'business_id' => $biz, 'pay_type' => $i === 0 ? 'hybrid' : 'retainer', 'retainer_amount' => $pay, 'commission_rate' => $i === 0 ? 1.5 : 0,
                'job_title' => $title, 'department' => 'Shop floor', 'employment_date' => $day(400 - $i * 60)->toDateString(), 'id_number' => '2' . mt_rand(1000000, 9999999)]);
            $staff[] = [$uid, $spid];
            foreach ($leaveTypes as $lt) DB::table('leave_balances')->insert(['business_id' => $biz, 'user_id' => $uid, 'leave_type_id' => $lt, 'year' => (int) $now->format('Y'), 'entitled_days' => 21, 'used_days' => 0, 'remaining_days' => 21, 'created_at' => $now, 'updated_at' => $now]);
            for ($d = 9; $d >= 1; $d--) {
                $dt = $day($d); if ($dt->isSunday()) continue;
                self::ins('attendance_records', ['business_id' => $biz, 'user_id' => $uid, 'date' => $dt->toDateString(), 'clock_in' => $dt->copy()->setTime(8, mt_rand(0, 20)), 'clock_out' => $dt->copy()->setTime(17, mt_rand(0, 40)), 'status' => 'present'], $dt);
            }
        }
        self::ins('commission_rules', ['business_id' => $biz, 'staff_profile_id' => $staff[0][1], 'rule_type' => 'percentage', 'rate' => 1.5, 'is_active' => true]);
        self::ins('leave_requests', ['business_id' => $biz, 'user_id' => $staff[1][0], 'leave_type_id' => $leaveTypes[0], 'start_date' => $day(-10)->toDateString(), 'end_date' => $day(-14)->toDateString(), 'days_requested' => 5, 'reason' => 'Family visit upcountry', 'status' => 'pending']);
        self::ins('leave_requests', ['business_id' => $biz, 'user_id' => $staff[2][0], 'leave_type_id' => $leaveTypes[1], 'start_date' => $day(20)->toDateString(), 'end_date' => $day(19)->toDateString(), 'days_requested' => 2, 'reason' => 'Flu', 'status' => 'approved', 'approved_by' => $owner, 'approved_at' => $day(20)]);
        self::ins('salary_advances', ['business_id' => $biz, 'user_id' => $staff[1][0], 'amount' => 5000, 'reason' => 'School fees', 'status' => 'pending']);
        foreach ([['Staff meeting Friday at 5pm', 'Please be on time. We will review this month\'s targets.']] as [$t, $m]) {
            self::ins('memos', ['organization_id' => $orgId, 'business_id' => $biz, 'sender_id' => $owner, 'scope' => 'business', 'title' => $t, 'message' => $m, 'recipient_count' => 3]);
        }
        $ec = $ecats['Transport'];
        foreach ([['Transport to market', 'Matatu fare and porter', 1200, 'submitted'], ['Stationery for the office', 'Receipt books and pens', 850, 'approved']] as $i => [$title, $desc, $amt, $st]) {
            $cid = self::ins('expense_claims', ['business_id' => $biz, 'user_id' => $staff[1 + $i][0], 'reference' => 'EC-' . $token . '-' . ($i + 1), 'title' => $title, 'total_amount' => $amt, 'status' => $st, 'submitted_at' => $day(4), 'approved_by' => $st === 'approved' ? $owner : null]);
            self::ins('expense_claim_items', ['expense_claim_id' => $cid, 'description' => $desc, 'expense_date' => $day(5)->toDateString(), 'category' => 'Transport', 'amount' => $amt]);
        }

        // ── services and appointments ────────────────────────────────────────
        $svc = [];
        foreach ([['Home delivery (Nairobi)', 30, 200], ['Gift hamper packing', 45, 500], ['Bulk order consultation', 30, 0], ['Freezer rental (per day)', 60, 300]] as [$n, $mins, $price]) {
            $svc[] = self::ins('services', ['business_id' => $biz, 'name' => $n, 'duration_minutes' => $mins, 'price' => $price, 'category' => 'General', 'is_active' => true]);
        }
        foreach ([[1, 0, 10, 'scheduled'], [2, 1, 14, 'confirmed'], [0, 2, 9, 'completed']] as $i => [$si, $ci, $h, $st]) {
            $d = $st === 'completed' ? $day(2) : $day(-($i + 1));
            self::ins('appointments', ['business_id' => $biz, 'customer_id' => $customers[$ci], 'user_id' => $owner, 'service_id' => $svc[$si], 'booked_by' => $owner, 'appointment_date' => $d->toDateString(),
                'start_time' => sprintf('%02d:00:00', $h), 'end_time' => sprintf('%02d:30:00', $h), 'status' => $st, 'total_price' => 500]);
        }

        // ── restaurant tables, online orders ─────────────────────────────────
        foreach ([['1', 'Table 1', 'Main hall', 4], ['2', 'Table 2', 'Main hall', 4], ['3', 'Table 3', 'Main hall', 6], ['4', 'Patio 1', 'Patio', 2], ['5', 'Patio 2', 'Patio', 4], ['6', 'Counter', 'Main hall', 2]] as $i => [$no, $nm, $sec, $cap]) {
            self::ins('restaurant_tables', ['business_id' => $biz, 'number' => $no, 'name' => $nm, 'section' => $sec, 'capacity' => $cap, 'status' => 'available', 'sort_order' => $i]);
        }
        foreach ([['Lucy Wambui', 'delivered', 1], ['Ali Yusuf', 'processing', 0], ['Rose Atieno', 'pending', 0]] as $i => [$nm, $st, $ago]) {
            $p = $pick($i + 3); $q = 3; $sub = $p['sell'] * $q;
            $oid = self::ins('online_orders', ['business_id' => $biz, 'customer_name' => $nm, 'customer_phone' => '07001100' . ($i + 10), 'delivery_address' => 'Kilimani, Nairobi', 'status' => $st,
                'subtotal' => $sub, 'delivery_fee' => 200, 'total' => $sub + 200, 'payment_method' => 'mpesa', 'reference' => 'ORD-' . strtoupper(Str::random(6))], $day($ago + 1));
            self::ins('online_order_items', ['online_order_id' => $oid, 'product_id' => $p['id'], 'product_name' => $p['name'], 'quantity' => $q, 'unit_price' => $p['sell'], 'total' => $sub], $day($ago + 1));
        }

        // ── money side: budgets, bank, petty cash, assets, loan ──────────────
        self::ins('budgets', ['business_id' => $biz, 'expense_category_id' => $ecats['Transport'], 'name' => 'Transport budget', 'period_type' => 'monthly', 'year' => (int) $now->format('Y'), 'month' => (int) $now->format('n'), 'amount' => 6000]);
        self::ins('budgets', ['business_id' => $biz, 'expense_category_id' => $ecats['Electricity'], 'name' => 'Electricity budget', 'period_type' => 'monthly', 'year' => (int) $now->format('Y'), 'month' => (int) $now->format('n'), 'amount' => 5000]);
        self::ins('bank_accounts', ['business_id' => $biz, 'name' => 'Main Business Account', 'account_number' => '0123456789', 'bank_name' => 'Equity Bank', 'current_balance' => 184500]);
        $pc = self::ins('petty_cash_accounts', ['business_id' => $biz, 'name' => 'Shop petty cash', 'current_balance' => 3200]);
        foreach ([['topup', 5000, 'Float top-up', 5000, 12], ['disbursement', 1200, 'Cleaning supplies', 3800, 8], ['disbursement', 600, 'Tea and snacks', 3200, 3]] as [$type, $amt, $desc, $bal, $ago]) {
            self::ins('petty_cash_transactions', ['business_id' => $biz, 'user_id' => $owner, 'petty_cash_account_id' => $pc, 'type' => $type, 'amount' => $amt, 'description' => $desc, 'transaction_date' => $day($ago)->toDateString(), 'balance_after' => $bal], $day($ago));
        }
        foreach ([['Chest Freezer', 'Equipment', 65000, 52000, 300], ['Shop Shelving', 'Furniture', 40000, 34000, 500]] as [$n, $cat, $cost, $val, $ago]) {
            self::ins('business_assets', ['business_id' => $biz, 'user_id' => $owner, 'name' => $n, 'category' => $cat, 'purchase_date' => $day($ago)->toDateString(), 'purchase_cost' => $cost, 'depreciation_method' => 'straight_line', 'useful_life_years' => 5, 'current_value' => $val, 'status' => 'active']);
        }
        self::ins('business_loans', ['business_id' => $biz, 'user_id' => $owner, 'lender_name' => 'Kenya Women Microfinance', 'loan_type' => 'bank', 'principal_amount' => 200000, 'interest_rate' => 12, 'disbursement_date' => $day(120)->toDateString(),
            'repayment_start_date' => $day(90)->toDateString(), 'term_months' => 12, 'monthly_installment' => 17800, 'outstanding_balance' => 142400, 'status' => 'active']);
    }
}
