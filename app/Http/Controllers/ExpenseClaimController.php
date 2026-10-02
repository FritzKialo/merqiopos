<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseClaim;
use App\Models\ExpenseClaimItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpenseClaimController extends Controller
{
    private function businessId(): int
    {
        return Auth::user()->currentBusiness()->id;
    }

    public function index()
    {
        $user    = Auth::user();
        $business = $user->currentBusiness();
        $isManager = $user->hasAnyRole('owner', 'manager');

        $query = ExpenseClaim::forBusiness($this->businessId())
            ->with(['user', 'approver'])
            ->latest();

        if (!$isManager) {
            $query->where('user_id', $user->id);
        }

        $claims = $query->paginate(20);

        return view('expense-claims.index', compact('claims'));
    }

    public function create()
    {
        return view('expense-claims.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'                     => 'required|string|max:200',
            'items'                     => 'required|array|min:1',
            'items.*.description'       => 'required|string|max:300',
            'items.*.expense_date'      => 'required|date',
            'items.*.category'          => 'nullable|string|max:100',
            'items.*.amount'            => 'required|numeric|min:0.01',
        ]);

        $businessId = $this->businessId();
        $total = collect($request->items)->sum('amount');

        $claim = ExpenseClaim::create([
            'business_id'  => $businessId,
            'user_id'      => Auth::id(),
            'reference'    => ExpenseClaim::generateReference($businessId),
            'title'        => $request->title,
            'total_amount' => $total,
            'status'       => 'submitted',
            'submitted_at' => now()->toDateString(),
        ]);

        foreach ($request->items as $item) {
            $claim->items()->create([
                'description'  => $item['description'],
                'expense_date' => $item['expense_date'],
                'category'     => $item['category'] ?? null,
                'amount'       => $item['amount'],
            ]);
        }

        \App\Models\AppNotification::notifyApprovers(
            Auth::user()->currentBusiness(), Auth::id(), 'claim_submitted', 'New Expense Claim',
            Auth::user()->name . ' submitted an expense claim for review.',
            route('expense-claims.show', $claim), 'dollar-sign'
        );

        return redirect()->route('expense-claims.show', $claim)->with('success', 'Expense claim submitted.');
    }

    public function show(ExpenseClaim $expenseClaim)
    {
        $this->authorizeAccess($expenseClaim);
        $expenseClaim->load(['items', 'user', 'approver']);
        return view('expense-claims.show', compact('expenseClaim'));
    }

    public function edit(ExpenseClaim $expenseClaim)
    {
        $this->authorizeAccess($expenseClaim);
        if ($expenseClaim->status !== 'draft') {
            return back()->with('error', 'Only draft claims can be edited.');
        }
        $expenseClaim->load('items');
        return view('expense-claims.edit', compact('expenseClaim'));
    }

    public function update(Request $request, ExpenseClaim $expenseClaim)
    {
        $this->authorizeAccess($expenseClaim);
        if ($expenseClaim->status !== 'draft') {
            return back()->with('error', 'Only draft claims can be edited.');
        }
        $request->validate([
            'title' => 'required|string|max:200',
            'items' => 'required|array|min:1',
            'items.*.description'  => 'required|string|max:300',
            'items.*.expense_date' => 'required|date',
            'items.*.amount'       => 'required|numeric|min:0.01',
        ]);

        $total = collect($request->items)->sum('amount');
        $expenseClaim->update(['title' => $request->title, 'total_amount' => $total]);
        $expenseClaim->items()->delete();

        foreach ($request->items as $item) {
            $expenseClaim->items()->create([
                'description'  => $item['description'],
                'expense_date' => $item['expense_date'],
                'category'     => $item['category'] ?? null,
                'amount'       => $item['amount'],
            ]);
        }

        return redirect()->route('expense-claims.show', $expenseClaim)->with('success', 'Claim updated.');
    }

    public function destroy(ExpenseClaim $expenseClaim)
    {
        $this->authorizeAccess($expenseClaim);
        if ($expenseClaim->status !== 'draft') {
            return back()->with('error', 'Only draft claims can be deleted.');
        }
        $expenseClaim->delete();
        return redirect()->route('expense-claims.index')->with('success', 'Claim deleted.');
    }


    // Nobody but the owner (who has no one above them) may act on their own
    // claim — a manager could otherwise submit, approve and pay their own.
    private function blockSelfAction(ExpenseClaim $claim): ?\Illuminate\Http\RedirectResponse
    {
        if ($claim->user_id === Auth::id() && !Auth::user()->hasRole('owner')) {
            return back()->with('error', 'You cannot approve, reject or pay your own expense claim — ask another manager or the owner.');
        }
        return null;
    }

    public function approve(ExpenseClaim $claim)
    {
        $this->authorizeAccess($claim);
        $this->authorizeManager();
        if ($blocked = $this->blockSelfAction($claim)) return $blocked;
        $claim->update(['status' => 'approved', 'approved_by' => Auth::id()]);
        \App\Models\AppNotification::notifyUser($claim->business_id, $claim->user_id, 'claim_approved', 'Expense Claim Approved',
            'Your expense claim was approved by ' . Auth::user()->name . '.', route('expense-claims.show', $claim), 'check-circle');
        return back()->with('success', 'Expense claim approved.');
    }

    public function reject(Request $request, ExpenseClaim $claim)
    {
        $this->authorizeAccess($claim);
        $this->authorizeManager();
        if ($blocked = $this->blockSelfAction($claim)) return $blocked;
        $claim->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->rejection_reason,
        ]);
        \App\Models\AppNotification::notifyUser($claim->business_id, $claim->user_id, 'claim_rejected', 'Expense Claim Rejected',
            'Your expense claim was rejected.' . ($request->rejection_reason ? ' Reason: ' . $request->rejection_reason : ''),
            route('expense-claims.show', $claim), 'x-circle');
        return back()->with('success', 'Expense claim rejected.');
    }

    public function pay(ExpenseClaim $claim)
    {
        $this->authorizeAccess($claim);
        $this->authorizeManager();
        if ($blocked = $this->blockSelfAction($claim)) return $blocked;
        if ($claim->status !== 'approved') {
            return back()->with('error', 'Only approved claims can be paid.');
        }

        $claim->update(['status' => 'paid', 'paid_at' => now()->toDateString()]);

        // Create expense record for accounting if Expense model exists
        try {
            if (class_exists(Expense::class)) {
                Expense::create([
                    'business_id'    => $claim->business_id,
                    'user_id'        => Auth::id(),
                    'title'          => 'Expense Claim: ' . $claim->title,
                    'description'    => $claim->reference,
                    'amount'         => $claim->total_amount,
                    'expense_date'   => now()->toDateString(),
                    'payment_method' => 'cash',
                    'reference'      => $claim->reference,
                ]);
            }
        } catch (\Throwable $e) {
            // Expense creation is best-effort
        }

        return back()->with('success', 'Expense claim marked as paid.');
    }

    private function authorizeAccess(ExpenseClaim $claim): void
    {
        if ($claim->business_id !== $this->businessId()) abort(403);
    }

    // approve/reject/pay were only checking business scoping, not role —
    // any staff member (e.g. a cashier) could approve, reject, or pay out
    // ANY claim in the business, including one they submitted themselves,
    // completely bypassing the manager-approval workflow. Same role gate
    // index() already uses to decide "see everyone's claims vs just mine".
    private function authorizeManager(): void
    {
        abort_unless(Auth::user()->hasAnyRole('owner', 'manager', 'overall_manager'), 403);
    }
}
