<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Sale;
use App\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller {

    private function businessId(): int {
        return Auth::user()->currentBusiness()->id;
    }

    // â”€â”€ List all customers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function index(Request $request) {
        $businessId = $this->businessId();
        $query = Customer::forBusiness($businessId);

        // Search
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name',  'like', "%{$s}%")
                  ->orWhere('phone','like', "%{$s}%")
                  ->orWhere('email','like', "%{$s}%");
            });
        }

        // Filter: only those with debt
        if ($request->boolean('with_debt')) {
            $query->withDebt();
        }

        $customers = $query->orderBy('name')
                        ->paginate(15)
                        ->withQueryString();

        // Summary stats
        $stats = [
            'total'      => Customer::forBusiness(
                                $businessId)->count(),
            'with_debt'  => Customer::forBusiness(
                                $businessId)
                                ->withDebt()->count(),
            'total_debt' => Customer::forBusiness(
                                $businessId)
                                ->sum('balance_owed'),
            'new_month'  => Customer::forBusiness(
                                $businessId)
                                ->whereMonth('created_at',
                                    now()->month)
                                ->whereYear('created_at',
                                    now()->year)
                                ->count(),
        ];

        return view('customers.index', compact(
            'customers', 'stats'
        ));
    }

    // â”€â”€ Show create form â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function create() {
        return view('customers.create');
    }

    // â”€â”€ Store new customer â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function store(CustomerRequest $request) {
        $data = $request->validated();
        $data['business_id'] = $this->businessId();

        $customer = Customer::create($data);
        $this->syncTags($customer, $request);

        // Was declared in WebhookService::EVENTS from day one but never
        // actually fired anywhere — every webhook subscribed to
        // customer.created has been silently waiting forever.
        WebhookService::dispatch('customer.created', $customer->business_id, [
            'customer_id' => $customer->id,
            'name'        => $customer->name,
            'phone'       => $customer->phone,
        ]);

        return redirect()
            ->route('customers.index')
            ->with('success',
                'Customer added successfully.');
    }

    /** Set the customer's tags from the form — only tags of this business are accepted. */
    private function syncTags(Customer $customer, Request $request): void {
        if (! $request->has('tags_submitted')) {
            return; // a form without the tag picker must not wipe existing tags
        }
        $ids = \App\Models\CustomerTag::where('business_id', $this->businessId())
            ->whereIn('id', array_map('intval', (array) $request->input('tag_ids', [])))
            ->pluck('id')
            ->all();
        $customer->tags()->sync($ids);
    }

    // â”€â”€ Show customer profile â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function show(
        Customer $customer,
        Request $request
    ) {
        $this->authorizeCustomer($customer);

        // Paginated sales history
        $sales = Sale::with('items')
            ->where('customer_id', $customer->id)
            ->where('sale_status', 'completed')
            ->latest()
            ->paginate(10);

        // Summary for this customer
        $summary = [
            'total_spent'    => $customer->totalSpent(),
            'total_purchases'=> $customer->totalPurchases(),
            'balance_owed'   => $customer->balance_owed,
            'last_purchase'  => $customer->lastPurchaseDate(),
        ];

        return view('customers.show', compact(
            'customer', 'sales', 'summary'
        ));
    }

    // â”€â”€ Show edit form â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function edit(Customer $customer) {
        $this->authorizeCustomer($customer);
        return view('customers.edit', compact(
            'customer'
        ));
    }

    // â”€â”€ Update customer â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function update(
        CustomerRequest $request,
        Customer $customer
    ) {
        $this->authorizeCustomer($customer);
        $customer->update($request->validated());
        $this->syncTags($customer, $request);

        return redirect()
            ->route('customers.show', $customer)
            ->with('success',
                'Customer updated successfully.');
    }

    // â”€â”€ Delete customer â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function destroy(Customer $customer) {
        $this->authorizeCustomer($customer);

        if ($customer->balance_owed > 0) {
            return back()->with('error',
                'Cannot delete customer with 
                outstanding balance of KSh ' .
                number_format(
                    $customer->balance_owed, 2
                ) . '. Clear the balance first.');
        }

        $customer->delete();

        return redirect()
            ->route('customers.index')
            ->with('success',
                'Customer deleted successfully.');
    }

    // â”€â”€ Record debt payment â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function recordPayment(
        Request $request,
        Customer $customer
    ) {
        $this->authorizeCustomer($customer);

        $request->validate([
            'payment_amount' => [
                'required',
                'numeric',
                'min:1',
                'max:' . $customer->balance_owed,
            ],
            'payment_method' => 'required|in:cash,mpesa,bank_transfer',
            'mpesa_reference'=> 'nullable|string|max:50',
        ]);

        $amount = $request->payment_amount;

        DB::beginTransaction();
        try {
            // Reduce customer balance
            $customer->decrement(
                'balance_owed', $amount
            );

            // Find the oldest unpaid/partial sale
            // and mark as paid if balance clears
            $unpaidSales = Sale::where(
                    'customer_id', $customer->id
                )
                ->where('business_id', $this->businessId())
                ->whereIn('payment_status', [
                    'unpaid', 'partial'
                ])
                ->where('sale_status', 'completed')
                ->orderBy('created_at')
                ->get();

            $remaining = $amount;

            foreach ($unpaidSales as $sale) {
                if ($remaining <= 0) break;

                if ($remaining >= $sale->balance_due) {
                    $remaining -= $sale->balance_due;
                    $sale->update([
                        'paid_amount'    => $sale->total_amount,
                        'balance_due'    => 0,
                        'payment_status' => 'paid',
                        'payment_method' => $request->payment_method,
                        'mpesa_reference'=> $request->mpesa_reference,
                    ]);
                } else {
                    $sale->update([
                        'paid_amount'    => $sale->paid_amount + $remaining,
                        'balance_due'    => $sale->balance_due - $remaining,
                        'payment_status' => 'partial',
                    ]);
                    $remaining = 0;
                }
            }

            AuditLog::record('customer.payment', $customer, [
                'amount'          => $amount,
                'payment_method'  => $request->payment_method,
                'mpesa_reference' => $request->mpesa_reference,
            ]);

            DB::commit();

            return redirect()
                ->route('customers.show', $customer)
                ->with('success',
                    'Payment of KSh ' .
                    number_format($amount, 2) .
                    ' recorded successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error',
                'Payment failed. Please try again.');
        }
    }

    // â”€â”€ Account statement â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function statement(Customer $customer, Request $request) {
        $this->authorizeCustomer($customer);
        $customer->load('business');

        $fromDate = $request->get('from_date', now()->subDays(30)->toDateString());
        $toDate   = $request->get('to_date',   now()->toDateString());

        $sales = Sale::where('customer_id', $customer->id)
            ->whereBetween('created_at', [$fromDate, $toDate . ' 23:59:59'])
            ->where('sale_status', '!=', 'cancelled')
            ->orderBy('created_at')
            ->get();

        $transactions   = [];
        $runningBalance = 0;
        foreach ($sales as $sale) {
            $runningBalance += $sale->total_amount;
            $transactions[] = ['date' => $sale->created_at, 'reference' => $sale->invoice_number, 'type' => 'sale', 'amount' => $sale->total_amount, 'balance' => $runningBalance, 'sale' => $sale];
            if ($sale->paid_amount > 0) {
                $runningBalance -= $sale->paid_amount;
                $transactions[] = ['date' => $sale->created_at, 'reference' => 'Payment on ' . $sale->invoice_number, 'type' => 'payment', 'amount' => -$sale->paid_amount, 'balance' => $runningBalance, 'sale' => $sale];
            }
        }

        $summary = [
            'total_sales'  => $sales->sum('total_amount'),
            'total_paid'   => $sales->sum('paid_amount'),
            'outstanding'  => $customer->balance_owed,
            'sale_count'   => $sales->count(),
        ];

        return view('customers.statement', compact('customer', 'transactions', 'summary', 'fromDate', 'toDate'));
    }

    public function statementPdf(Customer $customer, Request $request) {
        $this->authorizeCustomer($customer);
        $customer->load('business');

        $fromDate = $request->get('from_date', now()->subDays(30)->toDateString());
        $toDate   = $request->get('to_date',   now()->toDateString());

        $sales = Sale::where('customer_id', $customer->id)
            ->whereBetween('created_at', [$fromDate, $toDate . ' 23:59:59'])
            ->where('sale_status', '!=', 'cancelled')
            ->orderBy('created_at')
            ->get();

        $transactions   = [];
        $runningBalance = 0;
        foreach ($sales as $sale) {
            $runningBalance += $sale->total_amount;
            $transactions[] = ['date' => $sale->created_at, 'reference' => $sale->invoice_number, 'type' => 'sale', 'amount' => $sale->total_amount, 'balance' => $runningBalance];
            if ($sale->paid_amount > 0) {
                $runningBalance -= $sale->paid_amount;
                $transactions[] = ['date' => $sale->created_at, 'reference' => 'Payment on ' . $sale->invoice_number, 'type' => 'payment', 'amount' => -$sale->paid_amount, 'balance' => $runningBalance];
            }
        }

        $summary = ['total_sales' => $sales->sum('total_amount'), 'total_paid' => $sales->sum('paid_amount'), 'outstanding' => $customer->balance_owed, 'sale_count' => $sales->count()];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('customers.statement-pdf',
            compact('customer', 'transactions', 'summary', 'fromDate', 'toDate'));

        return $pdf->download("statement-{$customer->name}-{$toDate}.pdf");
    }

    // â”€â”€ Export customers as CSV â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function export(Request $request) {
        $customers = Customer::forBusiness($this->businessId())
            ->orderBy('name')
            ->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="customers_' . now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($customers) {
            $handle = fopen('php://output', 'w');
            \App\Support\Csv::put($handle, ['Name', 'Phone', 'Email', 'Address', 'Balance Owed', 'Notes']);
            foreach ($customers as $c) {
                \App\Support\Csv::put($handle, [$c->name, $c->phone, $c->email ?? '', $c->address ?? '', $c->balance_owed, $c->notes ?? '']);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ── Update Credit Limit ──────────────────────────────────────────────────
    public function updateCreditLimit(\Illuminate\Http\Request $request, \App\Models\Customer $customer)
    {
        $this->authorizeCustomer($customer);
        $request->validate([
            'credit_limit'         => 'required|numeric|min:0',
            'credit_limit_enabled' => 'nullable|boolean',
        ]);
        $customer->update([
            'credit_limit'         => $request->credit_limit,
            'credit_limit_enabled' => $request->boolean('credit_limit_enabled'),
        ]);
        return back()->with('success', 'Credit limit updated.');
    }

    // â”€â”€ Authorization helper â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    private function authorizeCustomer(
        Customer $customer
    ): void {
        if ($customer->business_id
            !== $this->businessId()) {
            abort(403, 'Unauthorized action.');
        }
    }
}
