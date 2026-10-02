<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaleRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Jobs\SubmitEtimsDocument;
use App\Models\Discount;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyTransaction;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\Sale;
use App\Services\SaleService;
use App\Services\WebhookService;
use App\Services\WhatsAppService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SalesController extends Controller {

    private function businessId(): int {
        return Auth::user()->currentBusiness()->id;
    }

    // â”€â”€ List all sales â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function index(Request $request) {
        $businessId = $this->businessId();

        $query = Sale::with(['customer', 'user', 'items'])
            ->forBusiness($businessId);

        // Search by invoice number
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('invoice_number', 
                        'like', "%{$s}%")
                  ->orWhereHas('customer', function ($q2) 
                        use ($s) {
                        $q2->where('name', 
                            'like', "%{$s}%");
                    });
            });
        }

        // Filter by payment status
        if ($request->filled('payment_status')) {
            $query->where(
                'payment_status', 
                $request->payment_status
            );
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate(
                'created_at', '>=', $request->date_from
            );
        }
        if ($request->filled('date_to')) {
            $query->whereDate(
                'created_at', '<=', $request->date_to
            );
        }

        $sales = $query->latest()->paginate(15)
                    ->withQueryString();

        // Summary stats
        $stats = [
            'today_total'   => Sale::forBusiness($businessId)
                ->today()
                ->where('sale_status', 'completed')
                ->sum('total_amount'),
            'today_count'   => Sale::forBusiness($businessId)
                ->today()
                ->where('sale_status', 'completed')
                ->count(),
            'month_total'   => Sale::forBusiness($businessId)
                ->thisMonth()
                ->where('sale_status', 'completed')
                ->sum('total_amount'),
            'unpaid_total'  => Sale::forBusiness($businessId)
                ->where('payment_status', '!=', 'paid')
                ->where('sale_status', 'completed')
                ->sum('balance_due'),
        ];

        return view('sales.index', compact(
            'sales', 'stats'
        ));
    }

    // â”€â”€ Show create sale form â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function create() {
        // Overall managers are oversight-only — no POS. (They inherit 'owner'
        // in RequireRole, so this can't be enforced by route role lists.)
        abort_if(auth()->user()->isOverallManager(), 403, 'Overall managers do not have POS access.');

        $businessId = $this->businessId();

        $products  = Product::forBusiness($businessId)
            ->active()
            // A product whose stock is all held in variants has 0 on the base
            // record, so requiring base stock hid it from the till entirely.
            ->where(function ($q) {
                $q->where('stock_qty', '>', 0)
                  ->orWhere(function ($q2) {
                      $q2->where('has_variants', true)
                         ->whereHas('activeVariants', fn ($v) => $v->where('stock_qty', '>', 0));
                  });
            })
            ->where('hide_in_pos', false)
            ->orderBy('name')
            ->get(['id','name','sku','selling_price',
                   'buying_price','stock_qty','unit','barcode',
                   'has_variants','track_serials','category_id','is_featured']);

        // The visible product row is now a single compact strip (shrunk from
        // a full browsing grid — the cart needed the space back), so it only
        // shows products someone has actually flagged as featured. Nothing
        // is hidden from the till itself: search/barcode-scan still reaches
        // every product via the full $productsData JS array below. Falls
        // back to showing everything if no business has set up featured
        // products yet, so the strip isn't just empty by default.
        $gridProducts = $products->where('is_featured', true)->values();
        if ($gridProducts->isEmpty()) {
            $gridProducts = $products;
        }

        // Attach variant/serial data
        $productsData = $products->map(function ($p) {
            $data = $p->toArray();
            if ($p->has_variants) {
                $data['variants'] = $p->activeVariants()
                    ->get(['id','name','price','stock_qty'])
                    ->toArray();
            }
            if ($p->track_serials) {
                $data['serials'] = \App\Models\SerialNumber::where('product_id', $p->id)
                    ->inStock()->get(['id','serial_number'])->toArray();
            }
            return $data;
        });

        $customers = Customer::where(
                        'business_id', $businessId
                     )->orderBy('name')->get();

        $bundles = ProductBundle::forBusiness($businessId)->active()
            ->with('items.product')
            ->get();

        $invoiceNo = Sale::generateInvoiceNumber(
            $businessId
        );

        $hasLoyalty = LoyaltyProgram::where('business_id', $businessId)
            ->where('is_active', true)->exists();

        $lineDiscounts = \App\Models\Discount::where('business_id', $businessId)
            ->where('is_active', true)
            ->get(['id', 'name', 'type', 'value']);

        $business        = Auth::user()->currentBusiness();
        $mpesaConfigured = $business->hasFeature('mpesa_sales') && $business->hasMpesaConfigured();

        return view('sales.create', compact(
            'products', 'gridProducts', 'productsData', 'customers', 'invoiceNo', 'bundles',
            'hasLoyalty', 'lineDiscounts', 'mpesaConfigured'
        ));
    }

    // â”€â”€ Store new sale â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function store(SaleRequest $request, SaleService $saleService) {
        // Overall managers are oversight-only — no POS.
        abort_if(auth()->user()->isOverallManager(), 403, 'Overall managers do not have POS access.');

        $businessId = $this->businessId();

        // Idempotency: if offline_id already exists, return the existing sale
        if ($request->filled('offline_id')) {
            $existing = Sale::where('offline_id', $request->offline_id)
                ->where('business_id', $businessId)
                ->first();
            if ($existing) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success'        => true,
                        'duplicate'      => true,
                        'sale_id'        => $existing->id,
                        'invoice_number' => $existing->invoice_number,
                        'redirect'       => route('sales.show', $existing),
                    ]);
                }
                return redirect()->route('sales.show', $existing)
                    ->with('info', "Sale {$existing->invoice_number} was already recorded.");
            }
        }

        DB::beginTransaction();
        try {
            $sale = $saleService->createSale(
                $request->validated(),
                $businessId,
                Auth::id()
            );

            DB::commit();

            // Dispatch eTIMS submission if VAT registered and configured
            $business = Auth::user()->currentBusiness();
            if ($business->isEtimsConfigured()) {
                $sale->update(['etims_status' => 'pending']);
                // KRA's rules require the eTIMS response before a receipt is
                // issued, so try it inline; if KRA is unreachable fall back
                // to the queue (retried) rather than losing the sale.
                try {
                    SubmitEtimsDocument::dispatchSync('sale', $sale->id);
                    $sale->refresh();
                    if ($sale->etims_status !== 'submitted') {
                        SubmitEtimsDocument::dispatch('sale', $sale->id)->delay(now()->addMinutes(2));
                    }
                } catch (\Throwable $e) {
                    SubmitEtimsDocument::dispatch('sale', $sale->id)->delay(now()->addMinutes(2));
                }
            }

            // WhatsApp receipt
            if ($business->whatsapp_enabled && $sale->customer_id) {
                $sale->load('customer');
                if ($sale->customer) {
                    try {
                        WhatsAppService::forBusiness($business)->sendReceiptLink($sale->customer, $sale);
                    } catch (\Exception) {}
                }
            }

            // Loyalty points
            $this->awardLoyaltyPoints($sale, $businessId);

            // Webhook: sale.created
            WebhookService::dispatch('sale.created', $businessId, [
                'sale_id'        => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'total_amount'   => $sale->total_amount,
                'payment_method' => $sale->payment_method,
                'customer_id'    => $sale->customer_id,
            ]);

            // JSON response for offline sync POST
            if ($request->expectsJson()) {
                return response()->json([
                    'success'        => true,
                    'sale_id'        => $sale->id,
                    'invoice_number' => $sale->invoice_number,
                    'redirect'       => route('sales.show', $sale),
                ]);
            }

            if (in_array($request->payment_method, ['mpesa', 'bank_transfer'], true)) {
                $next = $request->payment_method === 'mpesa'
                    ? 'Complete the M-Pesa payment below.'
                    : 'Record the bank transfer reference below to confirm receipt.';
                return redirect()
                    ->route('sales.payment', $sale)
                    ->with('success', "Sale {$sale->invoice_number} recorded. {$next}");
            }

            return redirect()
                ->route('sales.show', $sale)
                ->with('success',
                    "Sale {$sale->invoice_number} recorded successfully.");

        } catch (\Exception $e) {
            DB::rollBack();

            // The offline-sync replay (syncPendingSales in offline-db.js)
            // posts with Accept: application/json and expects a JSON
            // {success:false, message} on failure — a redirect here made
            // fetch() follow it and try to parse an HTML page as JSON,
            // surfacing a cryptic "Unexpected token '<'" instead of the
            // real reason (e.g. stock ran out while the cashier was
            // offline), with no way for the cashier to know what to fix.
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    // â”€â”€ M-Pesa Payment Page â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function payment(Sale $sale) {
        $this->authorizeSale($sale);

        $sale->load(['items', 'customer', 'business', 'mpesaTransactions']);

        return view('sales.payment', compact('sale'));
    }

    public function customerLoyalty(Customer $customer) {
        $businessId = $this->businessId();
        if ($customer->business_id !== $businessId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $program = LoyaltyProgram::where('business_id', $businessId)
            ->where('is_active', true)->first();
        if (!$program) {
            return response()->json(['enabled' => false]);
        }
        $points   = (float) ($customer->loyalty_points ?? 0);
        $minPts   = (int) ($program->min_redemption_points ?? 0);
        $value    = $program->pointsToKsh($points);
        return response()->json([
            'enabled'            => true,
            'points'             => $points,
            'value'              => $value,
            'redemption_rate'    => $program->redemption_rate,
            'points_per_shilling'=> $program->points_per_shilling,
            'min_points'         => $minPts,
            'can_redeem'         => $points >= $minPts && $points > 0,
            'customer_name'      => $customer->name,
        ]);
    }

    public function recordPayment(Request $request, Sale $sale) {
        $this->authorizeSale($sale);

        $request->validate([
            'amount'         => 'required|numeric|min:0.01|max:' . $sale->balance_due,
            'payment_method' => 'required|in:cash,mpesa,bank,cheque,store_credit',
            'reference'      => 'required_if:payment_method,mpesa,bank,cheque|nullable|string|max:100',
        ], [
            'reference.required_if' => 'A transaction reference / M-Pesa code is required to confirm this payment.',
        ]);

        $amount = (float) $request->amount;

        // M-Pesa code checks. The code is typed by hand, so:
        //  1. the same code must not confirm several sales;
        //  2. if the system already holds that payment (STK push / QR / paybill
        //     confirmations) it must belong to this business and cover the amount;
        //  3. a code the system has never seen can't be verified — it is allowed
        //     (money can go straight to a till) but flagged, and a business can
        //     restrict unverified codes to owners and managers.
        $codeVerified = null;
        if ($request->payment_method === 'mpesa' && filled($request->reference)) {
            $ref = strtoupper(trim($request->reference));

            $used = Sale::where('business_id', $sale->business_id)
                    ->where('id', '!=', $sale->id)
                    ->whereRaw('UPPER(mpesa_reference) = ?', [$ref])->exists()
                || \App\Models\MpesaTransaction::whereRaw('UPPER(mpesa_receipt) = ?', [$ref])
                    ->whereNotNull('sale_id')->where('sale_id', '!=', $sale->id)->exists();
            if ($used) {
                return back()->withInput()->with('error', "M-Pesa code {$ref} has already been used to pay another sale.");
            }

            $known = \App\Models\MpesaTransaction::whereRaw('UPPER(mpesa_receipt) = ?', [$ref])
                ->where('status', 'COMPLETE')->first();

            // A push confirmed by Safaricom's status query has no receipt code on
            // file (only the callback carries it). If this sale has such a
            // payment and the amount fits, the typed code is that payment's
            // receipt: accept it as verified and store it on the transaction.
            if (! $known) {
                $stk = \App\Models\MpesaTransaction::where('sale_id', $sale->id)
                    ->where('status', 'COMPLETE')->whereNull('mpesa_receipt')->latest()->first();
                if ($stk && $amount <= (float) $stk->amount + 0.009) {
                    $stk->update(['mpesa_receipt' => $ref]);
                    $known = $stk;
                }
            }

            if ($known) {
                $owner = $known->business_id
                    ?: ($known->sale_id ? Sale::where('id', $known->sale_id)->value('business_id') : null);
                if ($owner && (int) $owner !== (int) $sale->business_id) {
                    return back()->withInput()->with('error', "M-Pesa code {$ref} belongs to a different business.");
                }
                if ($amount > (float) $known->amount + 0.009) {
                    return back()->withInput()->with('error',
                        "M-Pesa payment {$ref} was for KSh " . number_format($known->amount, 2)
                        . ", but you entered KSh " . number_format($amount, 2) . '.');
                }
                $codeVerified = true;
            } else {
                $codeVerified = false;
                $user = Auth::user();
                $mayOverride = $user->canActAsOwner() || $user->hasAnyRole('owner', 'manager', 'overall_manager');
                $allowed = (bool) ($sale->business->allow_unverified_mpesa_codes ?? true);
                if (! $allowed && ! $mayOverride) {
                    return back()->withInput()->with('error',
                        "M-Pesa code {$ref} isn't in the system's payment records, so it can't be confirmed. Ask a manager to record it.");
                }
            }
        }

        try {
            DB::transaction(function () use ($request, $sale, $amount, &$prevBalance, &$newBalance, &$newStatus) {
                // Re-read under a lock: two quick submits (double tap) both
                // passed the max-balance check above and both applied.
                $sale = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();
                if ($amount > (float) $sale->balance_due + 0.001) {
                    throw new \RuntimeException('This sale only has KSh ' . number_format($sale->balance_due, 2) . ' left to pay.');
                }

                // "Store credit" used to be accepted with no effect on the
                // customer's credit at all — anyone could pay with credit they
                // don't have, and the balance never went down.
                if ($request->payment_method === 'store_credit') {
                    $customer = $sale->customer_id
                        ? Customer::where('business_id', $sale->business_id)->lockForUpdate()->find($sale->customer_id)
                        : null;
                    if (! $customer) {
                        throw new \RuntimeException('Store credit can only be used for a registered customer.');
                    }
                    if ((float) $customer->credit_balance + 0.001 < $amount) {
                        throw new \RuntimeException($customer->name . ' only has KSh ' . number_format($customer->credit_balance, 2) . ' store credit.');
                    }
                    $customer->decrement('credit_balance', $amount);
                    \App\Models\CustomerCredit::create([
                        'business_id'   => $sale->business_id,
                        'customer_id'   => $customer->id,
                        'user_id'       => Auth::id(),
                        'type'          => 'adjustment',
                        'amount'        => -$amount,
                        'balance_after' => (float) $customer->fresh()->credit_balance,
                        'reference'     => $sale->invoice_number,
                        'notes'         => 'Store credit applied to sale ' . $sale->invoice_number,
                    ]);
                }

                $prevBalance = (float) $sale->balance_due;
                $newPaid     = (float) $sale->paid_amount + $amount;
                $newBalance  = max(0, (float) $sale->total_amount - $newPaid);
                $newStatus   = $newBalance <= 0 ? 'paid' : 'partial';

                // The form's method names don't all exist in the sales.payment_method
                // column ('bank' is stored as 'bank_transfer'; 'cheque' and
                // 'store_credit' aren't in older databases at all). Writing an
                // unknown value threw "Data truncated", so every Bank, Cheque and
                // Store Credit payment failed outright. Only overwrite the sale's
                // method with a value the column accepts.
                $method = $request->payment_method === 'bank' ? 'bank_transfer' : $request->payment_method;
                // Schema::getColumns() is Laravel's own portable introspection (no
                // doctrine/dbal) — the raw "SHOW COLUMNS" this replaced is MySQL-only
                // and threw outright on any other driver (e.g. the sqlite test suite).
                // Other drivers don't expose the enum's member list the same way, so
                // there's nothing to validate against there — fall through unchanged.
                $col = collect(Schema::getColumns('sales'))->firstWhere('name', 'payment_method');
                $allowed = ($col && preg_match_all("/'([^']+)'/", $col['type'] ?? '', $m)) ? $m[1] : [];
                $newMethod = (! $allowed || in_array($method, $allowed, true)) ? $method : $sale->payment_method;

                $sale->update([
                    'paid_amount'     => $newPaid,
                    'balance_due'     => $newBalance,
                    'payment_status'  => $newStatus,
                    'payment_method'  => $newMethod,
                    'mpesa_reference' => $request->reference ?? $sale->mpesa_reference,
                ]);

                // Mirror MpesaController::completeSalePayment / PesapalController — a
                // manually recorded payment against a credit sale must reduce the
                // customer's outstanding balance the same way an automated one does,
                // or their statement/credit-limit checks stay wrong forever.
                if ($sale->customer_id && $prevBalance > 0) {
                    $reduction = $prevBalance - $newBalance;
                    if ($reduction > 0) {
                        Customer::where('id', $sale->customer_id)
                            ->decrement('balance_owed', $reduction);
                    }
                }
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $sale->refresh();

        AuditLog::record('sale.payment', $sale, [
            'amount'  => $amount,
            'method'  => $request->payment_method,
            'reference'     => $request->reference,
            'code_verified' => $codeVerified,
            'balance' => $newBalance,
        ]);

        // A typed-in code the system can't match: if the business has a Daraja
        // initiator set up, ask Safaricom to confirm it (the answer arrives
        // later and updates the badge on the sale).
        if ($codeVerified === false && \App\Http\Controllers\MpesaStatusController::isAvailable($sale->business)) {
            try {
                \App\Http\Controllers\MpesaStatusController::start($sale, (string) $request->reference, $amount);
            } catch (\Throwable $e) {
                \Log::warning('M-Pesa verification could not be started: ' . $e->getMessage());
            }
        }

        // Auto-send the receipt once the sale is fully paid.
        if ($newStatus === 'paid') {
            \App\Services\ReceiptService::send($sale);
        }

        $msg = $newStatus === 'paid'
            ? "Payment recorded. Sale {$sale->invoice_number} is now fully paid."
            : "Payment of KSh " . number_format($amount, 2) . " recorded. Balance: KSh " . number_format($newBalance, 2) . ".";

        return redirect()->route('sales.show', $sale)->with('success', $msg);
    }

    // ── Public (signed) receipt — link sent to customers via WhatsApp/email ──
    public function publicReceipt(Sale $sale) {
        $sale->load(['items.product', 'customer', 'business']);
        // A link sent to the customer is never the original that was printed at the till.
        $receiptCopy = (($sale->etims_status ?? null) === 'submitted') ? 'COPY' : null;
        return view('sales.receipt', compact('sale', 'receiptCopy'));
    }

    // ── Manually (re)send the receipt to the customer ───────────────────────
    public function sendReceipt(Sale $sale) {
        abort_if($sale->business_id !== $this->businessId(), 403);

        $r = \App\Services\ReceiptService::send($sale);

        $sent = array_keys(array_filter($r));
        $msg  = $sent
            ? 'Receipt sent via ' . implode(' and ', $sent) . '.'
            : 'No receipt sent — set up WhatsApp (Settings → WhatsApp) or add a customer email.';

        return back()->with($sent ? 'success' : 'error', $msg);
    }

    // â”€â”€ View single sale â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function show(Sale $sale) {
        $this->authorizeSale($sale);
        $sale->load([
            'items.product',
            'customer',
            'user',
            'business',
            'mpesaTransactions',
            'voidRequests' => fn ($q) => $q->pending(),
        ]);
        $voidReasons = \App\Models\VoidReason::forBusiness($this->businessId())->enabled()->orderBy('name')->get();
        return view('sales.show', compact('sale', 'voidReasons'));
    }

    // â”€â”€ Print invoice (HTML) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function invoice(Sale $sale) {
        $this->authorizeSale($sale);
        $sale->load([
            'items.product',
            'customer',
            'user',
            'business',
            'mpesaTransactions',
        ]);
        return view('sales.invoice', compact('sale'));
    }

    // â”€â”€ Download PDF invoice â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function downloadPdf(Sale $sale) {
        $this->authorizeSale($sale);
        $sale->load([
            'items.product',
            'customer',
            'user',
            'business',
            'mpesaTransactions',
        ]);

        $pdf = Pdf::loadView('sales.invoice-pdf', compact('sale'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('invoice-' . $sale->invoice_number . '.pdf');
    }

    // â”€â”€ Cancel a sale â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Owner/manager keep this direct action (no approval queue needed for
    // the person who'd be the approver anyway), but a reason is now required
    // so every cancellation — direct or cashier-requested — lands in the
    // same void_requests audit trail. This one is self-approved: requested_by
    // and reviewed_by are the same user, recorded immediately.
    public function cancel(Request $request, Sale $sale, SaleService $saleService) {
        $this->authorizeSale($sale);

        $request->validate([
            'void_reason_id' => 'required|integer|exists:void_reasons,id',
            'note'           => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $sale->load('items');
            $saleService->cancelSale($sale);

            \App\Models\VoidRequest::create([
                'business_id'    => $this->businessId(),
                'sale_id'        => $sale->id,
                'void_reason_id' => $request->void_reason_id,
                'note'           => $request->note,
                'status'         => 'approved',
                'requested_by'   => Auth::id(),
                'reviewed_by'    => Auth::id(),
                'reviewed_at'    => now(),
            ]);

            DB::commit();

            return redirect()
                ->route('sales.index')
                ->with('success',
                    "Sale {$sale->invoice_number} has been cancelled.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    // â”€â”€ Email invoice to customer â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function emailInvoice(Sale $sale) {
        $this->authorizeSale($sale);

        if (!$sale->customer || !$sale->customer->email) {
            return back()->with('error', 'This sale has no customer email address on record.');
        }

        $sale->load(['items.product', 'customer', 'business']);

        // A mail-server problem used to surface as a bare 500 error page.
        try {
            \Illuminate\Support\Facades\Mail::to($sale->customer->email)
                ->send(new \App\Mail\InvoiceEmail($sale));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Invoice email failed', ['sale' => $sale->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'The invoice could not be emailed right now. Please try again shortly, or download the PDF and send it yourself.');
        }

        return back()->with('success', "Invoice emailed to {$sale->customer->email}.");
    }

    // â”€â”€ Export sales as CSV â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function export(Request $request) {
        $businessId = $this->businessId();
        $query = Sale::with('customer')->forBusiness($businessId);

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        $sales = $query->orderBy('created_at', 'desc')->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="sales_' . now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($sales) {
            $handle = fopen('php://output', 'w');
            \App\Support\Csv::put($handle, ['Invoice #', 'Date', 'Customer', 'Subtotal', 'Discount', 'Tax', 'Total', 'Paid', 'Balance', 'Payment Status', 'Sale Status', 'Payment Method']);
            foreach ($sales as $s) {
                \App\Support\Csv::put($handle, [
                    $s->invoice_number,
                    $s->created_at->format('Y-m-d'),
                    $s->customer->name ?? 'Walk-in',
                    $s->subtotal,
                    $s->discount_amount,
                    $s->tax_amount,
                    $s->total_amount,
                    $s->paid_amount,
                    $s->balance_due,
                    $s->payment_status,
                    $s->sale_status,
                    $s->payment_method ?? '',
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    // â”€â”€ Authorization helper â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    private function authorizeSale(Sale $sale): void {
        if ($sale->business_id !== $this->businessId()) {
            abort(403, 'Unauthorized action.');
        }
    }

    // -- Thermal Receipt --
    public function receipt(Sale $sale, Request $request) {
        $this->authorizeSale($sale);
        $sale->load(['items.product', 'customer', 'user', 'business', 'tableOrder.table']);

        // KRA's rules allow one ORIGINAL receipt; every reprint must say COPY.
        // Only relevant once the receipt carries eTIMS data.
        $receiptCopy = null;
        if (($sale->etims_status ?? null) === 'submitted') {
            $event = 'sale.receipt.printed';
            $already = AuditLog::where('event', $event)
                ->where('subject_type', Sale::class)->where('subject_id', $sale->id)->exists();
            $receiptCopy = $already ? 'COPY' : 'ORIGINAL';
            if (! $already) {
                AuditLog::record($event, $sale, ['printed_by' => Auth::id()]);
            }
        }

        // After paying at a table, the receipt sends the cashier back to the floor
        // plan. Only this one fixed destination is accepted, never a URL from the request.
        $returnUrl = match ($request->query('return')) {
            'floor' => route('tables.floor'),
            // more guests still to pay on the same table
            'order' => $sale->table_order_id ? route('tables.orders.show', $sale->table_order_id) : null,
            default => null,
        };

        return view('sales.receipt', compact('sale', 'receiptCopy', 'returnUrl'));
    }

    // -- Award loyalty points after a sale --
    private function awardLoyaltyPoints(Sale $sale, int $businessId): void {
        if (!$sale->customer_id) return;

        $program = LoyaltyProgram::where('business_id', $businessId)
            ->where('is_active', true)
            ->first();

        if (!$program) return;

        $points   = round($sale->total_amount * $program->points_per_shilling, 2);
        $customer = $sale->customer ?? $sale->load('customer')->customer;
        if (!$customer) return;

        $newBalance = $customer->loyalty_points + $points;
        $customer->update(['loyalty_points' => $newBalance]);

        LoyaltyTransaction::create([
            'business_id'   => $businessId,
            'customer_id'   => $customer->id,
            'user_id'       => Auth::id(),
            'sale_id'       => $sale->id,
            'type'          => 'earn',
            'points'        => $points,
            'balance_after' => $newBalance,
            'description'   => "Earned from sale #{$sale->invoice_number}",
        ]);
    }
}
