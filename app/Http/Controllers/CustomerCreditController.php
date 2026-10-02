<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerCredit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CustomerCreditController extends Controller
{
    private function business()
    {
        return Auth::user()->currentBusiness();
    }

    private function authorizeCustomer(Customer $customer): void
    {
        if ($customer->business_id !== $this->business()->id) {
            abort(403);
        }
    }

    // ── Index / Ledger ────────────────────────────────────────────────────────

    public function index(Customer $customer)
    {
        $this->authorizeCustomer($customer);

        $credits = CustomerCredit::where('customer_id', $customer->id)
            ->where('business_id', $this->business()->id)
            ->with('user')
            ->latest()
            ->paginate(30);

        return view('customers.credits', compact('customer', 'credits'));
    }

    // ── Store Repayment ───────────────────────────────────────────────────────

    public function store(Request $request, Customer $customer)
    {
        $this->authorizeCustomer($customer);

        $request->validate([
            'amount'    => 'required|numeric|min:0.01|max:' . $customer->credit_balance,
            'reference' => 'nullable|string|max:100',
            'notes'     => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($request, $customer) {
            $business = $this->business();
            $amount   = (float) $request->amount;

            $customer->decrement('credit_balance', $amount);
            $customer->refresh();

            CustomerCredit::create([
                'business_id'  => $business->id,
                'customer_id'  => $customer->id,
                'user_id'      => Auth::id(),
                'type'         => 'repayment',
                'amount'       => $amount,
                'balance_after'=> $customer->credit_balance,
                'reference'    => $request->reference,
                'notes'        => $request->notes,
            ]);
        });

        return back()->with('success', 'KSh ' . number_format($request->amount, 0) . ' of store credit deducted.');
    }

    // ── Adjust Credit Limit ───────────────────────────────────────────────────

    public function adjustLimit(Request $request, Customer $customer)
    {
        $this->authorizeCustomer($customer);

        $request->validate([
            'credit_limit' => 'required|numeric|min:0',
        ]);

        $customer->update(['credit_limit' => $request->credit_limit]);

        return back()->with('success', 'Credit limit updated to KSh ' . number_format($request->credit_limit, 0) . '.');
    }
}
