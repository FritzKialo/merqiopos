<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BudgetController extends Controller {

    private function businessId(): int {
        return Auth::user()->currentBusiness()->id;
    }

    public function index(Request $request) {
        $businessId = $this->businessId();
        $month = (int) $request->input('month', now()->month);
        $year  = (int) $request->input('year',  now()->year);

        // Get all expense categories for this business
        $categories = ExpenseCategory::forBusiness($businessId)->orderBy('name')->get();

        // Get budgets for this month
        $budgets = Budget::forBusiness($businessId)
            ->where('year', $year)
            ->where('month', $month)
            ->get()
            ->keyBy('expense_category_id');

        // Actual spending per category
        $actuals = Expense::forBusiness($businessId)
            ->forMonth($month, $year)
            ->select('expense_category_id', DB::raw('SUM(amount) as total'))
            ->groupBy('expense_category_id')
            ->pluck('total', 'expense_category_id');

        // Build rows — only categories that have a budget
        $rows = [];
        $totalBudget = 0;
        $totalActual = 0;

        foreach ($categories as $cat) {
            $budget = $budgets->get($cat->id);
            if (!$budget) continue;

            $budgeted = (float) $budget->amount;
            $actual   = (float) ($actuals->get($cat->id) ?? 0);
            $variance = $budgeted - $actual;
            $pct      = $budgeted > 0 ? round($actual / $budgeted * 100, 1) : 0;

            $rows[] = [
                'category' => $cat,
                'budget'   => $budget,
                'budgeted' => $budgeted,
                'actual'   => $actual,
                'variance' => $variance,
                'pct'      => $pct,
            ];

            $totalBudget += $budgeted;
            $totalActual += $actual;
        }

        // Spending with no category (or whose category no longer exists) was
        // dropped from the actuals and totals entirely, so the budget page
        // understated spending against the Expenses list. Show it as its own row.
        $knownIds = $categories->pluck('id')->all();
        $uncategorised = 0.0;
        foreach ($actuals as $catId => $actual) {
            if ($catId === null || !in_array((int) $catId, $knownIds, true)) {
                $uncategorised += (float) $actual;
            }
        }
        if ($uncategorised > 0) {
            $rows[] = [
                'category' => (object) ['id' => null, 'name' => 'Uncategorised'],
                'budget'   => null, 'budgeted' => 0, 'actual' => $uncategorised,
                'variance' => -$uncategorised, 'pct' => 100,
            ];
            $totalActual += $uncategorised;
        }

        // Petty-cash disbursements are real spending that lives in its own table.
        $petty = (float) DB::table('petty_cash_transactions')
            ->where('business_id', $businessId)->where('type', 'disbursement')
            ->whereMonth('transaction_date', $month)->whereYear('transaction_date', $year)->sum('amount');
        if ($petty > 0) {
            $rows[] = [
                'category' => (object) ['id' => null, 'name' => 'Petty cash disbursements'],
                'budget'   => null, 'budgeted' => 0, 'actual' => $petty,
                'variance' => -$petty, 'pct' => 100,
            ];
            $totalActual += $petty;
        }

        // Also include actuals for categories WITHOUT a budget but with spending
        foreach ($actuals as $catId => $actual) {
            $cat = $categories->firstWhere('id', $catId);
            if (!$cat || $budgets->has($catId)) continue;
            $rows[] = [
                'category' => $cat,
                'budget'   => null,
                'budgeted' => 0,
                'actual'   => (float) $actual,
                'variance' => -(float) $actual,
                'pct'      => 100,
            ];
            $totalActual += (float) $actual;
        }

        $months = [
            1=>'January',2=>'February',3=>'March',4=>'April',
            5=>'May',6=>'June',7=>'July',8=>'August',
            9=>'September',10=>'October',11=>'November',12=>'December',
        ];
        $years = range(now()->year - 2, now()->year + 1);

        return view('budgets.index', compact(
            'rows', 'categories', 'month', 'year',
            'months', 'years', 'totalBudget', 'totalActual'
        ));
    }

    public function store(Request $request) {
        $businessId = $this->businessId();
        $validated = $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'amount'              => 'required|numeric|min:0',
            'month'               => 'required|integer|min:1|max:12',
            'year'                => 'required|integer|min:2020|max:2099',
        ]);

        $cat = ExpenseCategory::findOrFail($validated['expense_category_id']);
        if ($cat->business_id !== $businessId) abort(403);

        Budget::updateOrCreate(
            [
                'business_id'         => $businessId,
                'expense_category_id' => $validated['expense_category_id'],
                'year'                => $validated['year'],
                'month'               => $validated['month'],
            ],
            [
                'name'        => $cat->name . ' Budget',
                'period_type' => 'monthly',
                'amount'      => $validated['amount'],
            ]
        );

        return redirect()->route('budgets.index', [
            'month' => $validated['month'],
            'year'  => $validated['year'],
        ])->with('success', 'Budget saved.');
    }

    public function destroy(Budget $budget) {
        if ($budget->business_id !== $this->businessId()) abort(403);
        $budget->delete();
        return back()->with('success', 'Budget deleted.');
    }
}
