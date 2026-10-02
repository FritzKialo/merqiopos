<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller {

    private function businessId(): int {
        return Auth::user()->currentBusiness()->id;
    }

    // â”€â”€ List all expenses â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function index(Request $request) {
        $businessId = $this->businessId();
        $user       = Auth::user();
        $isCashier  = $user->hasRole('cashier');

        $query = Expense::with(['category', 'user'])
            ->forBusiness($businessId);

        // Cashiers only see their own expenses
        if ($isCashier) {
            $query->where('user_id', $user->id);
        }

        // Search by title
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                  ->orWhere('description',
                        'like', "%{$s}%")
                  ->orWhere('reference',
                        'like', "%{$s}%");
            });
        }

        // Filter by category
        if ($request->filled('category_id')) {
            $query->where(
                'expense_category_id',
                $request->category_id
            );
        }

        // Filter by payment method
        if ($request->filled('payment_method')) {
            $query->where(
                'payment_method',
                $request->payment_method
            );
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate(
                'expense_date', '>=',
                $request->date_from
            );
        }
        if ($request->filled('date_to')) {
            $query->whereDate(
                'expense_date', '<=',
                $request->date_to
            );
        }

        $expenses = $query
            ->orderBy('expense_date', 'desc')
            ->orderBy('created_at',   'desc')
            ->paginate(15)
            ->withQueryString();

        // Summary stats — scoped to own expenses for cashiers
        $statsBase = Expense::forBusiness($businessId);
        if ($isCashier) $statsBase->where('user_id', $user->id);

        $stats = [
            'today'       => (clone $statsBase)->whereDate('expense_date', today())->sum('amount'),
            'this_month'  => (clone $statsBase)->thisMonth()->sum('amount'),
            'this_year'   => (clone $statsBase)->thisYear()->sum('amount'),
            'total_count' => (clone $statsBase)->thisMonth()->count(),
        ];

        // Category breakdown for this month
        $breakdownBase = Expense::forBusiness($businessId)->thisMonth();
        if ($isCashier) $breakdownBase->where('user_id', $user->id);

        $categoryBreakdown = $breakdownBase
            ->select(
                'expense_category_id',
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->with('category')
            ->groupBy('expense_category_id')
            ->orderByDesc('total')
            ->get();

        // Monthly trend (last 6 months)
        $trendBase = Expense::forBusiness($businessId)
            ->where('expense_date', '>=', now()->subMonths(5)->startOfMonth());
        if ($isCashier) $trendBase->where('user_id', $user->id);

        $monthlyTrend = $trendBase
            ->select(
                DB::raw('YEAR(expense_date)  as year'),
                DB::raw('MONTH(expense_date) as month'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        $categories = ExpenseCategory::forBusiness(
            $businessId
        )->orderBy('name')->get();

        return view('expenses.index', compact(
            'expenses', 'stats',
            'categoryBreakdown', 'monthlyTrend',
            'categories'
        ));
    }

    // â”€â”€ Show create form â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function create() {
        $categories = ExpenseCategory::forBusiness(
            $this->businessId()
        )->orderBy('name')->get();

        return view('expenses.create', compact(
            'categories'
        ));
    }

    // â”€â”€ Store new expense â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function store(ExpenseRequest $request) {
        $data = $request->validated();
        $data['business_id'] = $this->businessId();
        $data['user_id']     = Auth::id();

        $created = Expense::create($data);
        \App\Models\AuditLog::record('expense.created', $created, ['amount' => (float) $created->amount, 'title' => $created->title]);

        WebhookService::dispatch('expense.created', $created->business_id, [
            'expense_id' => $created->id,
            'amount'     => (float) $created->amount,
            'title'      => $created->title,
        ]);

        return redirect()
            ->route('expenses.index')
            ->with('success',
                'Expense recorded successfully.');
    }

    // â”€â”€ Show edit form â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function edit(Expense $expense) {
        $this->authorizeExpense($expense);

        $categories = ExpenseCategory::forBusiness(
            $this->businessId()
        )->orderBy('name')->get();

        return view('expenses.edit', compact(
            'expense', 'categories'
        ));
    }

    // â”€â”€ Update expense â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function update(
        ExpenseRequest $request,
        Expense $expense
    ) {
        $this->authorizeExpense($expense);

        $expense->update($request->validated());

        return redirect()
            ->route('expenses.index')
            ->with('success',
                'Expense updated successfully.');
    }

    // â”€â”€ Delete expense â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function destroy(Expense $expense) {
        $this->authorizeExpense($expense);

        \App\Models\AuditLog::record('expense.deleted', $expense, ['amount' => (float) $expense->amount, 'title' => $expense->title]);

        $expense->delete();

        return redirect()
            ->route('expenses.index')
            ->with('success',
                'Expense deleted successfully.');
    }

    // â”€â”€ Export expenses as CSV â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function export(Request $request) {
        $businessId = $this->businessId();
        $query = Expense::with(['category', 'user'])->forBusiness($businessId);

        if ($request->filled('date_from')) {
            $query->whereDate('expense_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('expense_date', '<=', $request->date_to);
        }
        if ($request->filled('category_id')) {
            $query->where('expense_category_id', $request->category_id);
        }

        $expenses = $query->orderBy('expense_date', 'desc')->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="expenses_' . now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($expenses) {
            $handle = fopen('php://output', 'w');
            \App\Support\Csv::put($handle, ['Date', 'Title', 'Category', 'Amount', 'Payment Method', 'Reference', 'Recorded By', 'Notes']);
            foreach ($expenses as $e) {
                \App\Support\Csv::put($handle, [
                    $e->expense_date->format('Y-m-d'),
                    $e->title,
                    $e->category->name ?? 'Uncategorized',
                    $e->amount,
                    $e->payment_method,
                    $e->reference ?? '',
                    $e->user->name ?? '',
                    $e->description ?? '',
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    // â”€â”€ Category management â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function storeCategory(Request $request) {
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                \Illuminate\Validation\Rule::unique(
                    'expense_categories', 'name'
                )->where(
                    'business_id',
                    $this->businessId()
                ),
            ],
            'description' => 'nullable|string|max:255',
        ]);

        ExpenseCategory::create([
            'business_id' => $this->businessId(),
            'name'        => $request->name,
            'description' => $request->description,
        ]);

        return redirect()
            ->route('expenses.index')
            ->with('success',
                'Category added successfully.');
    }

    public function destroyCategory(
        ExpenseCategory $expenseCategory
    ) {
        if ($expenseCategory->business_id
            !== $this->businessId()) {
            abort(403);
        }

        // Unlink expenses before deleting
        Expense::where(
            'expense_category_id',
            $expenseCategory->id
        )->update(['expense_category_id' => null]);

        $expenseCategory->delete();

        return redirect()
            ->route('expenses.index')
            ->with('success',
                'Category deleted successfully.');
    }

    // â”€â”€ Authorization helper â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    private function authorizeExpense(
        Expense $expense
    ): void {
        if ($expense->business_id
            !== $this->businessId()) {
            abort(403, 'Unauthorized action.');
        }
    }
}
