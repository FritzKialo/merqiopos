<?php

namespace App\Http\Controllers;

use App\Models\CustomerDeposit;
use App\Models\DepositUsage;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerDepositController extends Controller
{
    private function businessId(): int
    {
        return Auth::user()->currentBusiness()->id;
    }

    public function index(Request $request)
    {
        $businessId = $this->businessId();
        $customers  = Customer::where('business_id', $businessId)->orderBy('name')->get();

        $query = CustomerDeposit::where('business_id', $businessId)
            ->with('customer')
            ->latest();

        if ($request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }

        $deposits = $query->paginate(20);
        return view('customer-deposits.index', compact('deposits', 'customers'));
    }

    public function create()
    {
        $customers = Customer::where('business_id', $this->businessId())->orderBy('name')->get();
        return view('customer-deposits.create', compact('customers'));
    }

    public function store(Request $request)
    {
        // Unscoped exists: previously — could link this deposit to another
        // business's customer record.
        $request->validate([
            'customer_id'    => ['nullable', \Illuminate\Validation\Rule::exists('customers', 'id')->where('business_id', $this->businessId())],
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,mpesa,bank,card',
            'mpesa_code'     => 'nullable|string|max:50',
            'received_at'    => 'required|date',
            'notes'          => 'nullable|string',
        ]);

        $reference = 'DEP-' . date('Ymd') . '-' . rand(1000, 9999);

        CustomerDeposit::create([
            'business_id'    => $this->businessId(),
            'customer_id'    => $request->customer_id,
            'reference'      => $reference,
            'amount'         => $request->amount,
            'currency'       => 'KES',
            'payment_method' => $request->payment_method,
            'mpesa_code'     => $request->mpesa_code,
            'received_at'    => $request->received_at,
            'notes'          => $request->notes,
            'status'         => 'available',
            'used_amount'    => 0,
        ]);

        return redirect()->route('customer-deposits.index')->with('success', 'Deposit recorded.');
    }

    public function show(CustomerDeposit $customerDeposit)
    {
        abort_if($customerDeposit->business_id !== $this->businessId(), 403);
        $customerDeposit->load('customer', 'usages');
        return view('customer-deposits.show', compact('customerDeposit'));
    }

    public function apply(Request $request, CustomerDeposit $customerDeposit)
    {
        // Without this, any business could apply another business's
        // customer deposit toward an invoice — real money misappropriation.
        abort_if($customerDeposit->business_id !== $this->businessId(), 403);
        $available = $customerDeposit->availableBalance();

        $request->validate([
            'amount'     => "required|numeric|min:0.01|max:{$available}",
            // Unscoped exists: previously — an out-of-business invoice_id
            // would have reduced ANOTHER business's invoice balance below,
            // since nothing else in this method rechecks ownership.
            'invoice_id' => ['nullable', \Illuminate\Validation\Rule::exists('invoices', 'id')->where('business_id', $this->businessId())],
            'notes'      => 'nullable|string|max:255',
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $customerDeposit) {
            DepositUsage::create([
                'customer_deposit_id' => $customerDeposit->id,
                'invoice_id'          => $request->invoice_id,
                'sale_id'             => null,
                'amount_used'         => $request->amount,
                'used_at'             => now(),
                'notes'               => $request->notes,
            ]);

            $newUsed = $customerDeposit->used_amount + $request->amount;
            $status  = $newUsed >= $customerDeposit->amount ? 'fully_used' : 'partially_used';

            $customerDeposit->update([
                'used_amount' => $newUsed,
                'status'      => $status,
            ]);

            // Previously this method recorded the usage and drained the
            // deposit but never actually reduced the linked invoice's
            // balance — the invoice would keep showing the full amount
            // owed even though a deposit had supposedly covered part of
            // it. Mirrors the same reduction CreditNote::issue() already
            // does for its own invoice_id branch.
            if ($request->invoice_id) {
                $invoice = \App\Models\Invoice::find($request->invoice_id);
                if ($invoice) {
                    $newBalance = max(0, (float) $invoice->balance_due - (float) $request->amount);
                    $invoice->update([
                        'balance_due' => $newBalance,
                        'amount_paid' => (float) $invoice->amount_paid + (float) $request->amount,
                        'status'      => $newBalance <= 0 ? 'paid' : $invoice->status,
                    ]);
                }
            }
        });

        return back()->with('success', 'Deposit applied successfully.');
    }

    public function destroy(CustomerDeposit $customerDeposit) {
        if ($customerDeposit->business_id !== $this->businessId()) abort(403);
        $customerDeposit->delete();
        return redirect()->route('customer-deposits.index')->with('success', 'Deposit deleted.');
    }
}
