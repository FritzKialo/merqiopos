<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RecurringInvoiceController extends Controller
{
    private function businessId(): int
    {
        return Auth::user()->currentBusiness()->id;
    }

    // â”€â”€ List recurring invoices â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function index()
    {
        $businessId = $this->businessId();

        $recurringInvoices = RecurringInvoice::forBusiness($businessId)
            ->with(['customer'])
            ->latest()
            ->paginate(15);

        try {
            $generatedMonth = Sale::forBusiness($businessId)
                ->whereNotNull('recurring_invoice_id')
                ->thisMonth()
                ->count();
        } catch (\Throwable $e) {
            $generatedMonth = 0;
        }

        $stats = [
            'active'          => RecurringInvoice::forBusiness($businessId)->active()->count(),
            'paused'          => RecurringInvoice::forBusiness($businessId)->where('is_active', false)->count(),
            'due_today'       => RecurringInvoice::forBusiness($businessId)->due()->count(),
            'generated_month' => $generatedMonth,
        ];

        return view('recurring.index', compact('recurringInvoices', 'stats'));
    }

    // â”€â”€ Show create form â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function create()
    {
        $businessId = $this->businessId();

        $customers = Customer::where('business_id', $businessId)
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        $products = Product::forBusiness($businessId)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'selling_price', 'unit']);

        return view('recurring.create', compact('customers', 'products'));
    }

    // â”€â”€ Store new recurring invoice â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function store(Request $request)
    {
        $businessIdForValidation = $this->businessId();

        // customer_id/items.*.product_id were unscoped exists: checks —
        // could link this recurring invoice to another business's customer
        // or product.
        $validated = $request->validate([
            'title'          => 'required|string|max:255',
            'customer_id'    => ['nullable', \Illuminate\Validation\Rule::exists('customers', 'id')->where('business_id', $businessIdForValidation)],
            'frequency'      => 'required|in:weekly,monthly,quarterly,yearly',
            'next_run_date'  => 'required|date',
            'end_date'       => 'nullable|date|after:next_run_date',
            'notes'          => 'nullable|string|max:1000',
            'items'          => 'required|array|min:1',
            'items.*.product_id'    => ['nullable', \Illuminate\Validation\Rule::exists('products', 'id')->where('business_id', $businessIdForValidation)],
            'items.*.product_name'  => 'required|string|max:255',
            'items.*.description'   => 'nullable|string|max:500',
            'items.*.unit_price'    => 'required|numeric|min:0',
            'items.*.quantity'      => 'required|integer|min:1',
            // The form (recurring/create.blade.php) submits these three, with
            // tax_amount computed client-side from subtotal/discount/tax_rate
            // — they were validated nowhere and ignored below, so every
            // discount/tax a user entered was silently discarded and every
            // Sale later generated from this template billed subtotal only.
            'discount_amount'       => 'nullable|numeric|min:0',
            'tax_rate'              => 'nullable|numeric|min:0|max:100',
            'tax_amount'            => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $businessId = $this->businessId();

            // Compute totals
            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $subtotal += $item['unit_price'] * $item['quantity'];
            }

            $discountAmount = $validated['discount_amount'] ?? 0;
            $taxAmount      = $validated['tax_amount'] ?? 0;

            $ri = RecurringInvoice::create([
                'business_id'    => $businessId,
                'user_id'        => Auth::id(),
                'customer_id'    => $validated['customer_id'] ?? null,
                'title'          => $validated['title'],
                'frequency'      => $validated['frequency'],
                'next_run_date'  => $validated['next_run_date'],
                'end_date'       => $validated['end_date'] ?? null,
                'notes'          => $validated['notes'] ?? null,
                'is_active'      => true,
                'subtotal'       => $subtotal,
                'discount_amount'=> $discountAmount,
                'tax_rate'       => $validated['tax_rate'] ?? 0,
                'tax_amount'     => $taxAmount,
                'total'          => $subtotal - $discountAmount + $taxAmount,
                'run_count'      => 0,
            ]);

            foreach ($validated['items'] as $item) {
                $lineSubtotal = $item['unit_price'] * $item['quantity'];
                RecurringInvoiceItem::create([
                    'recurring_invoice_id' => $ri->id,
                    'product_id'           => $item['product_id'] ?? null,
                    'product_name'         => $item['product_name'],
                    'description'          => $item['description'] ?? null,
                    'unit_price'           => $item['unit_price'],
                    'quantity'             => $item['quantity'],
                    'subtotal'             => $lineSubtotal,
                ]);
            }

            DB::commit();

            return redirect()
                ->route('recurring.index')
                ->with('success', "Recurring invoice \"{$ri->title}\" created successfully.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    // â”€â”€ View single recurring invoice â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function show(RecurringInvoice $recurringInvoice)
    {
        $this->authorizeRI($recurringInvoice);
        $recurringInvoice->load(['customer', 'items', 'user']);

        return view('recurring.show', compact('recurringInvoice'));
    }

    // â”€â”€ Show edit form â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function edit(RecurringInvoice $recurringInvoice)
    {
        $this->authorizeRI($recurringInvoice);
        $recurringInvoice->load(['items', 'customer']);

        $businessId = $this->businessId();

        $customers = Customer::where('business_id', $businessId)
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        $products = Product::forBusiness($businessId)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'selling_price', 'unit']);

        return view('recurring.edit', compact(
            'recurringInvoice', 'customers', 'products'
        ));
    }

    // â”€â”€ Update recurring invoice â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function update(Request $request, RecurringInvoice $recurringInvoice)
    {
        $this->authorizeRI($recurringInvoice);

        $validated = $request->validate([
            'title'         => 'required|string|max:255',
            'customer_id'   => ['nullable', \Illuminate\Validation\Rule::exists('customers', 'id')->where('business_id', $recurringInvoice->business_id)],
            'frequency'     => 'required|in:weekly,monthly,quarterly,yearly',
            'next_run_date' => 'required|date',
            'end_date'      => 'nullable|date|after:next_run_date',
            'notes'         => 'nullable|string|max:1000',
            'items'         => 'required|array|min:1',
            'items.*.product_id'   => ['nullable', \Illuminate\Validation\Rule::exists('products', 'id')->where('business_id', $recurringInvoice->business_id)],
            'items.*.product_name' => 'required|string|max:255',
            'items.*.description'  => 'nullable|string|max:500',
            'items.*.unit_price'   => 'required|numeric|min:0',
            'items.*.quantity'     => 'required|integer|min:1',
            // Same gap as store(): the edit form (recurring/edit.blade.php)
            // submits these three but they were never validated or applied —
            // 'total' below was being recomputed as bare subtotal, silently
            // discarding whatever discount/tax the customer had configured
            // (existing or freshly edited) every time the template was saved.
            'discount_amount'      => 'nullable|numeric|min:0',
            'tax_rate'             => 'nullable|numeric|min:0|max:100',
            'tax_amount'           => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $subtotal += $item['unit_price'] * $item['quantity'];
            }

            $discountAmount = $validated['discount_amount'] ?? 0;
            $taxAmount      = $validated['tax_amount'] ?? 0;

            $recurringInvoice->update([
                'customer_id'    => $validated['customer_id'] ?? null,
                'title'          => $validated['title'],
                'frequency'      => $validated['frequency'],
                'next_run_date'  => $validated['next_run_date'],
                'end_date'       => $validated['end_date'] ?? null,
                'notes'          => $validated['notes'] ?? null,
                'subtotal'       => $subtotal,
                'discount_amount'=> $discountAmount,
                'tax_rate'       => $validated['tax_rate'] ?? 0,
                'tax_amount'     => $taxAmount,
                'total'          => $subtotal - $discountAmount + $taxAmount,
            ]);

            // Replace items
            $recurringInvoice->items()->delete();
            foreach ($validated['items'] as $item) {
                $lineSubtotal = $item['unit_price'] * $item['quantity'];
                RecurringInvoiceItem::create([
                    'recurring_invoice_id' => $recurringInvoice->id,
                    'product_id'           => $item['product_id'] ?? null,
                    'product_name'         => $item['product_name'],
                    'description'          => $item['description'] ?? null,
                    'unit_price'           => $item['unit_price'],
                    'quantity'             => $item['quantity'],
                    'subtotal'             => $lineSubtotal,
                ]);
            }

            DB::commit();

            return redirect()
                ->route('recurring.show', $recurringInvoice)
                ->with('success', 'Recurring invoice updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    // â”€â”€ Delete (soft) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function destroy(RecurringInvoice $recurringInvoice)
    {
        $this->authorizeRI($recurringInvoice);
        $recurringInvoice->delete();

        return redirect()
            ->route('recurring.index')
            ->with('success', "Recurring invoice \"{$recurringInvoice->title}\" deleted.");
    }

    // â”€â”€ Toggle active / paused â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function toggle(RecurringInvoice $recurringInvoice)
    {
        $this->authorizeRI($recurringInvoice);
        $recurringInvoice->update(['is_active' => !$recurringInvoice->is_active]);

        $status = $recurringInvoice->is_active ? 'activated' : 'paused';

        return back()->with('success', "Recurring invoice \"{$recurringInvoice->title}\" {$status}.");
    }

    // â”€â”€ Run now (generate one Sale immediately) â”€
    public function runNow(RecurringInvoice $recurringInvoice)
    {
        $this->authorizeRI($recurringInvoice);
        $recurringInvoice->load(['items', 'customer']);

        DB::beginTransaction();
        try {
            $sale = $this->generateSale($recurringInvoice);
            DB::commit();

            return redirect()
                ->route('sales.show', $sale)
                ->with('success', "Invoice generated from recurring template \"{$recurringInvoice->title}\".");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    // â”€â”€ Private: generate a Sale from a RecurringInvoice â”€â”€
    private function generateSale(RecurringInvoice $ri): Sale
    {
        $businessId = $ri->business_id;

        $sale = Sale::create([
            'business_id'          => $businessId,
            'shift_id'             => Shift::currentOpenId($businessId),
            'customer_id'          => $ri->customer_id,
            'user_id'              => Auth::id() ?? $ri->user_id,
            'invoice_number'       => Sale::generateInvoiceNumber($businessId),
            'subtotal'             => $ri->subtotal,
            'discount_amount'      => $ri->discount_amount ?? 0,
            'tax_amount'           => $ri->tax_amount ?? 0,
            // invoice.blade.php reads vat_amount, not tax_amount — see
            // SaleService::createSale for full context.
            'vat_amount'           => $ri->tax_amount ?? 0,
            'total_amount'         => $ri->total,
            'paid_amount'          => 0,
            'balance_due'          => $ri->total,
            'payment_method'       => 'credit',
            'payment_status'       => 'unpaid',
            'sale_status'          => 'completed',
            'notes'                => $ri->notes,
            'recurring_invoice_id' => $ri->id,
        ]);

        foreach ($ri->items as $item) {
            // Same gap as QuoteController::convertToSale() — this created a
            // completed Sale but never touched Product stock at all. Product-
            // linked recurring items (as opposed to service/retainer lines
            // with no product_id) need the same check + decrement
            // SaleService::createSale does for a normal POS sale.
            $product = $item->product_id ? Product::find($item->product_id) : null;

            if ($product) {
                if ($product->stock_qty < $item->quantity) {
                    throw new \Exception(
                        "Insufficient stock for \"{$item->product_name}\": available {$product->stock_qty}, recurring invoice requires {$item->quantity}."
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
                'buying_price' => $item->product?->buying_price ?? 0,
                'quantity'     => $item->quantity,
                'discount'     => 0,
                'subtotal'     => $item->subtotal,
            ]);
        }

        // Update customer balance if applicable
        if ($ri->customer_id) {
            \App\Models\Customer::where('id', $ri->customer_id)
                ->increment('balance_owed', $ri->total);
        }

        // Update recurring invoice metadata
        $now = now();
        $ri->update([
            'last_run_date' => $now->toDateString(),
            'next_run_date' => $ri->nextRunAfter($now)->toDateString(),
            'run_count'     => $ri->run_count + 1,
        ]);

        return $sale;
    }

    // â”€â”€ Authorization helper â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    private function authorizeRI(RecurringInvoice $recurringInvoice): void
    {
        if ($recurringInvoice->business_id !== $this->businessId()) {
            abort(403, 'Unauthorized action.');
        }
    }
}

