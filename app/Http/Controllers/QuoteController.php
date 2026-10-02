<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuoteRequest;
use App\Mail\QuoteEmail;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shift;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class QuoteController extends Controller {

    private function businessId(): int {
        return Auth::user()->currentBusiness()->id;
    }

    // â”€â”€ List all quotes â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function index(Request $request) {
        $businessId = $this->businessId();

        $query = Quote::with('customer')
            ->forBusiness($businessId);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by customer
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Search by quote number or customer name
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('quote_number', 'like', "%{$s}%")
                  ->orWhereHas('customer', function ($q2) use ($s) {
                      $q2->where('name', 'like', "%{$s}%");
                  });
            });
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('quote_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('quote_date', '<=', $request->date_to);
        }

        $quotes = $query->latest()->paginate(15)->withQueryString();

        // Stats
        $stats = [
            'total'     => Quote::forBusiness($businessId)->count(),
            'pending'   => Quote::forBusiness($businessId)
                ->whereIn('status', ['draft', 'sent'])
                ->count(),
            'accepted'  => Quote::forBusiness($businessId)
                ->where('status', 'accepted')
                ->count(),
            'converted' => Quote::forBusiness($businessId)
                ->where('status', 'converted')
                ->whereMonth('updated_at', now()->month)
                ->whereYear('updated_at', now()->year)
                ->count(),
        ];

        return view('quotes.index', compact('quotes', 'stats'));
    }

    // â”€â”€ Show create quote form â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function create() {
        $businessId = $this->businessId();

        $customers = Customer::where('business_id', $businessId)
            ->orderBy('name')
            ->get();

        $products = Product::forBusiness($businessId)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'selling_price', 'stock_qty', 'unit']);

        return view('quotes.create', compact('customers', 'products'));
    }

    // â”€â”€ Store new quote â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function store(QuoteRequest $request) {
        $businessId = $this->businessId();

        DB::beginTransaction();
        try {
            $data     = $request->validated();
            $items    = $data['items'];

            // Compute totals
            $subtotal = collect($items)->sum(function ($item) {
                return $item['unit_price'] * $item['quantity'];
            });

            $discount   = (float) ($data['discount_amount'] ?? 0);
            $taxRate    = (float) ($data['tax_rate'] ?? 0);
            $taxAmount  = round($subtotal * $taxRate / 100, 2);
            $total      = round($subtotal - $discount + $taxAmount, 2);

            $quote = Quote::create([
                'business_id'     => $businessId,
                'user_id'         => Auth::id(),
                'customer_id'     => $data['customer_id'] ?? null,
                'quote_number'    => Quote::generateQuoteNumber($businessId),
                'quote_date'      => $data['quote_date'],
                'valid_until'     => $data['valid_until'] ?? null,
                'subtotal'        => $subtotal,
                'discount_amount' => $discount,
                'tax_rate'        => $taxRate,
                'tax_amount'      => $taxAmount,
                'total'           => $total,
                'status'          => 'draft',
                'notes'           => $data['notes'] ?? null,
                'terms'           => $data['terms'] ?? null,
            ]);

            foreach ($items as $item) {
                QuoteItem::create([
                    'quote_id'     => $quote->id,
                    'product_id'   => $item['product_id'] ?? null,
                    'product_name' => $item['product_name'],
                    'description'  => $item['description'] ?? null,
                    'unit_price'   => $item['unit_price'],
                    'quantity'     => $item['quantity'],
                    'subtotal'     => $item['unit_price'] * $item['quantity'],
                ]);
            }

            DB::commit();

            return redirect()
                ->route('quotes.show', $quote)
                ->with('success', "Quote {$quote->quote_number} created successfully.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    // â”€â”€ View single quote â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function show(Quote $quote) {
        $this->authorizeQuote($quote);

        $quote->load(['customer', 'items.product', 'user']);

        // Update expired status
        if ($quote->isExpired() && $quote->status !== 'expired') {
            $quote->update(['status' => 'expired']);
        }

        return view('quotes.show', compact('quote'));
    }

    // â”€â”€ Show edit form â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function edit(Quote $quote) {
        $this->authorizeQuote($quote);

        if (!in_array($quote->status, ['draft', 'sent'])) {
            return redirect()
                ->route('quotes.show', $quote)
                ->with('error', 'Only draft or sent quotes can be edited.');
        }

        $businessId = $this->businessId();

        $customers = Customer::where('business_id', $businessId)
            ->orderBy('name')
            ->get();

        $products = Product::forBusiness($businessId)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'selling_price', 'stock_qty', 'unit']);

        $quote->load('items.product');

        return view('quotes.edit', compact('quote', 'customers', 'products'));
    }

    // â”€â”€ Update quote â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function update(QuoteRequest $request, Quote $quote) {
        $this->authorizeQuote($quote);

        if (!in_array($quote->status, ['draft', 'sent'])) {
            return redirect()
                ->route('quotes.show', $quote)
                ->with('error', 'Only draft or sent quotes can be edited.');
        }

        DB::beginTransaction();
        try {
            $data  = $request->validated();
            $items = $data['items'];

            // Recompute totals
            $subtotal = collect($items)->sum(function ($item) {
                return $item['unit_price'] * $item['quantity'];
            });

            $discount  = (float) ($data['discount_amount'] ?? 0);
            $taxRate   = (float) ($data['tax_rate'] ?? 0);
            $taxAmount = round($subtotal * $taxRate / 100, 2);
            $total     = round($subtotal - $discount + $taxAmount, 2);

            $quote->update([
                'customer_id'     => $data['customer_id'] ?? null,
                'quote_date'      => $data['quote_date'],
                'valid_until'     => $data['valid_until'] ?? null,
                'subtotal'        => $subtotal,
                'discount_amount' => $discount,
                'tax_rate'        => $taxRate,
                'tax_amount'      => $taxAmount,
                'total'           => $total,
                'notes'           => $data['notes'] ?? null,
                'terms'           => $data['terms'] ?? null,
            ]);

            // Delete existing items and recreate
            $quote->items()->delete();

            foreach ($items as $item) {
                QuoteItem::create([
                    'quote_id'     => $quote->id,
                    'product_id'   => $item['product_id'] ?? null,
                    'product_name' => $item['product_name'],
                    'description'  => $item['description'] ?? null,
                    'unit_price'   => $item['unit_price'],
                    'quantity'     => $item['quantity'],
                    'subtotal'     => $item['unit_price'] * $item['quantity'],
                ]);
            }

            DB::commit();

            return redirect()
                ->route('quotes.show', $quote)
                ->with('success', "Quote {$quote->quote_number} updated successfully.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    // â”€â”€ Delete quote â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function destroy(Quote $quote) {
        $this->authorizeQuote($quote);

        $quote->delete();

        return redirect()
            ->route('quotes.index')
            ->with('success', "Quote {$quote->quote_number} deleted.");
    }

    // â”€â”€ Mark as sent â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function markSent(Quote $quote) {
        $this->authorizeQuote($quote);

        $quote->update(['status' => 'sent']);

        return redirect()->back()
            ->with('success', "Quote {$quote->quote_number} marked as sent.");
    }

    // â”€â”€ Mark as accepted â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function markAccepted(Quote $quote) {
        $this->authorizeQuote($quote);

        $quote->update(['status' => 'accepted']);

        return redirect()->back()
            ->with('success', "Quote {$quote->quote_number} marked as accepted.");
    }

    // â”€â”€ Mark as rejected â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function markRejected(Quote $quote) {
        $this->authorizeQuote($quote);

        $quote->update(['status' => 'rejected']);

        return redirect()->back()
            ->with('success', "Quote {$quote->quote_number} marked as rejected.");
    }

    // â”€â”€ Convert to sale â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function convertToSale(Quote $quote) {
        $this->authorizeQuote($quote);

        if (!$quote->canBeConverted()) {
            return redirect()->back()
                ->with('error', 'This quote cannot be converted to a sale.');
        }

        $quote->load('items');

        DB::beginTransaction();
        try {
            $businessId = $this->businessId();

            $sale = Sale::create([
                'business_id'     => $businessId,
                'shift_id'        => Shift::currentOpenId($businessId),
                'customer_id'     => $quote->customer_id,
                'user_id'         => Auth::id(),
                'invoice_number'  => Sale::generateInvoiceNumber($businessId),
                'subtotal'        => $quote->subtotal,
                'discount_amount' => $quote->discount_amount,
                'tax_amount'      => $quote->tax_amount,
                // invoice.blade.php reads vat_amount, not tax_amount — see
                // SaleService::createSale for full context.
                'vat_amount'      => $quote->tax_amount,
                'total_amount'    => $quote->total,
                'paid_amount'     => 0,
                'balance_due'     => $quote->total,
                'payment_method'  => 'credit',
                'payment_status'  => 'unpaid',
                'sale_status'     => 'completed',
                'notes'           => $quote->notes,
            ]);

            foreach ($quote->items as $item) {
                // Converting a quote created a completed Sale but never
                // touched Product stock at all — there's no model event doing
                // this implicitly anywhere in the app; SaleService::createSale
                // is the only place that actually decrements it, and this
                // path bypasses it entirely. Every quote-to-sale conversion
                // recorded a real sale while silently leaving inventory
                // untouched. Mirror SaleService's stock check + decrement.
                $product = $item->product_id ? Product::find($item->product_id) : null;

                if ($product) {
                    if ($product->stock_qty < $item->quantity) {
                        throw new \Exception(
                            "Insufficient stock for \"{$item->product_name}\": available {$product->stock_qty}, quote requires {$item->quantity}. " .
                            'Update stock or the quote quantity before converting.'
                        );
                    }
                    $product->decrement('stock_qty', $item->quantity);
                    \App\Models\ProductBatch::consume((int) $product->id, null, (float) $item->quantity);
                }

                SaleItem::create([
                    'sale_id'      => $sale->id,
                    'product_id'   => $item->product_id,
                    'product_name' => $item->product_name,
                    'unit_price'   => $item->unit_price,
                    'buying_price' => $product?->buying_price ?? 0,
                    'quantity'     => $item->quantity,
                    'discount'     => 0,
                    'subtotal'     => $item->subtotal,
                ]);
            }

            $quote->update([
                'status'               => 'converted',
                'converted_to_sale_id' => $sale->id,
            ]);

            DB::commit();

            return redirect()
                ->route('sales.show', $sale)
                ->with('success', 'Quote converted to sale successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    // â”€â”€ Authorization helper â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Print / download PDF — streamed (not force-downloaded) so it opens
    // directly in the browser's own PDF viewer; its native print button
    // covers "print" without a separate print-specific view/route.
    public function pdf(Quote $quote) {
        $this->authorizeQuote($quote);
        $quote->load(['items', 'customer', 'user', 'business']);

        $pdf = Pdf::loadView('quotes.pdf', compact('quote'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream('Quote-' . $quote->quote_number . '.pdf');
    }

    // Email to customer.
    public function email(Quote $quote) {
        $this->authorizeQuote($quote);

        if (!$quote->customer || !$quote->customer->email) {
            return back()->with('error', 'This quote has no customer email address on record.');
        }

        $quote->load(['items', 'customer', 'business', 'user']);

        try {
            Mail::to($quote->customer->email)->send(new QuoteEmail($quote));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Quote email failed', ['quote' => $quote->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'The quote could not be emailed right now. Please try again shortly, or download the PDF and send it yourself.');
        }

        // "Mark Sent" was previously the only way this status ever changed,
        // and it did nothing but write the label — completely disconnected
        // from whether the quote was actually sent anywhere. Actually
        // emailing it is real evidence of sending, so advance the status
        // automatically here too (still leaves the manual button available
        // for quotes sent some other way — WhatsApp, printed, read aloud).
        if ($quote->status === 'draft') {
            $quote->update(['status' => 'sent']);
        }

        return back()->with('success', "Quote emailed to {$quote->customer->email}.");
    }

    private function authorizeQuote(Quote $quote): void {
        if ($quote->business_id !== $this->businessId()) {
            abort(403, 'Unauthorized action.');
        }
    }
}

