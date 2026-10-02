<?php

namespace App\Http\Controllers;

use App\Models\ProformaInvoice;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProformaInvoiceController extends Controller
{
    private function businessId(): int
    {
        return Auth::user()->currentBusiness()->id;
    }

    public function index(Request $request)
    {
        $query = ProformaInvoice::where('business_id', $this->businessId())
            ->with('customer')
            ->latest();
        if ($request->status) {
            $query->where('status', $request->status);
        }
        $proformas = $query->paginate(20);
        return view('proforma-invoices.index', compact('proformas'));
    }

    public function create()
    {
        $customers = Customer::where('business_id', $this->businessId())->orderBy('name')->get();
        return view('proforma-invoices.create', compact('customers'));
    }

    public function store(Request $request)
    {
        $businessId = $this->businessId();

        // Unscoped exists: previously — could link this proforma to another
        // business's customer.
        $request->validate([
            'customer_id'              => ['nullable', \Illuminate\Validation\Rule::exists('customers', 'id')->where('business_id', $businessId)],
            'issue_date'               => 'required|date',
            'valid_until'              => 'nullable|date',
            'notes'                    => 'nullable|string',
            'items'                    => 'required|array|min:1',
            'items.*.description'      => 'required|string',
            'items.*.quantity'         => 'required|numeric|min:0.01',
            'items.*.unit_price'       => 'required|numeric|min:0',
            'items.*.tax_rate'         => 'nullable|numeric|min:0|max:100',
        ]);
        $count      = ProformaInvoice::where('business_id', $businessId)->count();
        $number     = 'PRO-' . date('Y') . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

        $subtotal  = 0;
        $taxAmount = 0;
        foreach ($request->items as $item) {
            $lineTotal  = $item['quantity'] * $item['unit_price'];
            $subtotal  += $lineTotal;
            $taxAmount += $lineTotal * (($item['tax_rate'] ?? 0) / 100);
        }

        $proforma = ProformaInvoice::create([
            'business_id'   => $businessId,
            'customer_id'   => $request->customer_id,
            'proforma_number' => $number,
            'issue_date'    => $request->issue_date,
            'valid_until'   => $request->valid_until,
            'notes'         => $request->notes,
            'subtotal'      => $subtotal,
            'tax_amount'    => $taxAmount,
            'total_amount'  => $subtotal + $taxAmount,
            'currency'      => 'KES',
            'status'        => 'draft',
        ]);

        foreach ($request->items as $item) {
            $lineTotal = $item['quantity'] * $item['unit_price'];
            $proforma->items()->create([
                'description' => $item['description'],
                'quantity'    => $item['quantity'],
                'unit_price'  => $item['unit_price'],
                'tax_rate'    => $item['tax_rate'] ?? 0,
                'total'       => $lineTotal + $lineTotal * (($item['tax_rate'] ?? 0) / 100),
            ]);
        }

        return redirect()->route('proforma-invoices.show', $proforma)
            ->with('success', 'Proforma invoice created.');
    }

    public function show(ProformaInvoice $proformaInvoice)
    {
        abort_if($proformaInvoice->business_id !== $this->businessId(), 403);
        $proformaInvoice->load('customer', 'items');
        $business = Auth::user()->currentBusiness();
        return view('proforma-invoices.show', compact('proformaInvoice', 'business'));
    }

    public function edit(ProformaInvoice $proformaInvoice)
    {
        abort_if($proformaInvoice->business_id !== $this->businessId(), 403);
        $customers = Customer::where('business_id', $this->businessId())->orderBy('name')->get();
        $proformaInvoice->load('items');
        return view('proforma-invoices.edit', compact('proformaInvoice', 'customers'));
    }

    public function update(Request $request, ProformaInvoice $proformaInvoice)
    {
        abort_if($proformaInvoice->business_id !== $this->businessId(), 403);
        $businessId = $this->businessId();

        // Same unscoped-exists gap store() already guards against: without
        // this, a request could reassign the proforma to another business's
        // customer, leaking that customer's name/phone/email into this
        // business's show/edit/pdf views.
        $request->validate([
            'customer_id' => ['nullable', \Illuminate\Validation\Rule::exists('customers', 'id')->where('business_id', $businessId)],
            'issue_date' => 'required|date',
            'items'      => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity'    => 'required|numeric|min:0.01',
            'items.*.unit_price'  => 'required|numeric|min:0',
            'items.*.tax_rate'    => 'nullable|numeric|min:0|max:100',
        ]);

        $proformaInvoice->items()->delete();

        $subtotal  = 0;
        $taxAmount = 0;
        foreach ($request->items as $item) {
            $lineTotal  = $item['quantity'] * $item['unit_price'];
            $subtotal  += $lineTotal;
            $taxAmount += $lineTotal * (($item['tax_rate'] ?? 0) / 100);
            $proformaInvoice->items()->create([
                'description' => $item['description'],
                'quantity'    => $item['quantity'],
                'unit_price'  => $item['unit_price'],
                'tax_rate'    => $item['tax_rate'] ?? 0,
                'total'       => $lineTotal + $lineTotal * (($item['tax_rate'] ?? 0) / 100),
            ]);
        }

        $proformaInvoice->update([
            'customer_id'  => $request->customer_id,
            'issue_date'   => $request->issue_date,
            'valid_until'  => $request->valid_until,
            'notes'        => $request->notes,
            'subtotal'     => $subtotal,
            'tax_amount'   => $taxAmount,
            'total_amount' => $subtotal + $taxAmount,
        ]);

        return redirect()->route('proforma-invoices.show', $proformaInvoice)
            ->with('success', 'Proforma invoice updated.');
    }

    public function destroy(ProformaInvoice $proformaInvoice)
    {
        abort_if($proformaInvoice->business_id !== $this->businessId(), 403);
        $proformaInvoice->delete();
        return redirect()->route('proforma-invoices.index')->with('success', 'Deleted.');
    }

    public function send(ProformaInvoice $proformaInvoice)
    {
        abort_if($proformaInvoice->business_id !== $this->businessId(), 403);
        $proformaInvoice->update(['status' => 'sent']);
        return back()->with('success', 'Marked as sent.');
    }

    public function accept(ProformaInvoice $proformaInvoice)
    {
        abort_if($proformaInvoice->business_id !== $this->businessId(), 403);
        $proformaInvoice->update(['status' => 'accepted']);
        return back()->with('success', 'Proforma accepted.');
    }

    public function reject(ProformaInvoice $proformaInvoice)
    {
        abort_if($proformaInvoice->business_id !== $this->businessId(), 403);
        $proformaInvoice->update(['status' => 'rejected']);
        return back()->with('success', 'Proforma rejected.');
    }

    // Was marking the proforma "converted" and redirecting to a blank
    // "New Invoice" form with only a text hint in the flash message — none
    // of the proforma's customer, items, or totals actually carried over,
    // and it was already locked as converted even if the user then
    // abandoned that blank form without ever creating an invoice.
    public function convert(ProformaInvoice $proformaInvoice)
    {
        abort_if($proformaInvoice->business_id !== $this->businessId(), 403);
        $proformaInvoice->load('items');

        $invoice = DB::transaction(function () use ($proformaInvoice) {
            $issueDate = now()->toDateString();
            $dueDate   = $proformaInvoice->valid_until
                && $proformaInvoice->valid_until->toDateString() >= $issueDate
                ? $proformaInvoice->valid_until->toDateString()
                : now()->addDays(30)->toDateString();

            $invoice = Invoice::create([
                'business_id'    => $proformaInvoice->business_id,
                'user_id'        => Auth::id(),
                'customer_id'    => $proformaInvoice->customer_id,
                'invoice_number' => Invoice::nextNumber($proformaInvoice->business_id),
                'issue_date'     => $issueDate,
                'due_date'       => $dueDate,
                'subtotal'       => $proformaInvoice->subtotal,
                'vat_amount'     => $proformaInvoice->tax_amount,
                'discount_amount'=> 0,
                'total'          => $proformaInvoice->total_amount,
                'amount_paid'    => 0,
                'balance_due'    => $proformaInvoice->total_amount,
                'status'         => 'draft',
                'notes'          => $proformaInvoice->notes,
            ]);

            foreach ($proformaInvoice->items as $item) {
                $lineSub = $item->quantity * $item->unit_price;
                InvoiceItem::create([
                    'invoice_id'  => $invoice->id,
                    'description' => $item->description,
                    'quantity'    => $item->quantity,
                    'unit_price'  => $item->unit_price,
                    'vat_rate'    => $item->tax_rate,
                    'vat_amount'  => round($lineSub * $item->tax_rate / 100, 2),
                    'subtotal'    => $lineSub,
                ]);
            }

            $proformaInvoice->update([
                'status'               => 'converted',
                'converted_invoice_id' => $invoice->id,
            ]);

            return $invoice;
        });

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Invoice ' . $invoice->invoice_number . ' created from proforma ' . $proformaInvoice->proforma_number . '.');
    }
}
