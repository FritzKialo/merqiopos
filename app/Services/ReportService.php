<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\MpesaTransaction;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportService
{
    /**
     * Get all data needed for the main dashboard.
     * Consolidates multiple queries into fewer round-trips.
     */
    public function getDashboardData(int $businessId): array
    {
        // ── Today + month sales (DB-agnostic whereDate/whereMonth) ───────────
        $baseSales = Sale::forBusiness($businessId)->where('sale_status', 'completed');

        $todaySalesVal = (clone $baseSales)->whereDate('created_at', today())->sum('total_amount');
        $todayCountVal = (clone $baseSales)->whereDate('created_at', today())->count();
        $monthSalesVal = (clone $baseSales)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at',  now()->year)
            ->sum('total_amount');

        // Customer Invoices count toward revenue too — same rule as the P&L
        // report: non-draft, non-cancelled, by issue date, excluding ones
        // already linked to a POS sale (that Sale's total is counted above,
        // so including the linked Invoice too would double it). Previously
        // this dashboard only ever summed Sale.total_amount, so a business
        // billing through Invoices saw "Month revenue" understated here even
        // though the VAT return and (as of this session) the P&L report both
        // counted those same invoices correctly.
        $monthInvoiceVal = (float) Invoice::forBusiness($businessId)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereNull('sale_id')
            ->whereMonth('issue_date', now()->month)
            ->whereYear('issue_date', now()->year)
            ->sum('total');

        $salesAgg = (object)[
            'today_sales' => $todaySalesVal,
            'today_count' => $todayCountVal,
            'month_sales' => $monthSalesVal + $monthInvoiceVal,
        ];

        // ── Expenses (two separate because of different date column) ───────
        $todayExpenses = Expense::forBusiness($businessId)
            ->whereDate('expense_date', today())
            ->sum('amount');

        $monthExpenses = Expense::forBusiness($businessId)
            ->thisMonth()
            ->sum('amount');

        // ── Previous month (for trend badges + insights below) ──────────────
        $prevMonthStart = now()->subMonthNoOverflow()->startOfMonth();
        $prevMonthEnd   = now()->subMonthNoOverflow()->endOfMonth();

        $prevMonthSales = Sale::forBusiness($businessId)
            ->where('sale_status', 'completed')
            ->whereBetween('created_at', [$prevMonthStart, $prevMonthEnd])
            ->sum('total_amount');

        // Same invoice inclusion as the current month above — otherwise the
        // trend badge compares an invoice-inclusive figure against an
        // invoice-less one, producing a misleading swing on any business
        // that bills through Invoices.
        $prevMonthSales += (float) Invoice::forBusiness($businessId)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereNull('sale_id')
            ->whereBetween('issue_date', [$prevMonthStart->toDateString(), $prevMonthEnd->toDateString()])
            ->sum('total');

        $prevMonthExpenses = Expense::forBusiness($businessId)
            ->whereBetween('expense_date', [$prevMonthStart, $prevMonthEnd])
            ->sum('amount');

        $prevMonthProfit = $prevMonthSales - $prevMonthExpenses;

        // ── Inventory (one aggregated query) ───────────────────────────────
        $inventoryAgg = Product::forBusiness($businessId)
            ->active()
            ->selectRaw("
                COUNT(*)                                    AS total_products,
                SUM(CASE WHEN reorder_level > 0 AND stock_qty <= reorder_level
                    THEN 1 ELSE 0 END)                     AS low_stock_count,
                SUM(stock_qty * buying_price)               AS stock_value
            ")
            // This is a separate raw query from Product::scopeLowStock() (fixed
            // earlier this session as bug #51) — that fix didn't cover this one.
            // reorder_level = 0 means "reorder tracking disabled for this
            // product" everywhere else in the codebase; without the same guard
            // here, the main dashboard's own "low stock" count would include
            // products that explicitly opted out the moment their stock hit 0.
            ->first();

        // ── Customers ──────────────────────────────────────────────────────
        $customerAgg = Customer::forBusiness($businessId)
            ->selectRaw('COUNT(*) AS total_customers, SUM(balance_owed) AS total_debt')
            ->first();

        // ── Revenue vs Expenses (default: last 6 months) ────────────────────
        // Extracted into revenueExpenseSeries() so the dashboard's date-range
        // picker / compare-to-previous-period AJAX endpoints (DashboardController
        // ::chartData) can reuse the exact same grouping logic against an
        // arbitrary caller-supplied range instead of duplicating it.
        $chartRangeEnd   = now();
        $chartRangeStart = now()->subMonths(5)->startOfMonth();
        $series = $this->revenueExpenseSeries($businessId, $chartRangeStart, $chartRangeEnd);

        $chartLabels   = $series['labels'];
        $chartRevenue  = $series['revenue'];
        $chartExpenses = $series['expenses'];
        $chartProfit   = $series['profit'];

        // ── Top 5 products this month (raw join, soft-delete safe) ─────────
        $topProducts = DB::table('sale_items')
            ->join('sales',    'sales.id',    '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.business_id', $businessId)
            ->where('sales.sale_status', 'completed')
            ->whereNull('sales.deleted_at')
            ->whereNull('products.deleted_at')
            // A bundle's summary line is tagged with its first component's
            // product_id purely for receipt display (see SaleService) — it
            // isn't a real, standalone sale of that product. Left in, it
            // credited that product with the bundle's full quantity/revenue
            // on top of its own real sales, inflating this "top products"
            // ranking for whichever product happened to be listed first in
            // any bundle.
            ->where('sale_items.is_bundle_summary', false)
            ->whereMonth('sales.created_at', now()->month)
            ->whereYear('sales.created_at',  now()->year)
            ->select(
                'products.name',
                DB::raw('SUM(sale_items.quantity) as total_qty'),
                DB::raw('SUM(sale_items.subtotal) as total_revenue')
            )
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        // ── Recent 5 sales ─────────────────────────────────────────────────
        $recentSales = Sale::with('customer')
            ->forBusiness($businessId)
            ->where('sale_status', 'completed')
            ->latest()
            ->limit(5)
            ->get();

        // ── Low stock products ─────────────────────────────────────────────
        $lowStockProducts = Product::forBusiness($businessId)
            ->active()
            ->lowStock()
            ->orderBy('stock_qty')
            ->limit(5)
            ->get();

        // ── Overdue invoices (unpaid sales older than 30 days) ─────────────
        $overdueInvoices = Sale::with('customer')
            ->forBusiness($businessId)
            ->where('payment_status', '!=', 'paid')
            ->where('sale_status', 'completed')
            ->where('created_at', '<', now()->subDays(30))
            ->orderBy('created_at')
            ->limit(5)
            ->get();

        $overdueCount  = Sale::forBusiness($businessId)
            ->where('payment_status', '!=', 'paid')
            ->where('sale_status', 'completed')
            ->where('created_at', '<', now()->subDays(30))
            ->count();

        $overdueAmount = Sale::forBusiness($businessId)
            ->where('payment_status', '!=', 'paid')
            ->where('sale_status', 'completed')
            ->where('created_at', '<', now()->subDays(30))
            ->sum('balance_due');

        // ── Sales awaiting payment (recent, non-credit) ────────────────────
        // Distinct from "Overdue invoices" above, which only catches sales
        // 30+ days unpaid — a credit-sale aging concern. This catches the
        // much more urgent case: a sale created via cash/M-Pesa/bank
        // (methods expected to settle immediately, not deferred like
        // 'credit') that's still unpaid days later. Stock is decremented
        // at sale creation regardless of payment method — the POS flow
        // creates the Sale and removes stock BEFORE an M-Pesa STK push is
        // even confirmed, so a customer who cancels the prompt or lets it
        // time out leaves a real sale on the books, stock already gone,
        // with nothing tying it back to what actually happened unless
        // someone happens to check. Capped at the same 30-day boundary so
        // it never overlaps with "Overdue invoices" above.
        $awaitingPaymentQuery = Sale::forBusiness($businessId)
            ->where('sale_status', 'completed')
            ->where('payment_status', '!=', 'paid')
            ->where('payment_method', '!=', 'credit')
            ->where('created_at', '>=', now()->subDays(30));

        $awaitingPaymentCount  = (clone $awaitingPaymentQuery)->count();
        $awaitingPaymentAmount = (clone $awaitingPaymentQuery)->sum('balance_due');

        $awaitingPaymentSales = (clone $awaitingPaymentQuery)
            ->with('customer')
            ->orderBy('created_at')
            ->limit(5)
            ->get();

        // Flag which of those had an M-Pesa attempt that actually FAILED
        // (vs. simply never attempted, e.g. a cash sale nobody paid) — a
        // real, confirmed failure is a stronger signal than just "unpaid".
        $failedMpesaSaleIds = MpesaTransaction::where('type', 'sale')
            ->where('status', 'FAILED')
            ->whereIn('sale_id', $awaitingPaymentSales->pluck('id'))
            ->pluck('sale_id')
            ->unique();

        // ── Top 5 customers this month by revenue ──────────────────────────
        $topCustomers = DB::table('sales')
            ->join('customers', 'customers.id', '=', 'sales.customer_id')
            ->where('sales.business_id', $businessId)
            ->where('sales.sale_status', 'completed')
            ->whereNull('sales.deleted_at')
            ->whereNull('customers.deleted_at')
            ->whereMonth('sales.created_at', now()->month)
            ->whereYear('sales.created_at', now()->year)
            ->select(
                'customers.id',
                'customers.name',
                DB::raw('SUM(sales.total_amount) as total_spent'),
                DB::raw('COUNT(sales.id) as order_count')
            )
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('total_spent')
            ->limit(5)
            ->get();

        // ── Cash collected vs outstanding this month ───────────────────────
        $cashCollected = Sale::forBusiness($businessId)
            ->where('sale_status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('paid_amount');

        $cashOutstanding = Sale::forBusiness($businessId)
            ->where('sale_status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('balance_due');

        // ── Daily revenue last 14 days (for sparkline) ─────────────────────
        $dailyRevenue = Sale::forBusiness($businessId)
            ->where('sale_status', 'completed')
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date');

        $sparklineData = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $sparklineData[] = round($dailyRevenue[$date] ?? 0, 2);
        }

        // ── Pending quotes count (if quotes table exists) ──────────────────
        $pendingQuotesCount = 0;
        try {
            $pendingQuotesCount = DB::table('quotes')
                ->where('business_id', $businessId)
                ->whereIn('status', ['draft', 'sent'])
                ->whereNull('deleted_at')
                ->count();
        } catch (\Exception $e) { /* table may not exist yet */ }

        // ── Upcoming recurring invoices (if table exists) ──────────────────
        $upcomingRecurringCount = 0;
        try {
            $upcomingRecurringCount = DB::table('recurring_invoices')
                ->where('business_id', $businessId)
                ->where('is_active', true)
                ->whereDate('next_run_date', '<=', now()->addDays(7))
                ->whereNull('deleted_at')
                ->count();
        } catch (\Exception $e) { /* table may not exist yet */ }

        // ── New visualizations: category mix, payment mix, peak hours ──────
        $categoryBreakdown = $this->salesByCategory($businessId);
        $paymentBreakdown  = $this->paymentMethodBreakdown($businessId);
        $peakHours         = $this->peakHours($businessId);

        // ── Auto-generated insights & recommendations ────────────────────────
        $insights = $this->generateInsights([
            'monthSales'        => $salesAgg->month_sales ?? 0,
            'prevMonthSales'    => $prevMonthSales,
            'monthExpenses'     => $monthExpenses,
            'prevMonthExpenses' => $prevMonthExpenses,
            'monthProfit'       => ($salesAgg->month_sales ?? 0) - $monthExpenses,
            'cashCollected'     => $cashCollected,
            'cashOutstanding'   => $cashOutstanding,
            'lowStockCount'     => $inventoryAgg->low_stock_count ?? 0,
            'overdueCount'      => $overdueCount,
            'overdueAmount'     => $overdueAmount,
            'topProducts'       => $topProducts,
        ]);

        return [
            'todaySales'             => $salesAgg->today_sales    ?? 0,
            'todayTransactions'      => $salesAgg->today_count    ?? 0,
            'todayExpenses'          => $todayExpenses,
            'todayProfit'            => ($salesAgg->today_sales ?? 0) - $todayExpenses,
            'monthSales'             => $salesAgg->month_sales    ?? 0,
            'monthExpenses'          => $monthExpenses,
            'monthProfit'            => ($salesAgg->month_sales ?? 0) - $monthExpenses,
            'totalProducts'          => $inventoryAgg->total_products  ?? 0,
            'lowStockCount'          => $inventoryAgg->low_stock_count ?? 0,
            'stockValue'             => $inventoryAgg->stock_value     ?? 0,
            'totalCustomers'         => $customerAgg->total_customers  ?? 0,
            'outstandingDebt'        => $customerAgg->total_debt       ?? 0,
            'chartLabels'            => $chartLabels,
            'chartRevenue'           => $chartRevenue,
            'chartExpenses'          => $chartExpenses,
            'chartProfit'            => $chartProfit,
            'topProducts'            => $topProducts,
            'topCustomers'           => $topCustomers,
            'recentSales'            => $recentSales,
            'lowStockProducts'       => $lowStockProducts,
            'overdueInvoices'        => $overdueInvoices,
            'overdueCount'           => $overdueCount,
            'overdueAmount'          => $overdueAmount,
            'awaitingPaymentSales'   => $awaitingPaymentSales,
            'awaitingPaymentCount'   => $awaitingPaymentCount,
            'awaitingPaymentAmount'  => $awaitingPaymentAmount,
            'failedMpesaSaleIds'     => $failedMpesaSaleIds,
            'cashCollected'          => $cashCollected,
            'cashOutstanding'        => $cashOutstanding,
            'sparklineData'          => $sparklineData,
            'pendingQuotesCount'     => $pendingQuotesCount,
            'upcomingRecurringCount' => $upcomingRecurringCount,
            'chartRangeStart'        => $chartRangeStart->toDateString(),
            'chartRangeEnd'          => $chartRangeEnd->toDateString(),
            'prevMonthSales'         => $prevMonthSales,
            'prevMonthExpenses'      => $prevMonthExpenses,
            'prevMonthProfit'        => $prevMonthProfit,
            'insights'               => $insights,
            'categoryLabels'         => $categoryBreakdown['labels'],
            'categoryRevenue'        => $categoryBreakdown['revenue'],
            'paymentLabels'          => $paymentBreakdown['labels'],
            'paymentRevenue'         => $paymentBreakdown['revenue'],
            'peakHourLabels'         => $peakHours['labels'],
            'peakHourCounts'         => $peakHours['counts'],
        ];
    }

    /**
     * Turn a handful of already-computed dashboard figures into a short,
     * human-readable list of insights/recommendations. Deliberately reuses
     * numbers getDashboardData() already queried — no extra DB round-trips —
     * so this stays cheap enough to run on every dashboard load.
     *
     * Each insight: ['type' => success|warning|danger|info, 'icon' => key,
     * 'title' => string, 'text' => string, optional 'action_route',
     * 'action_params', 'action_label'].
     */
    private function generateInsights(array $d): array
    {
        $insights = [];

        // Revenue trend vs previous month
        if ($d['prevMonthSales'] > 0) {
            $change = round((($d['monthSales'] - $d['prevMonthSales']) / $d['prevMonthSales']) * 100, 1);
            if ($change >= 10) {
                $insights[] = [
                    'type' => 'success', 'icon' => 'trend-up',
                    'title' => "Revenue is up {$change}% vs last month",
                    'text'  => "Whatever you did last month is working — keep it up.",
                ];
            } elseif ($change <= -10) {
                $insights[] = [
                    'type' => 'danger', 'icon' => 'trend-down',
                    'title' => 'Revenue is down ' . abs($change) . '% vs last month',
                    'text'  => 'Consider reviewing pricing, running a promotion, or checking your best sellers for stockouts.',
                ];
            }
        }

        // Profit margin this month
        if ($d['monthSales'] > 0) {
            $margin = round(($d['monthProfit'] / $d['monthSales']) * 100, 1);
            if ($margin < 10) {
                $insights[] = [
                    'type' => 'warning', 'icon' => 'alert',
                    'title' => "Thin profit margin ({$margin}%)",
                    'text'  => 'Review your expenses or pricing this month to protect your margins.',
                    'action_route' => 'reports.gross-margin', 'action_label' => 'View margin report',
                ];
            } elseif ($margin >= 30) {
                $insights[] = [
                    'type' => 'success', 'icon' => 'check',
                    'title' => "Healthy margin ({$margin}%)",
                    'text'  => "You're keeping {$margin}% of revenue as profit this month.",
                ];
            }
        }

        // Expense growth vs previous month
        if ($d['prevMonthExpenses'] > 0) {
            $expChange = round((($d['monthExpenses'] - $d['prevMonthExpenses']) / $d['prevMonthExpenses']) * 100, 1);
            if ($expChange >= 20) {
                $insights[] = [
                    'type' => 'warning', 'icon' => 'alert',
                    'title' => "Expenses up {$expChange}% vs last month",
                    'text'  => 'Check your Expenses report to see what changed.',
                    'action_route' => 'expenses.index', 'action_label' => 'View expenses',
                ];
            }
        }

        // Cash collection rate this month
        if ($d['monthSales'] > 0) {
            $collectRate = round(($d['cashCollected'] / $d['monthSales']) * 100, 1);
            if ($collectRate < 70 && $d['cashOutstanding'] > 0) {
                $insights[] = [
                    'type' => 'warning', 'icon' => 'cash',
                    'title' => "Only {$collectRate}% of sales collected so far",
                    'text'  => 'KES ' . number_format($d['cashOutstanding'], 2) . ' is still outstanding this month. Follow up on unpaid invoices.',
                    'action_route' => 'sales.index', 'action_params' => ['payment_status' => 'unpaid'], 'action_label' => 'View unpaid',
                ];
            }
        }

        // Low stock
        if ($d['lowStockCount'] > 0) {
            $insights[] = [
                'type' => 'warning', 'icon' => 'box',
                'title' => $d['lowStockCount'] . ' product' . ($d['lowStockCount'] > 1 ? 's' : '') . ' low on stock',
                'text'  => 'Restock soon to avoid missed sales.',
                'action_route' => 'inventory.index', 'action_params' => ['low_stock' => 1], 'action_label' => 'View low stock',
            ];
        }

        // Overdue invoices
        if ($d['overdueCount'] > 0) {
            $insights[] = [
                'type' => 'danger', 'icon' => 'clock',
                'title' => $d['overdueCount'] . ' overdue invoice' . ($d['overdueCount'] > 1 ? 's' : ''),
                'text'  => 'KES ' . number_format($d['overdueAmount'], 2) . ' has been outstanding for over 30 days. Send reminders to collect it.',
                'action_route' => 'sales.index', 'action_params' => ['payment_status' => 'unpaid'], 'action_label' => 'View overdue',
            ];
        }

        // Revenue concentration in a single product
        if ($d['topProducts']->isNotEmpty() && $d['monthSales'] > 0) {
            $top = $d['topProducts']->first();
            $share = round(($top->total_revenue / $d['monthSales']) * 100, 1);
            if ($share >= 40) {
                $insights[] = [
                    'type' => 'info', 'icon' => 'info',
                    'title' => "{$top->name} drives {$share}% of this month's revenue",
                    'text'  => "Your revenue leans heavily on one product. Consider diversifying, and make sure it never goes out of stock.",
                ];
            }
        }

        if (empty($insights)) {
            $insights[] = [
                'type' => 'success', 'icon' => 'check',
                'title' => 'Everything looks steady',
                'text'  => 'No urgent issues detected this month. Keep an eye on your numbers regularly.',
            ];
        }

        // Cap so a genuinely bad month doesn't grow the panel unbounded.
        return array_slice($insights, 0, 6);
    }

    /**
     * Revenue/expenses/profit series for a business over an arbitrary date
     * range. Groups by day when the range is 62 days or shorter (so a "Last
     * 7 days" pick isn't squashed into a single monthly bucket), otherwise
     * groups by month. Used both for the dashboard's default 6-month chart
     * and for the date-range-picker / compare-to-previous-period AJAX
     * endpoint (DashboardController::chartData).
     *
     * @return array{labels:array,revenue:array,expenses:array,profit:array,granularity:string}
     */
    public function revenueExpenseSeries(int $businessId, Carbon $start, Carbon $end): array
    {
        $start = $start->copy()->startOfDay();
        $end   = $end->copy()->endOfDay();
        $granularity = $start->diffInDays($end) <= 62 ? 'day' : 'month';

        $sales = Sale::forBusiness($businessId)
            ->where('sale_status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->get(['created_at', 'total_amount']);

        $expenses = Expense::forBusiness($businessId)
            ->whereBetween('expense_date', [$start, $end])
            ->get(['expense_date', 'amount']);

        $keyFn = fn (Carbon $d) => $granularity === 'day' ? $d->format('Y-m-d') : $d->format('Y-m');

        $revByKey = $sales->groupBy(fn ($r) => $keyFn($r->created_at))
            ->map(fn ($g) => $g->sum('total_amount'));
        $expByKey = $expenses->groupBy(fn ($r) => $keyFn($r->expense_date))
            ->map(fn ($g) => $g->sum('amount'));

        $labels = $revenue = $expensesOut = $profit = [];

        if ($granularity === 'day') {
            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $key = $d->format('Y-m-d');
                $rev = $revByKey[$key] ?? 0;
                $exp = $expByKey[$key] ?? 0;
                $labels[]      = $d->format('j M');
                $revenue[]     = round($rev, 2);
                $expensesOut[] = round($exp, 2);
                $profit[]      = round($rev - $exp, 2);
            }
        } else {
            $monthNames = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec'];
            for ($m = $start->copy()->startOfMonth(); $m->lte($end); $m->addMonth()) {
                $key = $m->format('Y-m');
                $rev = $revByKey[$key] ?? 0;
                $exp = $expByKey[$key] ?? 0;
                $labels[]      = $monthNames[$m->month] . ' ' . $m->format('y');
                $revenue[]     = round($rev, 2);
                $expensesOut[] = round($exp, 2);
                $profit[]      = round($rev - $exp, 2);
            }
        }

        return [
            'labels'      => $labels,
            'revenue'     => $revenue,
            'expenses'    => $expensesOut,
            'profit'      => $profit,
            'granularity' => $granularity,
        ];
    }

    /**
     * This month's revenue grouped by product category, for the dashboard's
     * "Sales by category" donut chart. Uncategorized products are grouped
     * under "Uncategorized" rather than dropped, so the total still
     * reconciles with topProducts/monthSales.
     *
     * @return array{labels:array,revenue:array}
     */
    public function salesByCategory(int $businessId): array
    {
        $rows = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('sales.business_id', $businessId)
            ->where('sales.sale_status', 'completed')
            ->whereNull('sales.deleted_at')
            ->whereNull('products.deleted_at')
            // See the same guard in topProducts() above — without it, a
            // bundle's full revenue is attributed to whichever category its
            // first component belongs to, rather than being excluded from
            // this per-product breakdown as the display-only line it is.
            ->where('sale_items.is_bundle_summary', false)
            ->whereMonth('sales.created_at', now()->month)
            ->whereYear('sales.created_at', now()->year)
            ->select(
                DB::raw('COALESCE(categories.name, "Uncategorized") as category_name'),
                DB::raw('SUM(sale_items.subtotal) as total_revenue')
            )
            ->groupBy('category_name')
            ->orderByDesc('total_revenue')
            ->limit(8)
            ->get();

        return [
            'labels'  => $rows->pluck('category_name')->all(),
            'revenue' => $rows->pluck('total_revenue')->map(fn ($v) => round($v, 2))->all(),
        ];
    }

    /**
     * This month's revenue grouped by payment method, for the dashboard's
     * "Payment method mix" donut chart. Labels are prettified here so the
     * view/JS don't need their own copy of the mapping.
     *
     * @return array{labels:array,revenue:array}
     */
    public function paymentMethodBreakdown(int $businessId): array
    {
        $labels = [
            'cash'         => 'Cash',
            'mpesa'        => 'M-Pesa',
            'bank'         => 'Bank Transfer',
            'cheque'       => 'Cheque',
            'store_credit' => 'Store Credit',
            'credit'       => 'Credit',
            'card'         => 'Card',
        ];

        $rows = Sale::forBusiness($businessId)
            ->where('sale_status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->selectRaw('COALESCE(payment_method, "cash") as method, SUM(total_amount) as total')
            ->groupBy('method')
            ->orderByDesc('total')
            ->get();

        return [
            'labels'  => $rows->pluck('method')->map(fn ($m) => $labels[$m] ?? ucfirst(str_replace('_', ' ', $m)))->all(),
            'revenue' => $rows->pluck('total')->map(fn ($v) => round($v, 2))->all(),
        ];
    }

    /**
     * Sales volume by hour of day over the last 30 days, for the dashboard's
     * "Peak hours" bar chart — helps owners see when to staff up. Always
     * returns all 24 hours (zero-filled) so the chart's x-axis is stable.
     *
     * @return array{labels:array,counts:array}
     */
    public function peakHours(int $businessId): array
    {
        $driver = DB::connection()->getDriverName();
        $hourExpr = $driver === 'sqlite' ? "CAST(strftime('%H', created_at) AS INTEGER)" : 'HOUR(created_at)';

        $rows = Sale::forBusiness($businessId)
            ->where('sale_status', 'completed')
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw("{$hourExpr} as hour, COUNT(*) as cnt")
            ->groupBy('hour')
            ->pluck('cnt', 'hour');

        $labels = $counts = [];
        for ($h = 0; $h < 24; $h++) {
            $labels[] = Carbon::createFromTime($h)->format('g A');
            $counts[] = (int) ($rows[$h] ?? 0);
        }

        return ['labels' => $labels, 'counts' => $counts];
    }

    /**
     * Lightweight today/month KPI snapshot for the dashboard's live-updating
     * cards (polled periodically without a full page reload). Deliberately
     * separate from getDashboardData() — that method does much more (top
     * products, recent sales, etc.) that a 60-second poll shouldn't repeat.
     */
    public function kpiSnapshot(int $businessId): array
    {
        $baseSales = Sale::forBusiness($businessId)->where('sale_status', 'completed');

        $todaySales = (clone $baseSales)->whereDate('created_at', today())->sum('total_amount');
        $monthSales = (clone $baseSales)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at',  now()->year)
            ->sum('total_amount');

        // Keep this live-polled snapshot consistent with getDashboardData()
        // above — same invoice-revenue rule — otherwise the card would
        // flip between an invoice-inclusive figure on page load and an
        // invoice-less one every time the periodic poll refreshes it.
        $monthSales += (float) Invoice::forBusiness($businessId)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereNull('sale_id')
            ->whereMonth('issue_date', now()->month)
            ->whereYear('issue_date', now()->year)
            ->sum('total');

        $todayExpenses = Expense::forBusiness($businessId)->whereDate('expense_date', today())->sum('amount');
        $monthExpenses = Expense::forBusiness($businessId)->thisMonth()->sum('amount');

        return [
            'todaySales'    => round($todaySales, 2),
            'todayExpenses' => round($todayExpenses, 2),
            'todayProfit'   => round($todaySales - $todayExpenses, 2),
            'monthSales'    => round($monthSales, 2),
            'monthExpenses' => round($monthExpenses, 2),
            'monthProfit'   => round($monthSales - $monthExpenses, 2),
        ];
    }
}
