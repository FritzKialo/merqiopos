<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PesapalTransaction;
use App\Models\Sale;
use App\Services\PesapalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PesapalController extends Controller
{
    // ── Initiate Checkout ──────────────────────────────────────────────────

    /**
     * Redirect the customer/cashier to the Pesapal hosted payment page.
     */
    public function initiate(Request $request)
    {
        $request->validate([
            'sale_id' => 'required|exists:sales,id',
        ]);

        $sale     = Sale::with('customer')->findOrFail($request->sale_id);
        $business = Auth::user()->currentBusiness();

        if ($sale->business_id !== $business->id) {
            return back()->with('error', 'Unauthorized.');
        }

        if ($sale->payment_status === 'paid') {
            return back()->with('error', 'This sale is already paid.');
        }

        if (!$business->hasPesapalConfigured()) {
            return back()->with('error', 'Pesapal is not set up. Go to Settings → Pesapal.');
        }

        $amount    = $sale->balance_due > 0 ? $sale->balance_due : $sale->total_amount;
        $reference = 'PSP-' . strtoupper(Str::random(10));

        $customer  = $sale->customer;
        $billing   = [
            'email'      => $customer?->email ?? Auth::user()->email,
            'phone'      => $customer?->phone ?? '',
            'first_name' => $customer ? explode(' ', $customer->name)[0] : 'Customer',
            'last_name'  => $customer ? (explode(' ', $customer->name)[1] ?? '') : '',
        ];

        try {
            $pesapal = new PesapalService($business->pesapalCredentials());
            $result  = $pesapal->submitOrder(
                merchantReference: $reference,
                amount: $amount,
                ipnId: $business->pesapal_ipn_id,
                description: 'Payment for ' . $sale->invoice_number,
                billing: $billing,
            );

            PesapalTransaction::create([
                'business_id'        => $business->id,
                'sale_id'            => $sale->id,
                'merchant_reference' => $reference,
                'order_tracking_id'  => $result['order_tracking_id'],
                'amount'             => $amount,
                'status'             => 'PENDING',
            ]);

            return redirect($result['redirect_url']);

        } catch (\Exception $e) {
            Log::error('Pesapal initiate failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'Card payment is not available right now. Please try another payment method, or contact the business if this continues.');
        }
    }

    // ── Callback (redirect after hosted checkout) ──────────────────────────

    /**
     * Pesapal redirects here after the customer finishes on their page.
     * We verify the status immediately.
     */
    public function callback(Request $request)
    {
        $orderTrackingId  = $request->query('OrderTrackingId');
        $merchantRef      = $request->query('OrderMerchantReference');

        if (!$orderTrackingId || !$merchantRef) {
            return redirect()->route('sales.index')
                ->with('error', 'Invalid payment callback.');
        }

        $pending = PesapalTransaction::where('merchant_reference', $merchantRef)
            ->where('status', 'PENDING')
            ->first();

        if (!$pending) {
            // Already handled by IPN, or unknown
            $done = PesapalTransaction::where('merchant_reference', $merchantRef)
                ->where('status', 'COMPLETE')
                ->first();
            if ($done) {
                return redirect()->route('sales.show', $done->sale_id)
                    ->with('success', 'Payment already confirmed.');
            }
            return redirect()->route('sales.index')
                ->with('error', 'Payment record not found. Contact support if charged.');
        }

        try {
            $this->verifyAndComplete($pending, $orderTrackingId);

            $sale = Sale::find($pending->sale_id);
            return redirect()->route('sales.show', $sale)
                ->with('success', 'Card payment confirmed! ' . $sale->invoice_number . ' is now paid.');

        } catch (\Exception $e) {
            Log::error('Pesapal callback failed', ['error' => $e->getMessage()]);
            return redirect()->route('sales.payment', $pending->sale_id)
                ->with('error', 'We could not confirm your payment. If you were charged, contact the business and they will confirm it manually.');
        }
    }

    // ── IPN (server-to-server instant notification) ────────────────────────

    /**
     * Pesapal POSTs here immediately after a payment, independent of the browser redirect.
     * No auth/CSRF — exempt via bootstrap/app.php.
     */
    public function ipn(Request $request)
    {
        $orderTrackingId = $request->query('OrderTrackingId')
            ?? $request->input('OrderTrackingId');
        $merchantRef     = $request->query('OrderMerchantReference')
            ?? $request->input('OrderMerchantReference');

        Log::info('Pesapal IPN received', [
            'tracking_id' => $orderTrackingId,
            'ref'         => $merchantRef,
        ]);

        if (!$orderTrackingId || !$merchantRef) {
            return response()->json(['status' => 'error', 'message' => 'Missing parameters'], 400);
        }

        $transaction = PesapalTransaction::where('merchant_reference', $merchantRef)->first();

        if (!$transaction) {
            // Bumped from warning() — invisible in production, whose
            // LOG_LEVEL only records error and above (see ReceiptService.php
            // and MpesaController.php's equivalent case). Pesapal is
            // confirming a real payment with nothing here to match it to.
            Log::error('Pesapal IPN: transaction not found', ['ref' => $merchantRef]);
            return response()->json(['orderNotificationType' => 'IPNCHANGE', 'orderTrackingId' => $orderTrackingId, 'orderMerchantReference' => $merchantRef, 'status' => '200']);
        }

        if ($transaction->status === 'COMPLETE') {
            return response()->json(['orderNotificationType' => 'IPNCHANGE', 'orderTrackingId' => $orderTrackingId, 'orderMerchantReference' => $merchantRef, 'status' => '200']);
        }

        try {
            $this->verifyAndComplete($transaction, $orderTrackingId);
        } catch (\Exception $e) {
            Log::error('Pesapal IPN processing error', ['error' => $e->getMessage()]);
        }

        // Pesapal expects this specific response format to stop retrying
        return response()->json([
            'orderNotificationType'  => 'IPNCHANGE',
            'orderTrackingId'        => $orderTrackingId,
            'orderMerchantReference' => $merchantRef,
            'status'                 => '200',
        ]);
    }

    // ── Status Poll ────────────────────────────────────────────────────────

    /**
     * AJAX endpoint to check payment status (for background polling on payment page).
     */
    public function status(Request $request)
    {
        $request->validate(['sale_id' => 'required|exists:sales,id']);

        $sale = Sale::find($request->sale_id);

        if ($sale->payment_status === 'paid') {
            return response()->json(['status' => 'COMPLETE']);
        }

        $tx = PesapalTransaction::where('sale_id', $request->sale_id)
            ->latest()->first();

        return response()->json([
            'status'  => $tx?->status ?? 'NOT_FOUND',
            'receipt' => $tx?->confirmation_code,
        ]);
    }

    // ── Settings: Register IPN ─────────────────────────────────────────────

    /**
     * Register IPN URL with Pesapal for this business.
     * Called from Settings → Pesapal.
     */
    public function registerIpn(Request $request)
    {
        $business = Auth::user()->currentBusiness();

        if (!$business->hasPesapalCredentials()) {
            return back()->with('error', 'Save your Pesapal credentials first.');
        }

        try {
            $pesapal = new PesapalService($business->pesapalCredentials());
            $ipnId   = $pesapal->registerIpn(route('pesapal.ipn'));

            $business->update(['pesapal_ipn_id' => $ipnId]);

            return back()->with('success', 'IPN registered with Pesapal. Card payments will now auto-confirm.');
        } catch (\Exception $e) {
            Log::error('Pesapal IPN registration failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'IPN registration failed: ' . $e->getMessage());
        }
    }

    // ── Private ────────────────────────────────────────────────────────────

    private function verifyAndComplete(PesapalTransaction $transaction, string $orderTrackingId): void
    {
        $business = \App\Models\Business::find($transaction->business_id);
        $pesapal  = new PesapalService($business->pesapalCredentials());
        $status   = $pesapal->getTransactionStatus($orderTrackingId);

        $paymentStatus   = strtoupper($status['payment_status_description'] ?? 'PENDING');
        $confirmCode     = $status['confirmation_code'] ?? null;
        $paymentMethod   = $status['payment_method'] ?? null;

        DB::transaction(function () use ($transaction, $orderTrackingId, $paymentStatus, $confirmCode, $paymentMethod, $status) {
            $transaction->update([
                'order_tracking_id' => $orderTrackingId,
                'status'            => match($paymentStatus) {
                    'COMPLETED' => 'COMPLETE',
                    'FAILED'    => 'FAILED',
                    'INVALID'   => 'INVALID',
                    default     => 'PENDING',
                },
                'confirmation_code' => $confirmCode,
                'payment_method'    => $paymentMethod,
                'payload'           => $status,
            ]);

            if ($paymentStatus === 'COMPLETED' && $transaction->sale_id) {
                $sale   = Sale::findOrFail($transaction->sale_id);
                $amount = (float) $transaction->amount;

                $newPaid    = min($sale->total_amount, $sale->paid_amount + $amount);
                $newBalance = max(0, $sale->total_amount - $newPaid);

                $sale->update([
                    'paid_amount'    => $newPaid,
                    'balance_due'    => $newBalance,
                    'payment_method' => 'pesapal',
                    'payment_status' => $newBalance <= 0 ? 'paid' : ($newPaid > 0 ? 'partial' : 'unpaid'),
                ]);

                if ($sale->customer_id && $newBalance < $sale->balance_due) {
                    $reduction = $sale->balance_due - $newBalance;
                    Customer::where('id', $sale->customer_id)->decrement('balance_owed', $reduction);
                }
            }
        });
    }
}
