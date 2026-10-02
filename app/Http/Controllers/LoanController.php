<?php

namespace App\Http\Controllers;

use App\Models\BusinessLoan;
use App\Models\LoanRepayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LoanController extends Controller {

    private function businessId(): int {
        return Auth::user()->currentBusiness()->id;
    }

    public function index() {
        $businessId = $this->businessId();

        $activeLoans = BusinessLoan::forBusiness($businessId)
            ->where('status', 'active')
            ->orderByDesc('disbursement_date')
            ->get();

        $paidLoans = BusinessLoan::forBusiness($businessId)
            ->where('status', 'fully_paid')
            ->orderByDesc('disbursement_date')
            ->get();

        $totalLiability = $activeLoans->sum('outstanding_balance');

        // The index view renders a single combined collection
        $loans = $activeLoans->concat($paidLoans);

        return view('loans.index', compact('loans', 'activeLoans', 'paidLoans', 'totalLiability'));
    }

    public function create() {
        return view('loans.create');
    }

    public function store(Request $request) {
        $businessId = $this->businessId();

        $validated = $request->validate([
            'lender_name'          => 'required|string|max:255',
            'loan_type'            => 'required|in:bank,sacco,mobile,personal,other',
            'principal_amount'     => 'required|numeric|min:1',
            'interest_rate'        => 'required|numeric|min:0|max:200',
            'disbursement_date'    => 'required|date',
            'repayment_start_date' => 'required|date',
            'term_months'          => 'required|integer|min:1',
            'notes'                => 'nullable|string',
        ]);

        $installment = BusinessLoan::computeInstallment(
            $validated['principal_amount'],
            $validated['interest_rate'],
            $validated['term_months']
        );

        DB::transaction(function () use ($validated, $businessId, $installment) {
            BusinessLoan::create([
                ...$validated,
                'business_id'         => $businessId,
                'user_id'             => Auth::id(),
                'monthly_installment' => round($installment, 2),
                'outstanding_balance' => $validated['principal_amount'],
            ]);
        });

        return redirect()->route('loans.index')->with('success', 'Loan recorded.');
    }

    public function show(BusinessLoan $loan) {
        if ($loan->business_id !== $this->businessId()) abort(403);

        $repayments = $loan->repayments()->orderByDesc('payment_date')->get();

        // Generate amortization schedule (first 12 months)
        $schedule = [];
        $balance  = (float) $loan->principal_amount;
        $monthlyRate = ($loan->interest_rate / 100) / 12;
        $installment = (float) $loan->monthly_installment;
        $startDate = $loan->repayment_start_date->copy();

        for ($i = 1; $i <= min(12, $loan->term_months); $i++) {
            $interestPortion  = $balance * $monthlyRate;
            $principalPortion = $installment - $interestPortion;
            $balance          = max(0, $balance - $principalPortion);

            $schedule[] = [
                'month'     => $startDate->copy()->addMonths($i - 1)->format('M Y'),
                'payment'   => $installment,
                'interest'  => $interestPortion,
                'principal' => $principalPortion,
                'balance'   => $balance,
            ];
        }

        return view('loans.show', compact('loan', 'repayments', 'schedule'));
    }

    public function recordPayment(Request $request, BusinessLoan $loan) {
        if ($loan->business_id !== $this->businessId()) abort(403);

        $validated = $request->validate([
            'amount'         => 'required|numeric|min:1|max:' . $loan->outstanding_balance,
            'payment_date'   => 'required|date',
            'payment_method' => 'required|in:cash,bank_transfer,mpesa,cheque',
            'reference'      => 'nullable|string|max:100',
            'notes'          => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, $loan) {
            $monthlyRate     = ($loan->interest_rate / 100) / 12;
            $outstanding     = (float) $loan->outstanding_balance;
            $amount          = (float) $validated['amount'];
            $interestPortion = $outstanding * $monthlyRate;
            $principalPortion = max(0, $amount - $interestPortion);
            $balanceAfter    = max(0, $outstanding - $principalPortion);

            LoanRepayment::create([
                'business_loan_id'  => $loan->id,
                'business_id'       => $loan->business_id,
                'user_id'           => Auth::id(),
                'amount'            => $amount,
                'principal_portion' => $principalPortion,
                'interest_portion'  => min($interestPortion, $amount),
                'payment_date'      => $validated['payment_date'],
                'payment_method'    => $validated['payment_method'],
                'reference'         => $validated['reference'] ?? null,
                'balance_after'     => $balanceAfter,
                'notes'             => $validated['notes'] ?? null,
            ]);

            $loan->update([
                'outstanding_balance' => $balanceAfter,
                'status'              => $balanceAfter <= 0 ? 'fully_paid' : 'active',
            ]);
        });

        return redirect()->route('loans.show', $loan)->with('success', 'Payment recorded.');
    }

    public function destroy(BusinessLoan $loan) {
        if ($loan->business_id !== $this->businessId()) abort(403);
        $loan->delete();
        return redirect()->route('loans.index')->with('success', 'Loan deleted.');
    }
}
