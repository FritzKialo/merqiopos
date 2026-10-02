<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Customer;
use App\Models\MpesaTransaction;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Services\MpesaService;
use App\Services\PromoCodeService;
use App\Services\SaleService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MpesaController extends Controller
{

    // ── Sale STK Push ──────────────────────────────────────────────────────

    /**
     * Initiate M-Pesa STK Push for a sale payment.
     */
    public function initiate(Request $request)
    {
        $request->validate([
            'sale_id' => 'required|exists:sales,id',
            'phone'   => 'required|string|min:9|max:13',
        ]);

        $sale = Sale::findOrFail($request->sale_id);

        if ($sale->business_id !== Auth::user()->currentBusiness()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        if ($sale->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'This sale is already fully paid.',
            ], 422);
        }

        $amount   = $sale->balance_due > 0 ? $sale->balance_due : $sale->total_amount;
        $business = Auth::user()->currentBusiness();

        // Each shop owner receives their own customer payments using their
        // own Daraja credentials. Block the request if not yet configured.
        if (! $business->hasMpesaConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'M-Pesa is not set up for your business yet. '
                           . 'Go to Settings → M-Pesa to add your Daraja credentials.',
            ], 422);
        }

        try {
            $mpesa    = new MpesaService($business->mpesaCredentials());
            $response = $mpesa->stkPush(
                $request->phone,
                $amount,
                $sale->invoice_number,
                'Sale Payment'
            );

            $transaction = MpesaTransaction::create([
                'sale_id'     => $sale->id,
                'type'        => 'sale',
                'phone'       => $mpesa->formatPhone($request->phone),
                'amount'      => $amount,
                'checkout_id' => $response['CheckoutRequestID'],
                'api_ref'     => $response['MerchantRequestID'] ?? null,
                'status'      => 'PENDING',
            ]);

            return response()->json([
                'success'        => true,
                'message'        => 'STK Push sent. Ask the customer to check their phone.',
                'transaction_id' => $transaction->id,
            ]);

        } catch (\Exception $e) {
            Log::error('STK Push initiate failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'We could not send the M-Pesa prompt right now. Please check your Daraja credentials in Settings → M-Pesa, or try again shortly.',
            ], 400);
        }
    }

    // ── Sale QR Code ───────────────────────────────────────────────────────

    /**
     * Generate a scannable M-Pesa QR code for a sale's balance due.
     * Unlike STK Push, no customer phone number is needed here — the
     * customer scans it themselves. Settles through the same C2B
     * confirmation webhook already used for Paybill/Till payments
     * (see c2bConfirm below), matched by the invoice number encoded
     * as the QR's reference.
     */
    public function generateQr(Request $request)
    {
        $request->validate([
            'sale_id' => 'required|exists:sales,id',
        ]);

        $sale = Sale::findOrFail($request->sale_id);

        if ($sale->business_id !== Auth::user()->currentBusiness()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        if ($sale->payment_status === 'paid') {
            return response()->json(['success' => false, 'message' => 'This sale is already fully paid.'], 422);
        }

        $business = Auth::user()->currentBusiness();

        if (! $business->hasMpesaConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'M-Pesa is not set up for your business yet. '
                           . 'Go to Settings → M-Pesa to add your Daraja credentials.',
            ], 422);
        }

        $amount = $sale->balance_due > 0 ? $sale->balance_due : $sale->total_amount;

        try {
            $mpesa = new MpesaService($business->mpesaCredentials());
            $qr    = $mpesa->generateQrCode($amount, $sale->invoice_number, $business->name);

            return response()->json([
                'success' => true,
                'qr_code' => $qr,
                'amount'  => $amount,
            ]);
        } catch (\Exception $e) {
            Log::error('QR generation failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'We could not generate the M-Pesa QR code right now. Please check your Daraja credentials in Settings → M-Pesa, or try again shortly.'], 400);
        }
    }

    // ── Subscription STK Push ──────────────────────────────────────────────

    /**
     * Initiate M-Pesa STK Push for a subscription payment.
     * api_ref stores "plan:business_id" for callback lookup.
     */
    public function subscribe(Request $request, PromoCodeService $promoCodeService)
    {
        $request->validate([
            'plan'       => 'required|in:solo,growth,enterprise',
            'phone'      => 'required|string|min:9|max:13',
            'promo_code' => 'nullable|string|max:40',
        ]);

        if (!Auth::user()->isOwner()) {
            return response()->json([
                'success' => false,
                'message' => 'Only the organization owner can manage subscriptions.',
            ], 403);
        }

        $organization = Auth::user()->organization;
        $plan         = config('plans.org.' . $request->plan);
        $amount       = $plan['price'];
        $promoCode    = null;

        if ($request->filled('promo_code')) {
            $result = $promoCodeService->validate($request->promo_code, $request->plan, $organization, (float) $amount);

            if (! $result['valid']) {
                return response()->json(['success' => false, 'message' => $result['message']], 422);
            }

            $promoCode = strtoupper(trim($request->promo_code));
            $amount    = $result['discountedAmount'];
        }

        try {
            // Subscription payments always go to the PLATFORM shortcode (.env),
            // never to the shop owner's shortcode.
            $mpesa    = new MpesaService(); // no credentials = uses .env
            $response = $mpesa->stkPush(
                $request->phone,
                $amount,
                'SME-SUB',
                'Subscription'
            );

            // Encode plan + organization_id in api_ref so the callback can use it
            $apiRef = $request->plan . ':' . Auth::user()->organization->id;

            $transaction = MpesaTransaction::create([
                'sale_id'     => null,
                'type'        => 'subscription',
                'phone'       => $mpesa->formatPhone($request->phone),
                'amount'      => $amount,
                'checkout_id' => $response['CheckoutRequestID'],
                'api_ref'     => $apiRef,
                'promo_code'  => $promoCode,
                'status'      => 'PENDING',
            ]);

            return response()->json([
                'success'        => true,
                'message'        => 'STK Push sent. Complete payment on your phone.',
                'transaction_id' => $transaction->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Subscription STK Push failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'We could not send the M-Pesa prompt right now. Please try again shortly, or pay with Card / Paystack instead.',
            ], 400);
        }
    }

    // ── Daraja Callback ────────────────────────────────────────────────────

    /**
     * Handle Daraja STK Push callback.
     * No auth middleware — CSRF exempt via bootstrap/app.php.
     */
    public function callback(Request $request)
    {
        $payload  = $request->all();
        $callback = $payload['Body']['stkCallback'] ?? null;

        Log::info('M-Pesa Callback received', $payload);

        if (!$callback) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        $checkoutId = $callback['CheckoutRequestID'] ?? null;
        $resultCode = (int) ($callback['ResultCode'] ?? 1);

        $transaction = MpesaTransaction::where('checkout_id', $checkoutId)->first();

        if (!$transaction) {
            // Bumped from warning() — production's LOG_LEVEL only records
            // error and above, so this and every other "money moved but we
            // can't match it to anything" case below was completely
            // invisible (same root cause as the receipt-email bug: see
            // ReceiptService.php). This one specifically means Safaricom
            // confirmed a real STK payment that has no matching record here.
            Log::error('M-Pesa callback: transaction not found', [
                'checkout_id' => $checkoutId,
            ]);
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        // Idempotency — Daraja may retry the callback on network failure.
        // If already processed, acknowledge without re-applying business logic.
        // A FAILED status set by the status-poll's STK query (not by a callback)
        // is not final: the customer may simply have entered their PIN after the
        // query ran, so a genuine success callback must still be applied. Only a
        // COMPLETE payment, or a FAILED one that this callback also reports as
        // failed, is a true duplicate.
        $alreadyHandled = $transaction->status === 'COMPLETE'
            || ($transaction->status === 'FAILED' && $resultCode !== 0);

        if ($alreadyHandled) {
            // The STK-query fallback (status polling) can mark a payment
            // COMPLETE before the real callback arrives, and it has no way
            // to learn the M-Pesa receipt number. When the genuine callback
            // does turn up late, record the receipt it carries — without
            // re-applying the payment — instead of discarding it for good.
            if ($transaction->status === 'COMPLETE' && $resultCode === 0 && empty($transaction->mpesa_receipt)) {
                $late = collect($callback['CallbackMetadata']['Item'] ?? [])->pluck('Value', 'Name');
                $lateReceipt = $late['MpesaReceiptNumber'] ?? null;
                if ($lateReceipt) {
                    $transaction->update(['mpesa_receipt' => $lateReceipt, 'payload' => $payload]);
                    if ($transaction->sale_id) {
                        Sale::where('id', $transaction->sale_id)->whereNull('mpesa_reference')
                            ->update(['mpesa_reference' => $lateReceipt]);
                    }
                }
            }

            Log::info('M-Pesa callback: already processed, skipping', [
                'checkout_id' => $checkoutId,
                'status'      => $transaction->status,
            ]);
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        DB::beginTransaction();
        try {
            if ($resultCode === 0) {
                // Payment successful — extract metadata
                $meta    = collect(
                    $callback['CallbackMetadata']['Item'] ?? []
                )->pluck('Value', 'Name');

                $receipt = $meta['MpesaReceiptNumber'] ?? null;
                $amount  = (float) ($meta['Amount'] ?? $transaction->amount);

                $transaction->update([
                    'status'        => 'COMPLETE',
                    'mpesa_receipt' => $receipt,
                    'payload'       => $payload,
                ]);

                if ($transaction->type === 'sale') {
                    $this->completeSalePayment($transaction, $amount, $receipt);
                } else {
                    app(SubscriptionService::class)
                        ->activateFromPayment($transaction, $amount, $receipt);
                }

            } else {
                $transaction->update([
                    'status'  => 'FAILED',
                    'payload' => $payload,
                ]);
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('M-Pesa callback processing error', [
                'error'       => $e->getMessage(),
                'checkout_id' => $checkoutId,
            ]);
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    // ── Status Polling ─────────────────────────────────────────────────────

    /**
     * Check sale payment status (AJAX polling).
     */
    public function status(Request $request)
    {
        $request->validate([
            'sale_id' => 'required|exists:sales,id',
        ]);

        $sale = Sale::find($request->sale_id);

        // If the sale is already marked paid by any means (C2B, STK, manual), report COMPLETE
        if ($sale && $sale->payment_status === 'paid') {
            $receipt = MpesaTransaction::where('sale_id', $sale->id)
                ->whereNotNull('mpesa_receipt')
                ->latest()
                ->value('mpesa_receipt');

            return response()->json([
                'status'  => 'COMPLETE',
                'receipt' => $receipt,
            ]);
        }

        // Otherwise check for a completed M-Pesa transaction (STK or C2B)
        $transaction = MpesaTransaction::where('sale_id', $request->sale_id)
            ->whereIn('type', ['sale', 'c2b'])
            ->latest()
            ->first();

        if (!$transaction) {
            return response()->json(['status' => 'NOT_FOUND']);
        }

        // A callback isn't guaranteed to ever arrive — confirmed on this
        // exact install: a real STK push, completed by the customer on
        // their phone, whose callback Safaricom's sandbox simply never
        // sent (nothing rejected, nothing logged, it just never came).
        // Rather than leave the frontend's "Waiting for payment…" poll
        // spinning forever on a callback that may not be coming, actively
        // ask Safaricom directly once the transaction has had a moment to
        // actually process (querying immediately after initiating returns
        // its own "still processing" error, so this only kicks in for a
        // still-PENDING checkout-type transaction, not the very first poll).
        // A payment the status check itself marked FAILED (no callback payload)
        // is re-checked for a while: earlier versions read Safaricom's
        // "still under processing" (4999) as a failure, and a customer can also
        // finish paying after the first check.
        $recheck = $transaction->status === 'FAILED'
            && empty($transaction->payload)
            && $transaction->created_at->gt(now()->subMinutes(30));

        if (($transaction->status === 'PENDING' || $recheck)
            && $transaction->type === 'sale'
            && $transaction->checkout_id
            && $transaction->created_at->diffInSeconds(now()) >= 8) {
            $this->reconcileViaStkQuery($transaction);
            $transaction->refresh();
        }

        return response()->json([
            'status'  => $transaction->status,
            'receipt' => $transaction->mpesa_receipt,
        ]);
    }

    /**
     * Check subscription payment status (AJAX polling).
     */
    public function subscriptionStatus(Request $request)
    {
        $request->validate([
            'transaction_id' => 'required|integer',
        ]);

        $transaction = MpesaTransaction::where('id', $request->transaction_id)
            ->where('type', 'subscription')
            ->first();

        if (!$transaction) {
            return response()->json(['status' => 'NOT_FOUND']);
        }

        // Verify it belongs to the authenticated user's organization
        // api_ref = "plan:organization_id"
        [, $orgId] = explode(':', $transaction->api_ref . ':', 2);

        if ((int) $orgId !== Auth::user()->organization?->id) {
            return response()->json(['status' => 'NOT_FOUND']);
        }

        return response()->json([
            'status'  => $transaction->status,
            'receipt' => $transaction->mpesa_receipt,
        ]);
    }

    // ── Private Helpers ────────────────────────────────────────────────────

    // ── C2B (Customer-Initiated via Paybill / Till) ────────────────────────

    /**
     * Register C2B URLs for a business's shortcode.
     * Called from Settings → M-Pesa by the business owner.
     */
    public function registerC2B(Request $request)
    {
        $business = Auth::user()->currentBusiness();

        if (!$business->hasMpesaConfigured()) {
            return back()->with('error', 'Configure your M-Pesa credentials first before registering C2B URLs.');
        }

        try {
            $mpesa = new MpesaService($business->mpesaCredentials());

            $confirmUrl  = route('mpesa.c2b.confirm');
            $validateUrl = route('mpesa.c2b.validate');

            $mpesa->registerC2BUrls($confirmUrl, $validateUrl);

            $business->update(['mpesa_c2b_registered' => true]);

            return back()->with('success', 'C2B URLs registered with Safaricom. Customers can now pay via Paybill/Till and the system will auto-detect their payment.');
        } catch (\Exception $e) {
            Log::error('C2B URL registration failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'Registration failed: ' . $e->getMessage());
        }
    }

    /**
     * C2B Validation URL — Safaricom asks "accept this payment?".
     * Called before the transaction is processed. Must respond within 8 seconds.
     * No auth/CSRF — called by Safaricom servers.
     */
    public function c2bValidate(Request $request)
    {
        $payload  = $request->all();
        $shortcode = $payload['BusinessShortCode'] ?? null;

        Log::info('M-Pesa C2B Validate', $payload);

        // Accept all payments — let the confirmation handler do matching.
        // Reject only if the shortcode doesn't belong to any known business.
        $known = Business::where('mpesa_shortcode', $shortcode)->exists();

        if (!$known) {
            // Bumped from warning() — see the note on the callback() method
            // above. A real C2B payment attempt against a shortcode this
            // app doesn't recognize at all is worth knowing about.
            Log::error('M-Pesa C2B Validate: unknown shortcode', ['shortcode' => $shortcode]);
            return response()->json([
                'ResultCode' => 'C2B00012',
                'ResultDesc' => 'Invalid account',
            ]);
        }

        return response()->json([
            'ResultCode' => '0',
            'ResultDesc' => 'Accepted',
        ]);
    }

    /**
     * C2B Confirmation URL — payment is done, record it.
     * No auth/CSRF — called by Safaricom servers.
     *
     * Payload from Safaricom:
     *   TransID, TransAmount, MSISDN, BillRefNumber, BusinessShortCode, FirstName, LastName
     */
    public function c2bConfirm(Request $request)
    {
        $payload  = $request->all();

        Log::info('M-Pesa C2B Confirm', $payload);

        $transId   = $payload['TransID']            ?? null;
        $amount    = (float) ($payload['TransAmount']     ?? 0);
        $phone     = $payload['MSISDN']             ?? '';
        $billRef   = trim($payload['BillRefNumber'] ?? '');
        $shortcode = $payload['BusinessShortCode']  ?? null;

        // Idempotency — Daraja may retry
        if (MpesaTransaction::where('mpesa_receipt', $transId)->exists()) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        // Find the business by shortcode
        $business = Business::where('mpesa_shortcode', $shortcode)->first();

        if (!$business) {
            // Bumped from warning() — see the note on callback() above.
            // This means real money already moved (Safaricom is confirming
            // a completed payment) but no business owns this shortcode.
            Log::error('M-Pesa C2B Confirm: no business for shortcode', [
                'shortcode' => $shortcode, 'transId' => $transId,
            ]);
            // Still acknowledge — money is already moved
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        DB::beginTransaction();
        try {
            // Try to match a pending sale by invoice number (Paybill account reference)
            $sale = null;
            if ($billRef) {
                $sale = Sale::where('business_id', $business->id)
                    ->where('invoice_number', $billRef)
                    ->whereIn('payment_status', ['unpaid', 'partial'])
                    ->first();
            }

            // Fallback: match by customer phone + closest unpaid amount
            if (!$sale) {
                $normalizedPhone = $this->normalizePhone($phone);
                $sale = Sale::where('business_id', $business->id)
                    ->whereIn('payment_status', ['unpaid', 'partial'])
                    ->whereHas('customer', fn ($q) => $q->where('phone', 'like', '%' . substr($normalizedPhone, -9)))
                    ->where('balance_due', '<=', $amount + 1) // allow KSh 1 rounding
                    ->orderByDesc('created_at')
                    ->first();
            }

            // No POS sale matched — this payment may be for an online-shop
            // order paid via its QR code instead of STK Push (STK completes
            // through a separate callback; a QR-scanned payment settles as
            // an ordinary C2B payment like this one, so it never reaches
            // that callback and has to be matched here instead). Same two
            // strategies as above: reference first, then phone + amount.
            $order = null;
            if (!$sale) {
                if ($billRef) {
                    $order = OnlineOrder::where('business_id', $business->id)
                        ->where('reference', $billRef)
                        ->where('status', 'pending')
                        ->first();
                }
                if (!$order) {
                    $normalizedPhone = $this->normalizePhone($phone);
                    $order = OnlineOrder::where('business_id', $business->id)
                        ->where('status', 'pending')
                        ->where('customer_phone', 'like', '%' . substr($normalizedPhone, -9))
                        ->where('total', '<=', $amount + 1)
                        ->orderByDesc('created_at')
                        ->first();
                }
            }

            // Record the transaction (even if unmatched — for reconciliation)
            $transaction = MpesaTransaction::create([
                'business_id'     => $business->id,
                'sale_id'         => $sale?->id,
                'type'            => 'c2b',
                'phone'           => $phone,
                'amount'          => $amount,
                'mpesa_receipt'   => $transId,
                'bill_ref_number' => $billRef,
                'shortcode'       => $shortcode,
                'status'          => 'COMPLETE',
                'payload'         => $payload,
            ]);

            if ($sale) {
                $this->completeSalePayment($transaction, $amount, $transId);
                Log::info('M-Pesa C2B matched to sale', [
                    'invoice' => $sale->invoice_number,
                    'amount'  => $amount,
                    'transId' => $transId,
                ]);
            } elseif ($order) {
                $this->completeOnlineOrderPayment($order, $transId);
                Log::info('M-Pesa C2B matched to online order', [
                    'reference' => $order->reference,
                    'amount'    => $amount,
                    'transId'   => $transId,
                ]);
            } else {
                // Bumped from warning() — see the note on callback() above.
                // Money already moved and got recorded as a transaction, but
                // nothing here connects it to an actual sale or order — this
                // is the exact "money received, unreconciled" case.
                Log::error('M-Pesa C2B Confirm: no matching sale or order', [
                    'billRef'  => $billRef,
                    'phone'    => $phone,
                    'amount'   => $amount,
                    'transId'  => $transId,
                    'business' => $business->id,
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('M-Pesa C2B confirm processing error', ['error' => $e->getMessage()]);
        }

        // Always acknowledge — Safaricom has already moved the money
        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (str_starts_with($phone, '0')) return '254' . substr($phone, 1);
        if (str_starts_with($phone, '+')) return substr($phone, 1);
        return $phone;
    }

    // ── Private Helpers ────────────────────────────────────────────────────

    /**
     * Actively reconcile a still-PENDING sale transaction against Safaricom
     * directly (STK Push Query) instead of only ever waiting on their
     * callback — see the comment in status() for why this exists. Mirrors
     * exactly what the callback handler above does on success/failure, so
     * a transaction resolved this way ends up in the identical state one
     * resolved by a normal callback would.
     */
    private function reconcileViaStkQuery(MpesaTransaction $transaction): void
    {
        try {
            $sale = Sale::find($transaction->sale_id);
            if (!$sale) return;

            $mpesa  = new MpesaService($sale->business->mpesaCredentials());
            $result = $mpesa->queryStkStatus($transaction->checkout_id);

            if ($result['stillProcessing']) {
                return;
            }

            $current = $transaction->fresh();
            if ($current->status === 'COMPLETE' || ($current->status === 'FAILED' && !empty($current->payload))) {
                // A callback arrived while this query was in flight — don't
                // clobber whatever it already resolved.
                return;
            }

            if ($result['resultCode'] === 0) {
                // The Query API confirms success but — unlike the callback's
                // CallbackMetadata — never includes the M-Pesa receipt
                // number. Marked complete now so the customer isn't stuck
                // waiting; if the real callback arrives later (it still
                // might, just late), its own idempotency check already
                // no-ops on an already-COMPLETE transaction, so a genuine
                // receipt would need a manual backfill at that point rather
                // than being lost — an acceptable trade-off against leaving
                // a confirmed payment stuck showing as pending indefinitely.
                $transaction->update(['status' => 'COMPLETE']);
                $this->completeSalePayment($transaction, (float) $transaction->amount, null);
            } else {
                // Logged at error level (production only records errors) so a
                // "payment failed but money left my phone" report can be traced.
                Log::error('M-Pesa STK query marked payment FAILED', [
                    'transaction_id' => $transaction->id,
                    'checkout_id'    => $transaction->checkout_id,
                    'result_code'    => $result['resultCode'],
                    'result_desc'    => $result['resultDesc'] ?? null,
                ]);
                $transaction->update(['status' => 'FAILED']);
            }
        } catch (\Throwable $e) {
            // A failed reconciliation attempt (Safaricom bot-protection,
            // timeout, etc.) should never break the poll itself — it just
            // means this particular check didn't resolve anything, and the
            // next poll (or the callback, if it ever arrives) gets another
            // chance.
            Log::warning('M-Pesa STK query reconciliation failed', [
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    private function completeSalePayment(
        MpesaTransaction $transaction,
        float $amount,
        ?string $receipt
    ): void {
        $sale = Sale::findOrFail($transaction->sale_id);

        $prevBalance = $sale->balance_due;
        $newPaid     = min($sale->total_amount, $sale->paid_amount + $amount);
        $newBalance  = max(0, $sale->total_amount - $newPaid);

        if ($newPaid <= 0) {
            $paymentStatus = 'unpaid';
        } elseif ($newBalance > 0) {
            $paymentStatus = 'partial';
        } else {
            $paymentStatus = 'paid';
        }

        $sale->update([
            'paid_amount'     => $newPaid,
            'balance_due'     => $newBalance,
            'payment_status'  => $paymentStatus,
            'mpesa_reference' => $receipt,
        ]);

        // Reduce customer's outstanding credit if applicable
        if ($sale->customer_id && $prevBalance > 0) {
            $reduction = $prevBalance - $newBalance;
            if ($reduction > 0) {
                Customer::where('id', $sale->customer_id)
                    ->decrement('balance_owed', $reduction);
            }
        }

        // Once fully paid, send the receipt to the M-Pesa number that paid.
        if ($paymentStatus === 'paid') {
            \App\Services\ReceiptService::send($sale, $transaction->phone);
        }
    }

    /**
     * Same completion steps StoreController::mpesaCallback() runs for an
     * STK-paid online order — stock decrement + a real Sale record — kept
     * in one place so a QR-paid order (arriving here via C2B, not STK)
     * ends up in an identical state either way.
     */
    private function completeOnlineOrderPayment(OnlineOrder $order, ?string $receipt): void
    {
        $order->update(['status' => 'paid', 'payment_confirmed_at' => now()]);

        foreach ($order->items as $item) {
            // Skip the bundle summary line — see Shop\StoreController::
            // markOnlineOrderPaid() for why (mirrors the fix already made
            // to SaleService for the exact same POS-side bug).
            if ($item->is_bundle_summary) {
                continue;
            }
            \App\Models\ProductBatch::consume((int) $item->product_id, $item->variant_id ?: null, (float) $item->quantity);
            if ($item->variant_id) {
                ProductVariant::where('id', $item->variant_id)->decrement('stock_qty', $item->quantity);
            } else {
                Product::where('id', $item->product_id)->decrement('stock_qty', $item->quantity);
            }
        }

        // Same timing as the stock decrement above — only recorded once
        // actually paid, so an abandoned/cancelled pending order never
        // permanently burns a limited-use coupon. Mirrors
        // Shop\StoreController::recordCouponUsage() exactly.
        if ($order->coupon_code) {
            $coupon = Coupon::where('business_id', $order->business_id)
                ->where('code', $order->coupon_code)
                ->first();
            if ($coupon) {
                $coupon->increment('used_count');
                CouponUsage::create([
                    'coupon_id'        => $coupon->id,
                    'business_id'      => $order->business_id,
                    'online_order_id'  => $order->id,
                    'discount_applied' => $order->coupon_discount_amount,
                    'used_at'          => now(),
                ]);
            }
        }

        (new SaleService())->createSaleFromOnlineOrder($order->fresh());
    }

}

