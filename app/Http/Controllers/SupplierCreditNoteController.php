<?php

namespace App\Http\Controllers;

use App\Models\SupplierCreditNote;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupplierCreditNoteController extends Controller
{
    private function businessId(): int
    {
        return Auth::user()->currentBusiness()->id;
    }

    public function index()
    {
        $scns = SupplierCreditNote::where('business_id', $this->businessId())
            ->with('supplier')
            ->latest()
            ->paginate(20);
        return view('supplier-credit-notes.index', compact('scns'));
    }

    public function create()
    {
        $suppliers = Supplier::where('business_id', $this->businessId())->orderBy('name')->get();
        return view('supplier-credit-notes.create', compact('suppliers'));
    }

    public function store(Request $request)
    {
        $businessId = $this->businessId();

        $request->validate([
            // Unscoped exists: previously — could link this credit note to
            // another business's supplier record (real data leak once
            // viewed via ->supplier on show/edit).
            'supplier_id' => ['nullable', \Illuminate\Validation\Rule::exists('suppliers', 'id')->where('business_id', $businessId)],
            'issue_date'  => 'required|date',
            'reason'      => 'required|in:return,overcharge,damaged,other',
            'notes'       => 'nullable|string',
            'items'       => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity'    => 'required|numeric|min:0.01',
            'items.*.unit_price'  => 'required|numeric|min:0',
        ]);
        $count      = SupplierCreditNote::where('business_id', $businessId)->count();
        $number     = 'SCN-' . date('Y') . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

        $total = 0;
        foreach ($request->items as $item) {
            $total += $item['quantity'] * $item['unit_price'];
        }

        $scn = SupplierCreditNote::create([
            'business_id'    => $businessId,
            'supplier_id'    => $request->supplier_id,
            'credit_number'  => $number,
            'issue_date'     => $request->issue_date,
            'reason'         => $request->reason,
            'status'         => 'pending',
            'amount'         => $total,
            'applied_amount' => 0,
            'notes'          => $request->notes,
        ]);

        foreach ($request->items as $item) {
            $scn->items()->create([
                'description' => $item['description'],
                'quantity'    => $item['quantity'],
                'unit_price'  => $item['unit_price'],
                'total'       => $item['quantity'] * $item['unit_price'],
            ]);
        }

        return redirect()->route('supplier-credit-notes.show', $scn)
            ->with('success', 'Supplier credit note created.');
    }

    public function show(SupplierCreditNote $supplierCreditNote)
    {
        abort_if($supplierCreditNote->business_id !== $this->businessId(), 403);
        $supplierCreditNote->load('supplier', 'items');
        return view('supplier-credit-notes.show', compact('supplierCreditNote'));
    }

    public function edit(SupplierCreditNote $supplierCreditNote)
    {
        abort_if($supplierCreditNote->business_id !== $this->businessId(), 403);
        $suppliers = Supplier::where('business_id', $this->businessId())->orderBy('name')->get();
        $supplierCreditNote->load('items');
        return view('supplier-credit-notes.edit', compact('supplierCreditNote', 'suppliers'));
    }

    public function update(Request $request, SupplierCreditNote $supplierCreditNote)
    {
        abort_if($supplierCreditNote->business_id !== $this->businessId(), 403);
        // supplier_id was previously written below with zero validation at
        // all (not even an unscoped `exists`) — any business_id's supplier
        // could be attached to this credit note. Scoped the same as store().
        $request->validate([
            'supplier_id' => ['nullable', \Illuminate\Validation\Rule::exists('suppliers', 'id')->where('business_id', $this->businessId())],
            'issue_date'  => 'required|date',
            'reason'      => 'required|in:return,overcharge,damaged,other',
            'items'       => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity'    => 'required|numeric|min:0.01',
            'items.*.unit_price'  => 'required|numeric|min:0',
        ]);

        $supplierCreditNote->items()->delete();
        $total = 0;
        foreach ($request->items as $item) {
            $lineTotal = $item['quantity'] * $item['unit_price'];
            $total += $lineTotal;
            $supplierCreditNote->items()->create([
                'description' => $item['description'],
                'quantity'    => $item['quantity'],
                'unit_price'  => $item['unit_price'],
                'total'       => $lineTotal,
            ]);
        }

        $supplierCreditNote->update([
            'supplier_id' => $request->supplier_id,
            'issue_date'  => $request->issue_date,
            'reason'      => $request->reason,
            'notes'       => $request->notes,
            'amount'      => $total,
        ]);

        return redirect()->route('supplier-credit-notes.show', $supplierCreditNote)
            ->with('success', 'Updated.');
    }

    public function apply(SupplierCreditNote $supplierCreditNote)
    {
        abort_if($supplierCreditNote->business_id !== $this->businessId(), 403);

        if ($supplierCreditNote->status !== 'pending') {
            return back()->with('error', 'Only pending credit notes can be applied.');
        }

        // Applying used to only flip the status — the supplier's payable
        // balance never went down, so the credit had no financial effect.
        // Deliberately not written as a SupplierPayment ledger row: that
        // table's 'payment' type is what the reports count as cash paid out.
        \Illuminate\Support\Facades\DB::transaction(function () use ($supplierCreditNote) {
            $supplierCreditNote->update([
                'status'         => 'applied',
                'applied_amount' => $supplierCreditNote->amount,
            ]);

            if ($supplierCreditNote->supplier_id) {
                $supplier = \App\Models\Supplier::where('business_id', $supplierCreditNote->business_id)
                    ->find($supplierCreditNote->supplier_id);
                if ($supplier) {
                    $supplier->update([
                        'payable_balance' => max(0, (float) $supplier->payable_balance - (float) $supplierCreditNote->amount),
                    ]);
                }
            }
        });

        return back()->with('success', 'Credit note applied.');
    }

    public function destroy(SupplierCreditNote $supplierCreditNote)
    {
        abort_if($supplierCreditNote->business_id !== $this->businessId(), 403);
        if ($supplierCreditNote->status !== 'pending') {
            return back()->with('error', 'Only pending credit notes can be deleted.');
        }
        $supplierCreditNote->delete();
        return redirect()->route('supplier-credit-notes.index')->with('success', 'Deleted.');
    }
}
