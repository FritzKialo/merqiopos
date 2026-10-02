<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\LoyaltyProgram;
use App\Models\MpesaTransaction;
use App\Services\MpesaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CustomerPortalController extends Controller {

    // ── Current authenticated customer ───────────────────────────────────────
    private function customer(): Customer {
        return Auth::guard('customer')->user();
    }

    // ── LOGIN ────────────────────────────────────────────────────────────────
    public function loginForm() {
        return view('portal.login');
    }

    public function login(Request $request) {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // The platform is multi-tenant and customers.email has no unique-per-
        // business constraint — two different businesses can each have a
        // customer record with the same email. Match ALL of them and verify
        // the password against each individually, rather than picking
        // whichever record happens to come back first(). That earlier
        // approach could log a customer into (or let them reset the
        // password for) a completely different business's record.
        $candidates = Customer::where('email', $request->email)
            ->whereNotNull('portal_password')
            ->get()
            ->filter(fn ($c) => Hash::check($request->password, $c->portal_password))
            ->values();

        if ($candidates->isEmpty()) {
            return back()->withErrors(['email' => 'Invalid email or password.'])->withInput();
        }

        if ($candidates->count() > 1) {
            // Same email + same password matched customer records at more
            // than one business — let them choose which one they meant.
            session([
                'portal_login_candidates' => $candidates->pluck('id')->all(),
                'portal_login_remember'   => $request->boolean('remember'),
            ]);
            return redirect()->route('portal.choose-business');
        }

        return $this->completePortalLogin($candidates->first(), $request->boolean('remember'));
    }

    // ── Finish logging a specific customer record in (shared by login() and
    //    chooseBusiness()) ─────────────────────────────────────────────────
    private function completePortalLogin(Customer $customer, bool $remember) {
        $business = $customer->business;
        if ($business && ! $business->portal_enabled) {
            return back()->withErrors(['email' => 'The customer portal is not currently enabled.']);
        }

        Auth::guard('customer')->login($customer, $remember);
        $customer->update(['portal_last_login' => now()]);

        return redirect()->route('portal.dashboard');
    }

    // ── Business picker (shown when login matched more than one business) ──
    public function chooseBusinessForm() {
        $ids = session('portal_login_candidates', []);
        if (empty($ids)) {
            return redirect()->route('portal.login');
        }

        $customers = Customer::whereIn('id', $ids)->with('business')->get();
        return view('portal.choose-business', compact('customers'));
    }

    public function chooseBusiness(Request $request) {
        $ids = session('portal_login_candidates', []);
        if (empty($ids)) {
            return redirect()->route('portal.login');
        }

        $request->validate(['customer_id' => 'required|integer']);

        // Only allow completing login as one of the already password-verified
        // candidates from this session — never trust an arbitrary posted id.
        if (! in_array((int) $request->customer_id, $ids, true)) {
            abort(403);
        }

        $customer = Customer::findOrFail($request->customer_id);
        $remember = session('portal_login_remember', false);
        session()->forget(['portal_login_candidates', 'portal_login_remember']);

        return $this->completePortalLogin($customer, $remember);
    }

    // ── FORGOT PASSWORD ───────────────────────────────────────────────────────
    public function forgotForm() {
        return view('portal.forgot');
    }

    public function sendReset(Request $request) {
        $request->validate(['email' => 'required|email']);

        // Same multi-tenant concern as login(): don't just take the first
        // match — an email can belong to a customer record at more than one
        // business. Send every matching business its own reset link (each
        // gets its own unique portal_token), clearly labelled by business
        // name, rather than silently resetting an arbitrary one of them.
        $customers = Customer::where('email', $request->email)->get();

        foreach ($customers as $customer) {
            $token = Str::random(60);
            $customer->update([
                'portal_token'             => $token,
                'portal_token_expires_at'  => now()->addMinutes(60),
            ]);

            $resetUrl = route('portal.reset', ['token' => $token]);
            $businessName = optional($customer->business)->name ?? config('app.name');

            Mail::send('portal.emails.reset', [
                'customer'     => $customer,
                'resetUrl'     => $resetUrl,
                'businessName' => $businessName,
                // Full model, not just the name — the view uses it to show
                // the business's own logo/phone/email/address instead of a
                // plain text name (previously the only thing passed here).
                'business'     => $customer->business,
            ], function ($message) use ($customer, $businessName) {
                $message->to($customer->email, $customer->name)
                        ->subject("Reset your {$businessName} portal password");
            });
        }

        // Always show success to avoid email enumeration
        return back()->with('success', 'If that email is in our system, a reset link has been sent.');
    }

    // ── RESET PASSWORD ────────────────────────────────────────────────────────
    public function resetForm(string $token) {
        $customer = Customer::where('portal_token', $token)->first();
        if (! $customer || ! $this->portalTokenValid($customer)) {
            return redirect()->route('portal.login')->withErrors(['token' => 'Invalid or expired reset link.']);
        }
        return view('portal.reset', compact('token', 'customer'));
    }

    public function resetPassword(Request $request) {
        $request->validate([
            'token'    => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $customer = Customer::where('portal_token', $request->token)->first();
        if (! $customer || ! $this->portalTokenValid($customer)) {
            return redirect()->route('portal.login')->withErrors(['token' => 'Invalid or expired reset link.']);
        }

        $customer->update([
            'portal_password'          => Hash::make($request->password),
            'portal_token'             => null,
            'portal_token_expires_at'  => null,
        ]);

        return redirect()->route('portal.login')->with('success', 'Password reset successfully. Please log in.');
    }

    /**
     * portal_token previously never expired — a leaked reset/invite link
     * stayed valid indefinitely. Rows created before this fix have a null
     * portal_token_expires_at; treat those as expired too rather than
     * grandfathering them in as permanently valid.
     */
    private function portalTokenValid(Customer $customer): bool
    {
        return $customer->portal_token_expires_at !== null
            && now()->lt($customer->portal_token_expires_at);
    }

    // ── DASHBOARD ─────────────────────────────────────────────────────────────
    public function dashboard() {
        $customer = $this->customer();
        $business = $customer->business;

        $openInvoices = Invoice::where('customer_id', $customer->id)
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->orderByDesc('issue_date')
            ->get();

        $totalOutstanding = $openInvoices->sum('balance_due');
        $openCount        = $openInvoices->count();

        // Recent 5 invoices
        $recentInvoices = Invoice::where('customer_id', $customer->id)
            ->orderByDesc('issue_date')
            ->limit(5)
            ->get();

        // Last payment
        $lastPayment = \App\Models\InvoicePayment::whereHas('invoice', function ($q) use ($customer) {
                $q->where('customer_id', $customer->id);
            })
            ->orderByDesc('paid_date')
            ->first();

        // Loyalty
        $loyaltyProgram = LoyaltyProgram::where('business_id', $customer->business_id)
            ->where('is_active', true)
            ->first();

        $loyaltyPoints = 0;
        if ($loyaltyProgram) {
            $loyaltyPoints = \App\Models\LoyaltyTransaction::where('customer_id', $customer->id)
                ->orderByDesc('id')
                ->value('balance_after') ?? 0;
        }

        return view('portal.dashboard', compact(
            'customer', 'business', 'openInvoices', 'totalOutstanding',
            'openCount', 'recentInvoices', 'lastPayment', 'loyaltyProgram', 'loyaltyPoints'
        ));
    }

    // ── INVOICES ──────────────────────────────────────────────────────────────
    public function invoices(Request $request) {
        $customer = $this->customer();

        $query = Invoice::where('customer_id', $customer->id)->orderByDesc('issue_date');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $invoices = $query->paginate(20)->withQueryString();

        return view('portal.invoices.index', compact('invoices', 'customer'));
    }

    public function invoiceDetail(Invoice $invoice) {
        $customer = $this->customer();
        if ($invoice->customer_id !== $customer->id) {
            abort(403);
        }
        $invoice->load(['items', 'payments', 'business']);
        return view('portal.invoices.show', compact('invoice', 'customer'));
    }

    public function invoicePdf(Invoice $invoice) {
        $customer = $this->customer();
        if ($invoice->customer_id !== $customer->id) {
            abort(403);
        }

        // Reuse the InvoiceController PDF logic
        $invoice->load(['items', 'business', 'customer', 'payments']);
        $business = $invoice->business;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoices.pdf', compact('invoice', 'business'));

        return $pdf->download('invoice-' . $invoice->invoice_number . '.pdf');
    }

    // ── STATEMENT ─────────────────────────────────────────────────────────────
    public function statement() {
        $customer = $this->customer();

        $credits = \App\Models\CustomerCredit::where('customer_id', $customer->id)
            ->orderBy('created_at')
            ->get();

        // The view needs $invoices/$totalInvoiced/$totalPaid/$balance, but this
        // method never fetched or computed any of them — every hit 500'd with
        // "Undefined variable $totalInvoiced". Invoice already tracks total and
        // amount_paid per row (kept in sync by Invoice::recordPayment()), so no
        // separate payments query is needed here.
        $invoices = Invoice::where('customer_id', $customer->id)
            ->orderByDesc('issue_date')
            ->get();

        $totalInvoiced = $invoices->sum('total');
        $totalPaid = $invoices->sum('amount_paid');
        $balance = $totalInvoiced - $totalPaid;

        return view('portal.statement', compact('credits', 'customer', 'invoices', 'totalInvoiced', 'totalPaid', 'balance'));
    }

    // ── LOYALTY ───────────────────────────────────────────────────────────────
    public function loyalty() {
        $customer = $this->customer();

        $loyaltyProgram = LoyaltyProgram::where('business_id', $customer->business_id)
            ->where('is_active', true)
            ->first();

        $transactions = \App\Models\LoyaltyTransaction::where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->get();

        // Named to match portal/loyalty.blade.php's $balance — a mismatched name
        // here ($pointBalance) left the view with an undefined $balance and a
        // 500 on every visit to this page.
        $balance = $transactions->last()?->balance_after ?? 0;

        return view('portal.loyalty', compact('loyaltyProgram', 'transactions', 'balance', 'customer'));
    }

    // ── PAY INVOICE ───────────────────────────────────────────────────────────
    public function payInvoice(Invoice $invoice, Request $request) {
        $customer = $this->customer();
        if ($invoice->customer_id !== $customer->id) {
            abort(403);
        }

        if ($invoice->balance_due <= 0) {
            return back()->with('error', 'This invoice is already paid.');
        }

        $request->validate([
            // Array syntax required here: the pipe-delimited string form splits
            // rules on every "|", including the ones inside this regex's own
            // alternation (07|01|2547|2541) — that mangled the pattern and made
            // every payment attempt 500 with "No ending delimiter '/' found".
            'phone' => ['required', 'string', 'regex:/^(07|01|2547|2541)\d{8}$/'],
        ]);

        $business = $customer->business;

        $credentials = [
            'consumer_key'    => $business->mpesa_consumer_key,
            'consumer_secret' => $business->mpesa_consumer_secret,
            'shortcode'       => $business->mpesa_shortcode,
            'passkey'         => $business->mpesa_passkey,
            'till_number'     => $business->mpesa_till_number,
            'environment'     => $business->mpesa_environment,
        ];

        try {
            $mpesa  = new MpesaService($credentials);
            $phone  = preg_replace('/^0/', '254', preg_replace('/^254/', '254', $request->phone));
            $amount = (int) ceil($invoice->balance_due);
            $ref    = $invoice->invoice_number;

            // Previously this never saved the checkout_id anywhere and never
            // created an MpesaTransaction row — meaning when Safaricom's
            // callback arrived there was nothing to match it back to, so a
            // portal invoice payment could never auto-confirm; the invoice
            // stayed unpaid until someone recorded it manually. Route the
            // callback to this controller's own handler (not the platform-
            // wide default, which only recognizes POS/MpesaTransaction
            // payments of other types) and record a real, reconcilable
            // transaction — same pattern PaymentLinkController already uses.
            $response   = $mpesa->stkPush($phone, $amount, $ref, "Invoice {$ref} payment", route('portal.invoice.mpesa.callback'));
            $checkoutId = $response['CheckoutRequestID'] ?? null;

            if ($checkoutId) {
                MpesaTransaction::create([
                    'business_id' => $business->id,
                    'type'        => 'portal_invoice',
                    'phone'       => $mpesa->formatPhone($request->phone),
                    'amount'      => $amount,
                    'checkout_id' => $checkoutId,
                    'api_ref'     => 'invoice:' . $invoice->id,
                    'status'      => 'PENDING',
                ]);
            }

            return back()->with('success', 'M-Pesa payment request sent to ' . $request->phone . '. Please check your phone to complete the payment.');
        } catch (\Exception $e) {
            Log::error('Portal invoice M-Pesa payment failed', ['invoice_id' => $invoice->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'We could not start the M-Pesa payment right now. Please try again shortly or contact the business.');
        }
    }

    /**
     * M-Pesa STK callback for a portal invoice payment. Mirrors
     * PaymentLinkController::mpesaCallback() exactly — find the pending
     * MpesaTransaction by checkout_id, apply the payment via the same
     * Invoice::recordPayment() the manual "Record Payment" staff action
     * uses (passing an explicit owner user_id, since this runs with no
     * authenticated session at all).
     */
    public function invoiceMpesaCallback(Request $request) {
        $body       = $request->all();
        $resultCode = data_get($body, 'Body.stkCallback.ResultCode');
        $checkoutId = data_get($body, 'Body.stkCallback.CheckoutRequestID');

        if (!$checkoutId) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        $transaction = MpesaTransaction::where('checkout_id', $checkoutId)
            ->where('type', 'portal_invoice')
            ->first();

        if (!$transaction) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        // Idempotency — Daraja may retry the callback on network failure.
        if (in_array($transaction->status, ['COMPLETE', 'FAILED'])) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        if ($resultCode == 0) {
            $meta    = collect(data_get($body, 'Body.stkCallback.CallbackMetadata.Item', []))->pluck('Value', 'Name');
            $receipt = $meta['MpesaReceiptNumber'] ?? null;
            $amount  = (float) ($meta['Amount'] ?? $transaction->amount);

            $transaction->update(['status' => 'COMPLETE', 'mpesa_receipt' => $receipt, 'payload' => $body]);

            $invoiceId = (int) str_replace('invoice:', '', $transaction->api_ref ?? '');
            $invoice   = Invoice::find($invoiceId);

            if ($invoice) {
                $ownerId = $invoice->business->users()->wherePivot('role', 'owner')->first()?->id
                    ?? $invoice->business->users()->first()?->id;
                if ($ownerId) {
                    $invoice->recordPayment(
                        min($amount, $invoice->balance_due),
                        'mpesa',
                        now()->toDateString(),
                        $receipt,
                        $ownerId
                    );
                }
            }
        } else {
            $transaction->update(['status' => 'FAILED', 'payload' => $body]);
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    // ── LOGOUT ────────────────────────────────────────────────────────────────
    public function logout(Request $request) {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('portal.login')->with('success', 'You have been logged out.');
    }

    // ── PROFILE ───────────────────────────────────────────────────────────────
    public function profile() {
        $customer = $this->customer();
        return view('portal.profile', compact('customer'));
    }

    public function updateProfile(Request $request) {
        $customer = $this->customer();

        $request->validate([
            'name'  => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'required|email|max:255',
        ]);

        // If changing password
        if ($request->filled('current_password')) {
            $request->validate([
                'current_password'      => 'required|string',
                'new_password'          => 'required|string|min:8|confirmed',
            ]);

            if (! Hash::check($request->current_password, $customer->portal_password)) {
                return back()->withErrors(['current_password' => 'Current password is incorrect.']);
            }

            $customer->update(['portal_password' => Hash::make($request->new_password)]);
        }

        $customer->update([
            'name'  => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
        ]);

        return back()->with('success', 'Profile updated successfully.');
    }

    // ── INVITE (called from main app by business user) ────────────────────────
    public function invite(Request $request, Customer $customer) {
        // Must be authenticated as a business user (web guard)
        $businessId = Auth::user()->currentBusiness()->id;

        if ($customer->business_id !== $businessId) {
            abort(403);
        }

        if (! $customer->email) {
            return back()->with('error', 'This customer does not have an email address.');
        }

        $token = Str::random(60);
        $customer->update([
            'portal_token'             => $token,
            // Invite links get a longer window than password-reset links
            // since they're an initial-setup step, not a security recovery.
            'portal_token_expires_at'  => now()->addDays(7),
        ]);

        $setupUrl     = route('portal.reset', ['token' => $token]);
        $business     = Auth::user()->currentBusiness();
        $businessName = $business->name;

        try {
            Mail::send('portal.emails.invite', [
                'customer'     => $customer,
                'setupUrl'     => $setupUrl,
                'businessName' => $businessName,
                // Full model, not just the name — the view uses it to show the
                // business's own logo/phone/email/address instead of a plain
                // text name (previously the only thing passed here).
                'business'     => $business,
            ], function ($message) use ($customer, $businessName) {
                $message->to($customer->email, $customer->name)
                        ->subject("You're invited to the {$businessName} customer portal");
            });
        } catch (\Throwable $e) {
            \Log::error('Portal invite email failed', ['customer' => $customer->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'The invitation could not be emailed right now. Please try again shortly.');
        }

        return back()->with('success', 'Portal invitation sent to ' . $customer->email);
    }
}
