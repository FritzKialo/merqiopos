<?php
namespace App\Http\Controllers;
use App\Models\PaymentLink;
use App\Models\Customer;
use App\Models\MpesaTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaymentLinkController extends Controller {
    private function businessId() { return Auth::user()->currentBusiness()->id; }

    public function index() {
        $links = PaymentLink::where('business_id', $this->businessId())->with('customer')->latest()->paginate(20);
        return view('payment-links.index', compact('links'));
    }

    public function create() {
        $customers = Customer::where('business_id', $this->businessId())->orderBy('name')->get();
        return view('payment-links.create', compact('customers'));
    }

    public function store(Request $request) {
        $businessId = $this->businessId();
        $request->validate([
            'title' => 'required|string|max:200',
            'amount' => 'required|numeric|min:1',
            // Unscoped exists: previously — could link this payment link to
            // another business's customer.
            'customer_id' => ['nullable', \Illuminate\Validation\Rule::exists('customers', 'id')->where('business_id', $businessId)],
            'description' => 'nullable|string|max:500',
            'expiry_hours' => 'nullable|integer|min:1|max:8760',
        ]);
        $link = PaymentLink::create([
            'business_id' => $businessId,
            'customer_id' => $request->customer_id,
            'title' => $request->title,
            'amount' => $request->amount,
            'token' => PaymentLink::generateToken(),
            'description' => $request->description,
            'expires_at' => $request->expiry_hours ? now()->addHours((int) $request->expiry_hours) : null,
            'status' => 'active',
        ]);
        return redirect()->route('payment-links.show', $link)->with('success', 'Payment link created.');
    }

    public function show(PaymentLink $paymentLink) {
        abort_if($paymentLink->business_id !== $this->businessId(), 403);
        $paymentLink->load('customer');
        return view('payment-links.show', ['link' => $paymentLink]);
    }

    public function sendSms(PaymentLink $paymentLink) {
        // Without this, any business could trigger an SMS to another
        // business's customer (real cost, real spam potential).
        abort_if($paymentLink->business_id !== $this->businessId(), 403);
        $paymentLink->load('customer');
        if (!$paymentLink->customer || !$paymentLink->customer->phone) {
            return back()->with('error', 'No customer phone number available.');
        }
        $message = "Hi {$paymentLink->customer->name}, please pay KSh " . number_format($paymentLink->amount, 2) . " for {$paymentLink->title}: {$paymentLink->public_url}";
        app(\App\Services\SmsService::class)->send($paymentLink->customer->phone, $message);
        return back()->with('success', 'SMS sent to ' . $paymentLink->customer->phone);
    }

    public function cancel(PaymentLink $paymentLink) {
        abort_if($paymentLink->business_id !== $this->businessId(), 403);
        $paymentLink->update(['status' => 'cancelled']);
        return back()->with('success', 'Payment link cancelled.');
    }

    public function destroy(PaymentLink $paymentLink) {
        abort_if($paymentLink->business_id !== $this->businessId(), 403);
        $paymentLink->delete();
        return redirect()->route('payment-links.index')->with('success', 'Deleted.');
    }

    public function pay(string $token) {
        $link = PaymentLink::where('token', $token)->with('business')->first();
        if (!$link) {
            return response()->view('payment-links.pay', ['link' => null, 'business' => null, 'error' => 'Payment link not found.'], 404);
        }
        $business = $link->business;
        return view('payment-links.pay', compact('link', 'business'));
    }

    public function initiate(Request $request, string $token) {
        $request->validate(['phone' => 'required|string|min:9|max:13']);
        $link = PaymentLink::with('business')->where('token', $token)->firstOrFail();
        if ($link->status !== 'active' || $link->isExpired()) {
            return response()->json(['success' => false, 'message' => 'This payment link is no longer valid.']);
        }

        $business = $link->business;

        // Was previously resolved with no credentials at all — MpesaService's
        // no-args fallback then used the PLATFORM's own .env shortcode, so
        // every payment-link STK push deposited the customer's money into
        // the platform's M-Pesa account instead of this business's. Must use
        // the business's own Daraja credentials, same as every other STK
        // push in the app (see MpesaController::initiate()).
        if (!$business || !$business->hasMpesaConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'This business has not set up M-Pesa yet. Please contact them directly to pay.',
            ]);
        }

        // Block a second STK push for this same link while an earlier one
        // is still pending (a double-tap of "Pay Now", or a slow response
        // tempting a retry) — the phone typed in isn't necessarily even the
        // same number twice, so this checks the link itself rather than a
        // phone/IP, which is what actually matters: don't ring someone's
        // phone with a second prompt for a bill they're already paying.
        $pendingPush = MpesaTransaction::where('api_ref', 'payment_link:' . $link->id)
            ->where('status', 'PENDING')
            ->where('created_at', '>=', now()->subMinutes(2))
            ->exists();
        if ($pendingPush) {
            return response()->json([
                'success' => false,
                'message' => 'A payment prompt was already sent — check your phone, or wait a moment before trying again.',
            ]);
        }

        try {
            $mpesa  = new \App\Services\MpesaService($business->mpesaCredentials());
            $result = $mpesa->stkPush(
                $request->phone,
                $link->amount,
                $link->title,
                // Was (string) $link->id — a bare numeric string like "1".
                // Daraja rejects that outright with "Bad Request - Invalid
                // Remarks", meaning every payment-link STK push has been
                // failing before it even reached the customer's phone.
                // Verified directly: identical call with this description
                // succeeds where the numeric one gets rejected every time.
                'Payment Link'
            );
            $checkoutId = $result['CheckoutRequestID'] ?? null;

            if ($checkoutId) {
                $link->update(['mpesa_checkout_id' => $checkoutId]);

                // Real, reconcilable transaction row — previously nothing was
                // recorded at all for payment-link payments, so there was no
                // paper trail tying a payment back to a business.
                MpesaTransaction::create([
                    'business_id' => $business->id,
                    'sale_id'     => null,
                    'type'        => 'payment_link',
                    'phone'       => $mpesa->formatPhone($request->phone),
                    'amount'      => $link->amount,
                    'checkout_id' => $checkoutId,
                    'api_ref'     => 'payment_link:' . $link->id,
                    'status'      => 'PENDING',
                ]);
            }

            return response()->json(['success' => true, 'message' => 'M-Pesa prompt sent. Enter your PIN to complete payment.']);
        } catch (\Exception $e) {
            Log::error('Payment link M-Pesa STK push failed', ['link_id' => $link->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'We could not start the M-Pesa payment right now. Please try again shortly or contact the business.']);
        }
    }

    public function mpesaCallback(Request $request) {
        $body = $request->all();
        $resultCode = data_get($body, 'Body.stkCallback.ResultCode');
        $checkoutId = data_get($body, 'Body.stkCallback.CheckoutRequestID');

        if (!$checkoutId) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        $transaction = MpesaTransaction::where('checkout_id', $checkoutId)
            ->where('type', 'payment_link')
            ->first();

        // Idempotency — Daraja may retry the callback on network failure.
        if ($transaction && in_array($transaction->status, ['COMPLETE', 'FAILED'])) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        $link = PaymentLink::where('mpesa_checkout_id', $checkoutId)->first();

        if ($resultCode == 0) {
            $meta    = collect(data_get($body, 'Body.stkCallback.CallbackMetadata.Item', []))->pluck('Value', 'Name');
            $receipt = $meta['MpesaReceiptNumber'] ?? null;

            $transaction?->update(['status' => 'COMPLETE', 'mpesa_receipt' => $receipt, 'payload' => $body]);
            $link?->update(['status' => 'paid', 'paid_at' => now()]);
        } else {
            $transaction?->update(['status' => 'FAILED', 'payload' => $body]);
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
