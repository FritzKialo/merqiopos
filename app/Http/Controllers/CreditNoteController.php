<?php

namespace App\Http\Controllers;

use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreditNoteController extends Controller
{
    private function business()
    {
        return Auth::user()->currentBusiness();
    }

    private function businessId(): int
    {
        return $this->business()->id;
    }

    private function authorize(CreditNote $cn): void
    {
        abort_if($cn->business_id !== $this->businessId(), 403);
    }

    public function index()
    {
        $notes = CreditNote::forBusiness($this->businessId())
            ->with(['customer', 'invoice'])
            ->latest()
            ->paginate(20);

        return view('credit-notes.index', compact('notes'));
    }

    public function create(Request $request)
    {
        $businessId = $this->businessId();
        $customers  = Customer::where('business_id', $businessId)->orderBy('name')->get();
        $products   = Product::forBusiness($businessId)->active()->orderBy('name')->get();
        $invoices   = Invoice::forBusiness($businessId)
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->orderBy('issue_date', 'desc')
            ->get();

        $invoice = null;
        if ($request->filled('invoice_id')) {
            $invoice = Invoice::where('id', $request->invoice_id)
                ->where('business_id', $businessId)
                ->with(['customer', 'items'])
                ->first();
        }

        $business = $this->business();

        return view('credit-notes.create', compact('customers', 'products', 'invoices', 'invoice', 'business'));
    }

    public function store(Request $request)
    {
        $businessIdForValidation = $this->businessId();

        // Both were unscoped exists: checks. CreditNote::issue() writes
        // straight to the linked Invoice's balance_due (or the linked
        // Customer's credit_balance) with no business check of its own —
        // an unscoped id here would let issuing this credit note corrupt
        // another business's invoice balance or customer credit.
        $request->validate([
            'customer_id'       => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('customers', 'id')->where('business_id', $businessIdForValidation)],
            'invoice_id'        => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('invoices', 'id')->where('business_id', $businessIdForValidation)],
            'reason'            => 'required|string|max:2000',
            'items'             => 'required|array|min:1',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity'    => 'required|numeric|min:0.01',
            'items.*.unit_price'  => 'required|numeric|min:0',
            'items.*.vat_rate'    => 'nullable|numeric|min:0|max:100',
        ]);

        $businessId = $this->businessId();

        DB::transaction(function () use ($request, $businessId) {
            $subtotal  = 0;
            $vatAmount = 0;
            $items     = [];

            foreach ($request->items as $line) {
                $qty      = (float) $line['quantity'];
                $price    = (float) $line['unit_price'];
                $vatRate  = (float) ($line['vat_rate'] ?? 0);
                $lineSub  = $qty * $price;
                $lineVat  = round($lineSub * $vatRate / 100, 2);
                $lineTotal= $lineSub + $lineVat;

                $subtotal  += $lineSub;
                $vatAmount += $lineVat;

                $items[] = [
                    'product_id'  => $line['product_id'] ?? null,
                    'description' => $line['description'],
                    'quantity'    => $qty,
                    'unit_price'  => $price,
                    'vat_rate'    => $vatRate,
                    'vat_amount'  => $lineVat,
                    'total'       => $lineTotal,
                ];
            }

            $total = $subtotal + $vatAmount;

            $cn = CreditNote::create([
                'business_id' => $businessId,
                'user_id'     => Auth::id(),
                'customer_id' => $request->customer_id,
                'invoice_id'  => $request->invoice_id,
                'number'      => CreditNote::nextNumber($businessId),
                'reason'      => $request->reason,
                'status'      => 'draft',
                'subtotal'    => $subtotal,
                'vat_amount'  => $vatAmount,
                'total'       => $total,
            ]);

            foreach ($items as $item) {
                $item['credit_note_id'] = $cn->id;
                CreditNoteItem::create($item);
            }

            $this->createdId = $cn->id;
        });

        return redirect()->route('credit-notes.show', $this->createdId)
            ->with('success', 'Credit note created.');
    }

    public function show(CreditNote $creditNote)
    {
        $this->authorize($creditNote);
        $creditNote->load(['customer', 'items.product', 'invoice', 'user', 'business']);

        return view('credit-notes.show', compact('creditNote'));
    }

    public function issue(CreditNote $creditNote)
    {
        $this->authorize($creditNote);

        if ($creditNote->status !== 'draft') {
            return back()->with('error', 'Only draft credit notes can be issued.');
        }

        $creditNote->issue();

        // Report the credit to KRA against the invoice it reduces.
        try {
            $creditNote->load('invoice');
            \App\Models\EtimsRefund::queue('credit_note', $creditNote->id, $this->business(), null, $creditNote->invoice, (float) $creditNote->total);
        } catch (\Throwable $e) {
            \Log::warning('eTIMS refund queue failed for credit note ' . $creditNote->id . ': ' . $e->getMessage());
        }

        return back()->with('success', 'Credit note issued successfully.');
    }

    public function pdf(CreditNote $creditNote)
    {
        $this->authorize($creditNote);
        $creditNote->load(['customer', 'items.product', 'invoice', 'business', 'user']);
        $business = $this->business();

        return view('credit-notes.pdf', compact('creditNote', 'business'));
    }
}
