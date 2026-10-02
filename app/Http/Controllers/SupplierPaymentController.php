<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SupplierPaymentController extends Controller
{
    private function businessId(): int
    {
        return Auth::user()->currentBusiness()->id;
    }

    public function index(Supplier $supplier)
    {
        $businessId = $this->businessId();
        abort_if($supplier->business_id !== $businessId, 403);

        $entries = SupplierPayment::where('supplier_id', $supplier->id)
            ->with(['user', 'purchaseOrder'])
            ->orderBy('payment_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return view('suppliers.payments', compact('supplier', 'entries'));
    }

    public function store(Supplier $supplier, Request $request)
    {
        $businessId = $this->businessId();
        abort_if($supplier->business_id !== $businessId, 403);

        $request->validate([
            'amount'         => 'required|numeric|min:0.01|max:' . $supplier->payable_balance,
            'payment_method' => 'required|in:cash,bank_transfer,mpesa,cheque',
            'payment_date'   => 'required|date',
            'reference'      => 'nullable|string|max:100',
            'notes'          => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($supplier, $request, $businessId) {
            $newBalance = max(0, (float) $supplier->payable_balance - (float) $request->amount);

            SupplierPayment::create([
                'business_id'    => $businessId,
                'supplier_id'    => $supplier->id,
                'user_id'        => Auth::id(),
                'type'           => 'payment',
                'amount'         => $request->amount,
                'balance_after'  => $newBalance,
                'payment_method' => $request->payment_method,
                'reference'      => $request->reference,
                'notes'          => $request->notes,
                'payment_date'   => $request->payment_date,
            ]);

            $supplier->update(['payable_balance' => $newBalance]);
        });

        return back()->with('success', 'Payment recorded successfully.');
    }

    public function bill(Supplier $supplier, Request $request)
    {
        $businessId = $this->businessId();
        abort_if($supplier->business_id !== $businessId, 403);

        $request->validate([
            'amount'       => 'required|numeric|min:0.01',
            'due_date'     => 'nullable|date',
            'reference'    => 'nullable|string|max:100',
            'notes'        => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($supplier, $request, $businessId) {
            $newBalance = (float) $supplier->payable_balance + (float) $request->amount;

            SupplierPayment::create([
                'business_id'   => $businessId,
                'supplier_id'   => $supplier->id,
                'user_id'       => Auth::id(),
                'type'          => 'bill',
                'amount'        => $request->amount,
                'balance_after' => $newBalance,
                'reference'     => $request->reference,
                'notes'         => $request->notes,
                'payment_date'  => now()->toDateString(),
                'due_date'      => $request->due_date,
            ]);

            $supplier->update(['payable_balance' => $newBalance]);
        });

        return back()->with('success', 'Bill added to supplier account.');
    }
}
