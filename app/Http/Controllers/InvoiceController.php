<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Jobs\SubmitEtimsDocument;
use App\Services\WebhookService;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    private function business()
    {
        return Auth::user()->currentBusiness();
    }

    private function authorizeInvoice(Invoice $invoice): void
    {
        if ($invoice->business_id !== $this->business()->id) {
            abort(403);
        }
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $business = $this->business();
        $status   = $request->get('status', 'all');

        $query = Invoice::with(['customer'])
            ->forBusiness($business->id)
            ->latest('issue_date');

        if ($status !== 'all') {
            if ($status === 'overdue') {
                $query->where('status', '!=', 'paid')
                      ->where('status', '!=', 'cancelled')
                      ->where('due_date', '<', now());
            } else {
                $query->where('status', $status);
            }
        }

        $invoices = $query->paginate(20)->withQueryString();

        $counts = [
            'all'     => Invoice::forBusiness($business->id)->count(),
            'draft'   => Invoice::forBusiness($business->id)->where('status', 'draft')->count(),
            'sent'    => Invoice::forBusiness($business->id)->where('status', 'sent')->count(),
            'partial' => Invoice::forBusiness($business->id)->where('status', 'partial')->count(),
            'paid'    => Invoice::forBusiness($business->id)->where('status', 'paid')->count(),
            'overdue' => Invoice::forBusiness($business->id)->where('status', '!=', 'paid')->where('status', '!=', 'cancelled')->where('due_date', '<', now())->count(),
        ];

        return view('invoices.index', compact('invoices', 'status', 'counts'));
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function create()
    {
        $business  = $this->business();
        $customers = Customer::where('business_id', $business->id)->orderBy('name')->get();
        $products  = Product::forBusiness($business->id)->active()->orderBy('name')->get();

        return view('invoices.create', compact('customers', 'products', 'business'));
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $business = $this->business();

        $request->validate([
            // Scoped to this business — an unscoped exists: check would let
            // an invoice get linked to another business's customer record,
            // which the customer portal (and any per-customer invoice list)
            // trusts customer_id alone to select the right invoices.
            'customer_id'       => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('customers', 'id')->where('business_id', $business->id)],
            'issue_date'        => 'required|date',
            'due_date'          => 'required|date|after_or_equal:issue_date',
            'discount_amount'   => 'nullable|numeric|min:0',
            'notes'             => 'nullable|string|max:2000',
            'payment_terms'     => 'nullable|string|max:500',
            'items'             => 'required|array|min:1',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity'    => 'required|numeric|min:0.01',
            'items.*.unit_price'  => 'required|numeric|min:0',
            'items.*.vat_rate'    => 'nullable|numeric|min:0|max:100',
        ]);

        DB::transaction(function () use ($request, $business) {
            [$subtotal, $vatAmount, $items] = $this->computeItems($request->items);

            $discount = (float) ($request->discount_amount ?? 0);
            $total    = $subtotal + $vatAmount - $discount;

            $invoice = Invoice::create([
                'business_id'    => $business->id,
                'user_id'        => Auth::id(),
                'customer_id'    => $request->customer_id,
                'invoice_number' => Invoice::nextNumber($business->id),
                'issue_date'     => $request->issue_date,
                'due_date'       => $request->due_date,
                'subtotal'       => $subtotal,
                'vat_amount'     => $vatAmount,
                'discount_amount'=> $discount,
                'total'          => $total,
                'amount_paid'    => 0,
                'balance_due'    => $total,
                'status'         => 'draft',
                'notes'          => $request->notes,
                'payment_terms'  => $request->payment_terms,
            ]);

            foreach ($items as $item) {
                $item['invoice_id'] = $invoice->id;
                InvoiceItem::create($item);
            }
        });

        return redirect()->route('invoices.index')->with('success', 'Invoice created.');
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(Invoice $invoice)
    {
        $this->authorizeInvoice($invoice);
        $invoice->load(['customer', 'items', 'payments.user', 'user']);
        $business = $this->business();

        return view('invoices.show', compact('invoice', 'business'));
    }

    // ── Edit ──────────────────────────────────────────────────────────────────

    public function edit(Invoice $invoice)
    {
        $this->authorizeInvoice($invoice);

        if (! in_array($invoice->status, ['draft', 'sent'])) {
            return back()->with('error', 'Only draft or sent invoices can be edited.');
        }

        $business  = $this->business();
        $customers = Customer::where('business_id', $business->id)->orderBy('name')->get();
        $products  = Product::forBusiness($business->id)->active()->orderBy('name')->get();

        $invoice->load('items');

        return view('invoices.edit', compact('invoice', 'customers', 'products', 'business'));
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(Request $request, Invoice $invoice)
    {
        $this->authorizeInvoice($invoice);

        if (! in_array($invoice->status, ['draft', 'sent'])) {
            return back()->with('error', 'Only draft or sent invoices can be edited.');
        }

        $request->validate([
            'customer_id'       => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('customers', 'id')->where('business_id', $invoice->business_id)],
            'issue_date'        => 'required|date',
            'due_date'          => 'required|date|after_or_equal:issue_date',
            'discount_amount'   => 'nullable|numeric|min:0',
            'notes'             => 'nullable|string|max:2000',
            'payment_terms'     => 'nullable|string|max:500',
            'items'             => 'required|array|min:1',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity'    => 'required|numeric|min:0.01',
            'items.*.unit_price'  => 'required|numeric|min:0',
            'items.*.vat_rate'    => 'nullable|numeric|min:0|max:100',
        ]);

        DB::transaction(function () use ($request, $invoice) {
            [$subtotal, $vatAmount, $items] = $this->computeItems($request->items);

            $discount = (float) ($request->discount_amount ?? 0);
            $total    = $subtotal + $vatAmount - $discount;
            $balance  = max(0, $total - $invoice->amount_paid);

            $invoice->update([
                'customer_id'    => $request->customer_id,
                'issue_date'     => $request->issue_date,
                'due_date'       => $request->due_date,
                'subtotal'       => $subtotal,
                'vat_amount'     => $vatAmount,
                'discount_amount'=> $discount,
                'total'          => $total,
                'balance_due'    => $balance,
                'notes'          => $request->notes,
                'payment_terms'  => $request->payment_terms,
            ]);

            $invoice->items()->delete();

            foreach ($items as $item) {
                $item['invoice_id'] = $invoice->id;
                InvoiceItem::create($item);
            }
        });

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice updated.');
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function destroy(Invoice $invoice)
    {
        $this->authorizeInvoice($invoice);

        if ($invoice->status !== 'draft') {
            return back()->with('error', 'Only draft invoices can be deleted.');
        }

        $invoice->delete();

        return redirect()->route('invoices.index')->with('success', 'Invoice deleted.');
    }

    // ── Send (mark as sent) ───────────────────────────────────────────────────

    public function send(Invoice $invoice)
    {
        $this->authorizeInvoice($invoice);

        if ($invoice->status !== 'draft') {
            return back()->with('error', 'Only draft invoices can be sent.');
        }

        $business = $this->business();
        $etimsQueued = $business->isEtimsConfigured();

        // Marked 'pending' unconditionally before, so a business without eTIMS
        // set up showed "submission pending" on every invoice forever.
        $invoice->update($etimsQueued ? ['status' => 'sent', 'etims_status' => 'pending'] : ['status' => 'sent']);

        if ($etimsQueued) {
            SubmitEtimsDocument::dispatch('invoice', $invoice->id);
        }

        // WhatsApp invoice reminder
        if ($business->whatsapp_enabled && $invoice->customer_id) {
            $invoice->load('customer');
            if ($invoice->customer) {
                try {
                    WhatsAppService::forBusiness($business)->sendInvoiceReminder($invoice->customer, $invoice);
                } catch (\Exception) {}
            }
        }

        return back()->with('success', 'Invoice marked as sent.');
    }

    // ── Record payment ────────────────────────────────────────────────────────

    public function recordPayment(Request $request, Invoice $invoice)
    {
        $this->authorizeInvoice($invoice);

        $request->validate([
            'amount'    => 'required|numeric|min:0.01|max:' . $invoice->balance_due,
            'method'    => 'required|in:cash,mpesa,bank,card,other',
            'paid_date' => 'required|date',
            'reference' => 'nullable|string|max:100',
        ]);

        $invoice->recordPayment(
            (float) $request->amount,
            $request->method,
            $request->paid_date,
            $request->reference
        );

        $invoice->refresh();
        if ($invoice->status === 'paid') {
            WebhookService::dispatch('invoice.paid', $invoice->business_id, [
                'invoice_id'     => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'total'          => $invoice->total,
                'customer_id'    => $invoice->customer_id,
            ]);
        }

        return back()->with('success', 'Payment recorded.');
    }

    // ── PDF ───────────────────────────────────────────────────────────────────

    public function pdf(Invoice $invoice)
    {
        $this->authorizeInvoice($invoice);
        $invoice->load(['customer', 'items', 'business']);
        $business = $this->business();

        return view('invoices.pdf', compact('invoice', 'business'));
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function computeItems(array $rawItems): array
    {
        $subtotal   = 0;
        $vatAmount  = 0;
        $items      = [];

        foreach ($rawItems as $line) {
            $qty      = (float) $line['quantity'];
            $price    = (float) $line['unit_price'];
            $vatRate  = (float) ($line['vat_rate'] ?? 0);
            $lineSub  = $qty * $price;
            $lineVat  = round($lineSub * $vatRate / 100, 2);

            $subtotal  += $lineSub;
            $vatAmount += $lineVat;

            $items[] = [
                'description' => $line['description'],
                'quantity'    => $qty,
                'unit_price'  => $price,
                'vat_rate'    => $vatRate,
                'vat_amount'  => $lineVat,
                'subtotal'    => $lineSub,
            ];
        }

        return [$subtotal, $vatAmount, $items];
    }
}
