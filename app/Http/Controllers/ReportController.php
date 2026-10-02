<?php

namespace App\Http\Controllers;

use App\Models\BusinessAsset;
use App\Models\BusinessLoan;
use App\Models\Customer;
use App\Models\CustomerCredit;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\LoanRepayment;
use App\Models\PettyCashAccount;
use App\Models\PettyCashTransaction;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller {

    private function businessId(): int {
        return Auth::user()->currentBusiness()->id;
    }

    // â”€â”€ Reports home â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function index() {
        return view('reports.index');
    }

    // â”€â”€ Profit & Loss Report â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function profitLoss(Request $request) {
        $businessId = $this->businessId();

        // Default to current month
        $month = $request->input('month',
            now()->month);
        $year  = $request->input('year',
            now()->year);

        // Revenue — POS sales plus customer Invoices. Invoices were previously
        // left out entirely, so a business billing through the Invoices module
        // saw revenue understated here (while the VAT return did include them).
        // Accrual basis: every non-draft, non-cancelled invoice by issue date,
        // excluding ones linked to a POS sale (already counted above). Invoices
        // carry no cost of goods, so they raise revenue and gross profit alike.
        $salesRevenue = Sale::forBusiness($businessId)
            ->where('sale_status', 'completed')
            ->forMonth($month, $year)
            ->sum('total_amount');

        $invoiceQuery = Invoice::forBusiness($businessId)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereNull('sale_id')
            ->whereMonth('issue_date', $month)
            ->whereYear('issue_date', $year);
        $invoiceRevenue = (float) (clone $invoiceQuery)->sum('total');
        $invoiceCount   = (clone $invoiceQuery)->count();

        $totalRevenue = $salesRevenue + $invoiceRevenue;

        // Cost of goods sold
        $cogs = DB::table('sale_items')
            ->join('sales', 'sales.id',
                '=', 'sale_items.sale_id')
            ->where('sales.business_id', $businessId)
            ->where('sales.sale_status', 'completed')
            ->whereNull('sales.deleted_at')
            ->whereMonth('sales.created_at', $month)
            ->whereYear('sales.created_at',  $year)
            ->sum(DB::raw(
                'sale_items.buying_price
                * sale_items.quantity'
            ));

        $grossProfit       = $totalRevenue - $cogs;
        $grossMargin       = $totalRevenue > 0
            ? round(($grossProfit / $totalRevenue)
                * 100, 2)
            : 0;

        // Operating expenses
        $totalExpenses = Expense::forBusiness($businessId)
            ->forMonth($month, $year)
            ->sum('amount');

        // Petty-cash disbursements are real spending too but live in their own
        // table, so they never reached the P&L. Shown as their own line.
        $pettyCashTotal = (float) DB::table('petty_cash_transactions')
            ->where('business_id', $businessId)
            ->where('type', 'disbursement')
            ->whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year)
            ->sum('amount');
        $totalExpenses += $pettyCashTotal;

        // Expenses by category
        $expensesByCategory = Expense::forBusiness(
                $businessId)
            ->forMonth($month, $year)
            ->select(
                'expense_category_id',
                DB::raw('SUM(amount) as total')
            )
            ->with('category')
            ->groupBy('expense_category_id')
            ->orderByDesc('total')
            ->get();

        $netProfit   = $grossProfit - $totalExpenses;
        $netMargin   = $totalRevenue > 0
            ? round(($netProfit / $totalRevenue)
                * 100, 2)
            : 0;

        // Daily revenue breakdown
        $dailyRevenue = Sale::forBusiness($businessId)
            ->where('sale_status', 'completed')
            ->forMonth($month, $year)
            ->select(
                DB::raw('DAY(created_at) as day'),
                DB::raw('SUM(total_amount) as revenue'),
                DB::raw('COUNT(*) as transactions')
            )
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        // Sales count
        $salesCount = Sale::forBusiness($businessId)
            ->where('sale_status', 'completed')
            ->forMonth($month, $year)
            ->count();

        $avgSaleValue = $salesCount > 0
            ? round($salesRevenue / $salesCount, 2)
            : 0;

        // Outstanding debt collected this month
        $cashCollected = Sale::forBusiness($businessId)
            ->where('sale_status', 'completed')
            ->forMonth($month, $year)
            ->sum('paid_amount');

        // Build month/year options
        $months = [
            1=>'January', 2=>'February',
            3=>'March',   4=>'April',
            5=>'May',     6=>'June',
            7=>'July',    8=>'August',
            9=>'September',10=>'October',
            11=>'November',12=>'December'
        ];

        $years = range(
            now()->year - 2, now()->year
        );

        return view('reports.profit_loss', compact(
            'totalRevenue', 'cogs',
            'grossProfit', 'grossMargin',
            'totalExpenses', 'expensesByCategory', 'pettyCashTotal',
            'netProfit', 'netMargin',
            'dailyRevenue', 'salesCount',
            'invoiceRevenue', 'invoiceCount',
            'avgSaleValue', 'cashCollected',
            'month', 'year', 'months', 'years'
        ));
    }

    // â”€â”€ Expenses Report â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function expensesReport(Request $request) {
        $businessId = $this->businessId();

        $month = $request->input('month',
            now()->month);
        $year  = $request->input('year',
            now()->year);

        $totalExpenses = Expense::forBusiness(
                $businessId)
            ->forMonth($month, $year)
            ->sum('amount');

        // By category
        $byCategory = Expense::forBusiness($businessId)
            ->forMonth($month, $year)
            ->select(
                'expense_category_id',
                DB::raw('SUM(amount)  as total'),
                DB::raw('COUNT(*)     as count'),
                DB::raw('AVG(amount)  as average'),
                DB::raw('MAX(amount)  as highest')
            )
            ->with('category')
            ->groupBy('expense_category_id')
            ->orderByDesc('total')
            ->get();

        // By payment method
        $byMethod = Expense::forBusiness($businessId)
            ->forMonth($month, $year)
            ->select(
                'payment_method',
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*)    as count')
            )
            ->groupBy('payment_method')
            ->orderByDesc('total')
            ->get();

        // Daily trend
        $dailyExpenses = Expense::forBusiness(
                $businessId)
            ->forMonth($month, $year)
            ->select(
                DB::raw('DAY(expense_date) as day'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        // All expenses this month
        $expenses = Expense::forBusiness($businessId)
            ->forMonth($month, $year)
            ->with(['category', 'user'])
            ->orderBy('expense_date', 'desc')
            ->get();

        // Month comparison (vs last month)
        $lastMonth      = $month == 1 ? 12 : $month - 1;
        $lastMonthYear  = $month == 1
            ? $year - 1 : $year;
        $lastMonthTotal = Expense::forBusiness(
                $businessId)
            ->forMonth($lastMonth, $lastMonthYear)
            ->sum('amount');

        $monthChange = $lastMonthTotal > 0
            ? round((($totalExpenses - $lastMonthTotal)
                / $lastMonthTotal) * 100, 1)
            : 0;

        $months = [
            1=>'January',  2=>'February',
            3=>'March',    4=>'April',
            5=>'May',      6=>'June',
            7=>'July',     8=>'August',
            9=>'September',10=>'October',
            11=>'November',12=>'December'
        ];

        $years = range(now()->year - 2, now()->year);

        return view('reports.expenses_report', compact(
            'totalExpenses', 'byCategory',
            'byMethod', 'dailyExpenses',
            'expenses', 'monthChange',
            'lastMonthTotal', 'month',
            'year', 'months', 'years'
        ));
    }

    // â”€â”€ Data export hub â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function export() {
        return view('reports.export');
    }

    // ── Aged Debtors Report ────────────────────────────────────────────────────
    public function agedDebtors(Request $request)
    {
        $businessId = $this->businessId();
        $today = now()->toDateString();

        // Source 1: customers who owe the business (balance_owed). This used
        // to read credit_balance, which is the opposite — store credit the
        // business owes BACK to the customer — so the report listed the wrong
        // people with the wrong amounts. Ageing follows the unpaid credit
        // sales: payments are treated as clearing the oldest sales first, so
        // what is left is aged from the newest unpaid sales backwards.
        $creditCustomers = Customer::where('business_id', $businessId)
            ->where('balance_owed', '>', 0)
            ->get();

        $unpaidSales = Sale::forBusiness($businessId)
            ->where('sale_status', 'completed')
            ->where('balance_due', '>', 0)
            ->whereIn('customer_id', $creditCustomers->pluck('id'))
            ->orderByDesc('created_at')
            ->get(['id', 'customer_id', 'balance_due', 'created_at'])
            ->groupBy('customer_id');

        // Source 2: unpaid invoices. All of them are money owed to the business
        // (the balance sheet counts them all), so ones not yet due were being
        // left out and the two reports disagreed. Not-yet-due invoices sit in
        // the first bucket. Invoices tied to a POS sale are that sale's debt,
        // already counted through the customer's balance above.
        $overdueInvoices = Invoice::forBusiness($businessId)
            ->whereNotIn('status', ['paid', 'cancelled', 'draft'])
            ->whereNull('sale_id')
            ->where('balance_due', '>', 0)
            ->with('customer')
            ->get();

        // Build debtors map
        $debtors = [];

        foreach ($creditCustomers as $customer) {
            $remaining = (float) $customer->balance_owed;
            $row = ['customer' => $customer, 'b0_30' => 0, 'b31_60' => 0, 'b61_90' => 0, 'b90plus' => 0,
                    'total' => $remaining, 'days_overdue' => 0];
            $oldestAge = 0;

            foreach ($unpaidSales->get($customer->id, collect()) as $sale) {
                if ($remaining <= 0) break;
                $part = min((float) $sale->balance_due, $remaining);
                $remaining -= $part;
                $age = (int) $sale->created_at->diffInDays(now());
                $oldestAge = max($oldestAge, $age);
                [$b0, $b30, $b60, $b90] = $this->bucketAmount($part, $age);
                $row['b0_30'] += $b0; $row['b31_60'] += $b30; $row['b61_90'] += $b60; $row['b90plus'] += $b90;
            }

            // A balance no sale accounts for (older data, manual adjustment) is
            // shown as the oldest bucket rather than being dropped from the total.
            if ($remaining > 0.005) {
                $row['b90plus'] += $remaining;
                $oldestAge = max($oldestAge, 91);
            }

            $row['days_overdue'] = $oldestAge > 30 ? $oldestAge : 0;
            $debtors[$customer->id] = $row;
        }

        foreach ($overdueInvoices as $invoice) {
            $age = $invoice->due_date && $invoice->due_date->lt(now()) ? (int) $invoice->due_date->diffInDays(now()) : 0;
            [$b0, $b30, $b60, $b90] = $this->bucketAmount((float) $invoice->balance_due, $age);
            $cid = $invoice->customer_id ?? 0;

            if (isset($debtors[$cid])) {
                $debtors[$cid]['b0_30']   += $b0;
                $debtors[$cid]['b31_60']  += $b30;
                $debtors[$cid]['b61_90']  += $b60;
                $debtors[$cid]['b90plus'] += $b90;
                $debtors[$cid]['total']   += (float) $invoice->balance_due;
                if ($age > ($debtors[$cid]['days_overdue'] ?? 0)) {
                    $debtors[$cid]['days_overdue'] = $age;
                }
            } else {
                $debtors[$cid] = [
                    'customer'    => $invoice->customer,
                    'b0_30'       => $b0,
                    'b31_60'      => $b30,
                    'b61_90'      => $b60,
                    'b90plus'     => $b90,
                    'total'       => (float) $invoice->balance_due,
                    'days_overdue'=> $age,
                ];
            }
        }

        usort($debtors, fn ($a, $b) => $b['total'] <=> $a['total']);

        $totals = [
            'b0_30'  => array_sum(array_column($debtors, 'b0_30')),
            'b31_60' => array_sum(array_column($debtors, 'b31_60')),
            'b61_90' => array_sum(array_column($debtors, 'b61_90')),
            'b90plus'=> array_sum(array_column($debtors, 'b90plus')),
            'total'  => array_sum(array_column($debtors, 'total')),
        ];

        if ($request->get('export') === 'csv') {
            $filename = 'aged-debtors-' . date('Y-m-d') . '.csv';
            $headers = [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => "attachment; filename={$filename}",
            ];
            $callback = function () use ($debtors, $totals) {
                $fh = fopen('php://output', 'w');
                \App\Support\Csv::put($fh, ['Customer', 'Phone', '0-30 Days', '31-60 Days', '61-90 Days', '90+ Days', 'Total']);
                foreach ($debtors as $d) {
                    \App\Support\Csv::put($fh, [
                        $d['customer']?->name ?? 'Walk-in',
                        $d['customer']?->phone ?? '',
                        number_format($d['b0_30'], 2),
                        number_format($d['b31_60'], 2),
                        number_format($d['b61_90'], 2),
                        number_format($d['b90plus'], 2),
                        number_format($d['total'], 2),
                    ]);
                }
                \App\Support\Csv::put($fh, ['TOTALS', '',
                    number_format($totals['b0_30'], 2),
                    number_format($totals['b31_60'], 2),
                    number_format($totals['b61_90'], 2),
                    number_format($totals['b90plus'], 2),
                    number_format($totals['total'], 2),
                ]);
                fclose($fh);
            };
            return response()->stream($callback, 200, $headers);
        }

        return view('reports.aged-debtors', compact('debtors', 'totals'));
    }

    private function bucketAmount(float $amount, int $ageDays): array
    {
        if ($ageDays <= 30) return [$amount, 0, 0, 0];
        if ($ageDays <= 60) return [0, $amount, 0, 0];
        if ($ageDays <= 90) return [0, 0, $amount, 0];
        return [0, 0, 0, $amount];
    }

    // ── Balance Sheet ────────────────────────────────────────────────────────
    public function balanceSheet(Request $request)
    {
        $businessId = $this->businessId();
        $asAt = $request->input('date', now()->toDateString());

        // Assets
        $cashFromSales = Sale::forBusiness($businessId)
            ->where('sale_status', 'completed')
            ->whereDate('created_at', '<=', $asAt)
            ->where('payment_method', '!=', 'credit')
            ->sum('paid_amount');

        $cashExpenses = Expense::forBusiness($businessId)
            ->whereDate('expense_date', '<=', $asAt)
            ->sum('amount');

        $pettyCashDisbursements = DB::table('petty_cash_transactions')
            ->join('petty_cash_accounts', 'petty_cash_accounts.id', '=', 'petty_cash_transactions.petty_cash_account_id')
            ->where('petty_cash_accounts.business_id', $businessId)
            ->whereDate('petty_cash_transactions.created_at', '<=', $asAt)
            ->where('petty_cash_transactions.type', 'disbursement')
            ->sum('petty_cash_transactions.amount');

        $loanInflows = DB::table('business_loans')
            ->where('business_id', $businessId)
            ->whereDate('disbursement_date', '<=', $asAt)
            ->sum('principal_amount');

        // Cash actually paid to suppliers — settling a supplier's
        // payable_balance is a real cash outflow (Cash down, Accounts
        // Payable down, books stay balanced) but was never subtracted here,
        // even though it correctly reduces payable_balance below in
        // Liabilities. Only 'payment' entries move cash — a 'bill' entry
        // just accrues the liability with no cash movement yet, so it's
        // deliberately excluded.
        $supplierCashPaid = SupplierPayment::where('business_id', $businessId)
            ->where('type', 'payment')
            ->whereDate('payment_date', '<=', $asAt)
            ->sum('amount');

        // Money received against Invoices (not linked to a POS sale, whose
        // payments are already in the sales figure). Only the unpaid balance
        // was counted anywhere, so every paid invoice's cash went missing.
        $invoiceCashReceived = DB::table('invoice_payments')
            ->join('invoices', 'invoices.id', '=', 'invoice_payments.invoice_id')
            ->where('invoice_payments.business_id', $businessId)
            ->whereNull('invoices.sale_id')
            ->whereDate('invoice_payments.paid_date', '<=', $asAt)
            ->sum('invoice_payments.amount');
        $cashFromSales += $invoiceCashReceived;

        $cash = $cashFromSales - $cashExpenses - $pettyCashDisbursements - $supplierCashPaid + $loanInflows; // not floored: a negative figure means recorded outflows exceed recorded receipts, which the Cash Flow report already shows

        // Real money owed TO the business: outstanding POS credit-sale
        // balances plus unpaid/partial invoice balances. credit_balance is
        // the opposite of that — store credit the business owes BACK to a
        // customer — so summing it here as an asset had this always
        // reporting Accounts Receivable as unrelated to (and usually far
        // smaller than) what customers actually owed.
        $accountsReceivable = Customer::where('business_id', $businessId)
                ->sum('balance_owed')
            + Invoice::forBusiness($businessId)
                ->whereNotIn('status', ['draft', 'cancelled'])
                ->whereNull('sale_id')
                ->whereDate('issue_date', '<=', $asAt)
                ->sum('balance_due');

        $inventory = DB::table('products')
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->sum(DB::raw('stock_qty * buying_price'));

        $fixedAssets = BusinessAsset::forBusiness($businessId)
            ->where('status', 'active')
            ->sum('current_value');

        $totalAssets = $cash + $accountsReceivable + $inventory + $fixedAssets;

        // Liabilities
        $accountsPayable = DB::table('suppliers')
            ->where('business_id', $businessId)
            ->sum('payable_balance');

        $loansOutstanding = DB::table('business_loans')
            ->where('business_id', $businessId)
            ->where('status', 'active')
            ->sum('outstanding_balance');

        $totalLiabilities = $accountsPayable + $loansOutstanding;

        $equity = $totalAssets - $totalLiabilities;

        return view('reports.balance-sheet', compact(
            'asAt', 'cash', 'accountsReceivable', 'inventory', 'fixedAssets',
            'totalAssets', 'accountsPayable', 'loansOutstanding',
            'totalLiabilities', 'equity'
        ));
    }

    // ── Cash Flow Statement ──────────────────────────────────────────────────
    public function cashFlow(Request $request)
    {
        $businessId = $this->businessId();
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to',   now()->toDateString());

        // Operating
        $cashFromSales = Sale::forBusiness($businessId)
            ->where('sale_status', 'completed')
            ->where('payment_method', '!=', 'credit')
            ->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->sum('paid_amount');

        // Invoice payments received in the period are operating cash too.
        $cashFromSales += (float) DB::table('invoice_payments')
            ->join('invoices', 'invoices.id', '=', 'invoice_payments.invoice_id')
            ->where('invoice_payments.business_id', $businessId)
            ->whereNull('invoices.sale_id')
            ->whereBetween('invoice_payments.paid_date', [$from, $to])
            ->sum('invoice_payments.amount');

        $cashExpenses = Expense::forBusiness($businessId)
            ->whereBetween(DB::raw('DATE(expense_date)'), [$from, $to])
            ->sum('amount');
        // Petty-cash disbursements are cash going out as well.
        $cashExpenses += (float) DB::table('petty_cash_transactions')
            ->where('business_id', $businessId)
            ->where('type', 'disbursement')
            ->whereBetween('transaction_date', [$from, $to])
            ->sum('amount');

        $netOperating = $cashFromSales - $cashExpenses;

        // Investing
        $assetPurchases = DB::table('business_assets')
            ->where('business_id', $businessId)
            ->whereBetween(DB::raw('DATE(purchase_date)'), [$from, $to])
            ->sum('purchase_cost');

        $netInvesting = -$assetPurchases;

        // Financing
        $loanReceipts = DB::table('business_loans')
            ->where('business_id', $businessId)
            ->whereBetween(DB::raw('DATE(disbursement_date)'), [$from, $to])
            ->sum('principal_amount');

        $loanRepayments = DB::table('loan_repayments')
            ->where('business_id', $businessId)
            ->whereBetween(DB::raw('DATE(payment_date)'), [$from, $to])
            ->sum('amount');

        $netFinancing = $loanReceipts - $loanRepayments;

        $netCashChange = $netOperating + $netInvesting + $netFinancing;

        // Opening balance = petty cash balance at start of period
        $pettyCash = PettyCashAccount::where('business_id', $businessId)->first();
        $openingBalance = $pettyCash ? (float) $pettyCash->current_balance : 0;
        $closingBalance = $openingBalance + $netCashChange;

        return view('reports.cash-flow', compact(
            'from', 'to',
            'cashFromSales', 'cashExpenses', 'netOperating',
            'assetPurchases', 'netInvesting',
            'loanReceipts', 'loanRepayments', 'netFinancing',
            'netCashChange', 'openingBalance', 'closingBalance'
        ));
    }

    // ── Cash Reconciliation Waterfall ───────────────────────────────────────
    // Answers "where did the money go, and does the till match" for a date
    // range spanning any number of shifts/terminals. Distinct from
    // cashFlow() above — that's an accounting-style statement of cash flows
    // (operating/investing/financing) for the business as a whole; this is
    // an operational till-integrity check, same purpose as
    // ShiftController::closeStore()'s per-shift variance but aggregated
    // over a period AND (the real gap that calc doesn't cover) netting out
    // cash expenses paid straight out of the till mid-shift, which nothing
    // currently subtracts anywhere.
    public function cashReconciliation(Request $request)
    {
        $businessId = $this->businessId();
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to',   now()->toDateString());

        $salesInRange = fn () => Sale::forBusiness($businessId)
            ->whereBetween(DB::raw('DATE(created_at)'), [$from, $to]);

        $grossSales = $salesInRange()->sum('total_amount');
        $voidedSales = $salesInRange()->where('sale_status', 'cancelled')->sum('total_amount');

        $completedSales = fn () => $salesInRange()->where('sale_status', 'completed');
        $netSales = $completedSales()->sum('total_amount');

        // paid_amount (actually collected), not total_amount — a partially
        // paid credit sale must show its collected portion here and the
        // rest in "outstanding" below, not the full total in one bucket.
        $cashCollected         = $completedSales()->where('payment_method', 'cash')->sum('paid_amount');
        $mpesaCollected        = $completedSales()->where('payment_method', 'mpesa')->sum('paid_amount');
        $bankTransferCollected = $completedSales()->where('payment_method', 'bank_transfer')->sum('paid_amount');
        // Outstanding is driven by balance_due, not payment_method='credit'
        // — a cash-tagged sale can still be partially paid and carry a
        // balance, and that unpaid portion belongs here regardless of tag.
        $outstanding = $completedSales()->sum('balance_due');

        $cashExpenses = Expense::forBusiness($businessId)
            ->whereBetween(DB::raw('DATE(expense_date)'), [$from, $to])
            ->where('payment_method', 'cash')
            ->sum('amount');

        // Aggregate each shift's OWN already-computed expected_cash/closing_cash
        // (frozen at close time by ShiftController::closeStore()) rather than
        // re-deriving opening-float + cash-sales here — this can never disagree
        // with what a cashier was shown when they actually closed their till.
        $closedShifts = Shift::forBusiness($businessId)
            ->where('status', 'closed')
            ->whereBetween(DB::raw('DATE(closed_at)'), [$from, $to])
            ->get();

        $shiftExpectedCash = (float) $closedShifts->sum('expected_cash');
        $actualCashCounted = (float) $closedShifts->sum('closing_cash');
        $expectedCashInTills = $shiftExpectedCash - (float) $cashExpenses;
        $variance = $actualCashCounted - $expectedCashInTills;
        $shiftCount = $closedShifts->count();

        return view('reports.cash-reconciliation', compact(
            'from', 'to',
            'grossSales', 'voidedSales', 'netSales',
            'cashCollected', 'mpesaCollected', 'bankTransferCollected', 'outstanding',
            'cashExpenses', 'shiftExpectedCash', 'expectedCashInTills',
            'actualCashCounted', 'variance', 'shiftCount'
        ));
    }

    // ── Dead Stock Report ────────────────────────────────────────────────────
    public function deadStock(Request $request)
    {
        $businessId = $this->businessId();
        $days = (int) $request->input('days', 90);
        $cutoff = now()->subDays($days)->toDateString();

        $products = DB::table('products')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('sale_items', 'sale_items.product_id', '=', 'products.id')
            ->leftJoin('sales', function ($join) use ($businessId) {
                $join->on('sales.id', '=', 'sale_items.sale_id')
                    ->where('sales.business_id', $businessId)
                    ->where('sales.sale_status', 'completed')
                    ->whereNull('sales.deleted_at');
            })
            ->where('products.business_id', $businessId)
            ->whereNull('products.deleted_at')
            ->where('products.stock_qty', '>', 0)
            // Added inside the window: not "dead", just new. Without this a
            // product created yesterday was listed as never sold.
            ->where('products.created_at', '<', $cutoff)
            ->select([
                'products.id',
                'products.name',
                'products.stock_qty',
                'products.buying_price',
                DB::raw('categories.name as category_name'),
                DB::raw('MAX(DATE(sales.created_at)) as last_sold'),
                DB::raw('products.stock_qty * products.buying_price as capital_tied_up'),
            ])
            ->groupBy('products.id', 'products.name', 'products.stock_qty', 'products.buying_price', 'categories.name')
            ->havingRaw('last_sold IS NULL OR last_sold < ?', [$cutoff])
            ->orderByDesc('capital_tied_up')
            ->get();

        $totalCapital = $products->sum('capital_tied_up');
        $totalCount   = $products->count();

        if ($request->get('export') === 'csv') {
            $filename = 'dead-stock-' . date('Y-m-d') . '.csv';
            $headers = [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => "attachment; filename={$filename}",
            ];
            $callback = function () use ($products) {
                $fh = fopen('php://output', 'w');
                \App\Support\Csv::put($fh, ['Product', 'Category', 'Stock Qty', 'Cost Price', 'Capital Tied Up', 'Last Sold', 'Days Since Sale']);
                foreach ($products as $p) {
                    $daysSince = $p->last_sold ? now()->diffInDays($p->last_sold) : 'Never';
                    \App\Support\Csv::put($fh, [
                        $p->name, $p->category_name ?? 'Uncategorised',
                        $p->stock_qty, number_format($p->buying_price, 2),
                        number_format($p->capital_tied_up, 2),
                        $p->last_sold ?? 'Never', $daysSince,
                    ]);
                }
                fclose($fh);
            };
            return response()->stream($callback, 200, $headers);
        }

        return view('reports.dead-stock', compact('products', 'totalCapital', 'totalCount', 'days'));
    }

    // ── KRA VAT Return ───────────────────────────────────────────────────────
    public function vatReturn(Request $request)
    {
        $businessId = $this->businessId();
        // The view checks $business->vat_registered to show a warning banner
        // — never actually passed to the view below, so this page has been
        // throwing "Undefined variable $business" on every request (masked
        // until now by the input-VAT query's own SQL error firing first).
        $business = Auth::user()->currentBusiness();
        $month = (int) $request->input('month', now()->month);
        $year  = (int) $request->input('year',  now()->year);

        // Output VAT: invoices. The view (reports/vat-return.blade.php) has
        // always expected $invoiceVat as an object with count/gross/vat_amount
        // (a per-source breakdown row) — the controller only ever built a bare
        // $invoiceOutputVat scalar, which the view never even referenced, so
        // this half of the "Output VAT — Tax Collected" table has been
        // silently blank (via the view's own `?? 0` fallbacks) since this
        // page's controller and view diverged.
        // Drafts and cancelled invoices are not tax events, and an invoice tied
        // to a POS sale is that sale's VAT (counted in the sales row below).
        $invoices = Invoice::forBusiness($businessId)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereNull('sale_id')
            ->whereMonth('issue_date', $month)
            ->whereYear('issue_date', $year)
            ->with('customer')
            ->get();

        $invoiceVat = (object) [
            'count'      => $invoices->count(),
            'gross'      => $invoices->sum('subtotal'),
            'vat_amount' => $invoices->sum('vat_amount'),
        ];

        // Output VAT: direct sales — same fix, now a count/gross/vat_amount
        // row instead of a bare scalar the view couldn't use.
        $salesAgg = Sale::forBusiness($businessId)
            ->where('sale_status', 'completed')
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->selectRaw('COUNT(*) as count, SUM(subtotal) as gross, SUM(vat_amount) as vat_amount')
            ->first();

        $salesVat = (object) [
            'count'      => $salesAgg->count ?? 0,
            'gross'      => $salesAgg->gross ?? 0,
            'vat_amount' => $salesAgg->vat_amount ?? 0,
        ];

        $outputVat = $invoiceVat->vat_amount + $salesVat->vat_amount;

        // Input VAT: purchase orders. Was previously approximated as a flat
        // 16% of quantity*unit_price — those columns don't even exist on
        // purchase_order_items (they're quantity_ordered/unit_cost), so this
        // query has been throwing a SQL error on every request. Since then,
        // purchase_order_items gained real per-line vat_rate/vat_amount
        // columns (see 2026_08_19_000003_add_vat_to_purchase_order_items_table
        // — genuinely populated by PurchaseOrderController::store()), so this
        // now sums the actual captured VAT instead of guessing a flat rate.
        $purchases = DB::table('purchase_orders')
            ->join('purchase_order_items', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->where('purchase_orders.business_id', $businessId)
            // Input VAT is only claimable on goods actually received — draft,
            // ordered-only and cancelled orders (including the automatic
            // reorder drafts) are not purchases yet.
            ->whereIn('purchase_orders.status', ['received', 'partially_received'])
            ->whereMonth('purchase_orders.order_date', $month)
            ->whereYear('purchase_orders.order_date', $year)
            ->whereNull('purchase_orders.deleted_at')
            ->select([
                'purchase_orders.id',
                'purchase_orders.po_number',
                DB::raw('SUM(purchase_order_items.subtotal) as taxable_amount'),
                DB::raw('SUM(purchase_order_items.vat_amount) as input_vat'),
            ])
            ->groupBy('purchase_orders.id', 'purchase_orders.po_number')
            ->get();

        $inputVat = $purchases->sum('input_vat');

        // Same count/gross/vat_amount shape as $invoiceVat/$salesVat above —
        // the view's "Input VAT" table expects $purchaseVat, which the
        // controller never set at all.
        $purchaseVat = (object) [
            'count'      => $purchases->count(),
            'gross'      => $purchases->sum('taxable_amount'),
            'vat_amount' => $inputVat,
        ];

        // The view's summary cards and page title use $netVat, not
        // $netVatPayable — another name the controller never actually supplied.
        $netVat = $outputVat - $inputVat;

        $months = [
            1=>'January',2=>'February',3=>'March',4=>'April',
            5=>'May',6=>'June',7=>'July',8=>'August',
            9=>'September',10=>'October',11=>'November',12=>'December',
        ];
        $years = range(now()->year - 2, now()->year);

        if ($request->get('export') === 'csv') {
            $filename = 'vat-return-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.csv';
            $headers = [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => "attachment; filename={$filename}",
            ];
            $callback = function () use ($invoices, $purchases, $outputVat, $inputVat, $netVat, $month, $year) {
                $fh = fopen('php://output', 'w');
                \App\Support\Csv::put($fh, ['VAT 3 Return - ' . $month . '/' . $year]);
                \App\Support\Csv::put($fh, []);
                \App\Support\Csv::put($fh, ['SECTION A: OUTPUT TAX']);
                \App\Support\Csv::put($fh, ['Invoice No', 'Customer', 'Taxable Amount', 'VAT Amount']);
                foreach ($invoices as $inv) {
                    \App\Support\Csv::put($fh, [
                        $inv->invoice_number,
                        $inv->customer?->name ?? 'Walk-in',
                        number_format($inv->subtotal, 2),
                        number_format($inv->vat_amount, 2),
                    ]);
                }
                \App\Support\Csv::put($fh, ['', 'TOTAL OUTPUT VAT', '', number_format($outputVat, 2)]);
                \App\Support\Csv::put($fh, []);
                \App\Support\Csv::put($fh, ['SECTION B: INPUT TAX']);
                \App\Support\Csv::put($fh, ['PO Number', 'Taxable Amount', 'VAT Amount (16%)']);
                foreach ($purchases as $po) {
                    \App\Support\Csv::put($fh, [
                        $po->po_number,
                        number_format($po->taxable_amount, 2),
                        number_format($po->input_vat, 2),
                    ]);
                }
                \App\Support\Csv::put($fh, ['TOTAL INPUT VAT', '', number_format($inputVat, 2)]);
                \App\Support\Csv::put($fh, []);
                \App\Support\Csv::put($fh, ['SECTION C: SUMMARY']);
                \App\Support\Csv::put($fh, ['Output VAT', number_format($outputVat, 2)]);
                \App\Support\Csv::put($fh, ['Input VAT', number_format($inputVat, 2)]);
                \App\Support\Csv::put($fh, ['Net VAT Payable', number_format($netVat, 2)]);
                fclose($fh);
            };
            return response()->stream($callback, 200, $headers);
        }

        return view('reports.vat-return', compact(
            'business',
            'invoices', 'purchases',
            'invoiceVat', 'salesVat', 'purchaseVat',
            'outputVat', 'inputVat', 'netVat',
            'month', 'year', 'months', 'years'
        ));
    }

    public function staffPerformance(Request $request) {
        $businessId = $this->businessId();
        $period     = $request->get('period', 'this_month');

        [$start, $end] = match($period) {
            'last_month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'this_year'  => [now()->startOfYear(), now()->endOfYear()],
            default      => [now()->startOfMonth(), now()->endOfMonth()],
        };

        $staffUsers = \App\Models\User::whereHas('businesses', function ($q) use ($businessId) {
            $q->where('businesses.id', $businessId);
        })->get();

        $staffStats = $staffUsers->map(function ($user) use ($businessId, $start, $end) {
            // Was unfiltered by sale_status — a cancelled sale still counted
            // toward this person's "sales" and "revenue" here, inflating
            // both for anyone who rang up a sale and then voided it.
            $completedSales = \App\Models\Sale::where('business_id', $businessId)
                ->where('user_id', $user->id)
                ->where('sale_status', 'completed')
                ->whereBetween('created_at', [$start, $end])
                ->get();

            $cancelledSales = \App\Models\Sale::where('business_id', $businessId)
                ->where('user_id', $user->id)
                ->where('sale_status', 'cancelled')
                ->whereBetween('created_at', [$start, $end])
                ->get();

            // Was hardcoded to 0 — never actually queried. SaleReturn is the
            // real model for this (both standalone returns and the
            // auto-generated refund record from a paid-sale cancellation).
            $returnsCount = \App\Models\SaleReturn::where('business_id', $businessId)
                ->where('user_id', $user->id)
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $totalRung = $completedSales->count() + $cancelledSales->count();

            return [
                'user_id'           => $user->id,
                'name'              => $user->name,
                'role'              => ucwords(str_replace('_', ' ', $user->pivot->role ?? $user->role ?? 'staff')),
                'sales_count'       => $completedSales->count(),
                'revenue'           => $completedSales->sum('total_amount'),
                'returns_count'     => $returnsCount,
                'cancelled_count'   => $cancelledSales->count(),
                'cancelled_value'   => $cancelledSales->sum('total_amount'),
                'cancellation_rate' => $totalRung > 0 ? $cancelledSales->count() / $totalRung : 0,
            ];
        })->sortByDesc('revenue')->values();

        // Team average, for display only (the footnote under the table).
        $ratesWithVolume = $staffStats->filter(fn ($s) => ($s['sales_count'] + $s['cancelled_count']) > 0);
        $avgCancellationRate = $ratesWithVolume->isNotEmpty()
            ? $ratesWithVolume->avg('cancellation_rate')
            : 0;

        // Flag anyone whose cancellation rate is a real outlier against
        // their peers — not just "cancelled a sale" (normal, sometimes
        // necessary), but a rate meaningfully above what everyone else is
        // doing. Requires at least 3 cancellations so one voided sale on a
        // quiet day doesn't get flagged from a tiny sample size.
        //
        // Deliberately leave-one-out: a person's own cancellations must
        // never count toward their own comparison baseline. Averaging
        // across the whole team (including the outlier) let a single bad
        // actor drag the average up enough to hide from a flat "2x average"
        // check — caught this directly while testing with a seeded 37.5%
        // vs. a team average of 23.3% that the outlier's own 6 cancellations
        // had inflated from what their peers were actually doing.
        $staffStats = $staffStats->map(function ($stat) use ($staffStats) {
            $peers = $staffStats
                ->reject(fn ($s) => $s['user_id'] === $stat['user_id'])
                ->filter(fn ($s) => ($s['sales_count'] + $s['cancelled_count']) > 0);

            $peerAvgRate = $peers->isNotEmpty() ? $peers->avg('cancellation_rate') : 0;
            $threshold   = max($peerAvgRate * 2, 0.10); // double the peer average, or 10% floor

            $stat['flagged'] = $stat['cancelled_count'] >= 3 && $stat['cancellation_rate'] > $threshold;
            return $stat;
        });

        return view('reports.staff-performance', compact('staffStats', 'period', 'avgCancellationRate'));
    }

    public function grossMargin(Request $request)
    {
        $businessId = $this->businessId();
        $period     = $request->get('period', 'this_month');

        [$start, $end] = match ($period) {
            'last_month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'this_year'  => [now()->startOfYear(), now()->endOfYear()],
            default      => [now()->startOfMonth(), now()->endOfMonth()],
        };

        $products = DB::table('sale_items as si')
            ->join('sales as s', 's.id', 'si.sale_id')
            ->join('products as p', 'p.id', 'si.product_id')
            ->leftJoin('categories as pc', 'pc.id', 'p.category_id')
            ->where('s.business_id', $businessId)
            ->where('s.sale_status', 'completed')
            // A bundle's summary line (tagged with its first component's
            // product_id, display-only — see SaleService) has buying_price
            // 0 by design, so including it here credited that product with
            // the bundle's full revenue as pure profit, inflating its
            // reported gross margin with no matching cost.
            ->where('si.is_bundle_summary', false)
            ->whereBetween('s.created_at', [$start, $end])
            ->groupBy('p.id', 'p.name', 'pc.name')
            ->select(
                'p.id',
                'p.name as product_name',
                DB::raw('COALESCE(pc.name, "Uncategorised") as category_name'),
                DB::raw('SUM(si.quantity) as units_sold'),
                DB::raw('SUM(si.subtotal) as revenue'),
                DB::raw('SUM(si.quantity * COALESCE(si.buying_price, 0)) as cost'),
                DB::raw('SUM(si.subtotal) - SUM(si.quantity * COALESCE(si.buying_price, 0)) as gross_profit')
            )
            ->orderByDesc('gross_profit')
            ->get();

        $totalRevenue  = $products->sum('revenue');
        $totalCost     = $products->sum('cost');
        $totalProfit   = $products->sum('gross_profit');
        $overallMargin = $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 1) : 0;

        $byCategory = $products->groupBy('category_name')->map(fn ($g) => [
            'revenue'    => $g->sum('revenue'),
            'cost'       => $g->sum('cost'),
            'profit'     => $g->sum('gross_profit'),
            'margin_pct' => $g->sum('revenue') > 0
                ? round(($g->sum('gross_profit') / $g->sum('revenue')) * 100, 1) : 0,
        ]);

        return view('reports.gross-margin', compact(
            'products', 'totalRevenue', 'totalCost', 'totalProfit',
            'overallMargin', 'byCategory', 'period'
        ));
    }

    public function salesForecast(Request $request) {
        $businessId = $this->businessId();
        $months     = (int) $request->get('months', 3);

        // 6 months of actuals, completed sales only (cancelled sales used to
        // be counted as revenue here).
        $history = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = now()->subMonths($i);
            $history[] = (float) \App\Models\Sale::where('business_id', $businessId)
                ->where('sale_status', 'completed')
                ->whereYear('created_at', $d->year)
                ->whereMonth('created_at', $d->month)
                ->sum('total_amount');
        }

        // The last entry is the month in progress, so it is always short of a
        // full month. The baseline and growth trend use complete months only —
        // otherwise every forecast said sales were falling early in a month.
        $complete   = array_slice($history, 0, 5);
        if (array_sum($complete) <= 0 && $history[5] > 0) {
            $complete = [$history[5], $history[5], $history[5], $history[5], $history[5]];
        }
        $avgMonthly = array_sum($complete) / count($complete);
        $last       = $complete[4];
        $prev       = $complete[3];
        $growthRate = $prev > 0 ? (($last - $prev) / $prev) * 100 : 0;

        // One month's swing is noise, not a trend: clamp what gets compounded.
        $stepRate = max(-10, min(10, $growthRate)) / 100;

        $forecast = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = now()->subMonths($i);
            $forecast[] = [
                'month'     => $d->format('M Y'),
                'actual'    => $history[5 - $i],
                'projected' => $avgMonthly * (1 + $stepRate * (5 - $i) / 5),
            ];
        }
        for ($i = 1; $i <= $months; $i++) {
            $d = now()->addMonths($i);
            $forecast[] = [
                'month'     => $d->format('M Y'),
                'actual'    => null,
                'projected' => $last > 0 ? $last * pow(1 + $stepRate, $i) : $avgMonthly,
            ];
        }

        return view('reports.sales-forecast', compact('forecast', 'avgMonthly', 'growthRate', 'months'));
    }
}
