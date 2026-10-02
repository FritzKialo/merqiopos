<?php

namespace App\Http\Controllers;

use App\Mail\SubscriptionConfirmed;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\PaystackTransaction;
use App\Models\PromoCode;
use App\Models\Subscription;
use App\Services\PaystackService;
use App\Services\PromoCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PaystackController extends Controller
{
    // ── Initiate Checkout ──────────────────────────────────────────────────

    /**
     * Initialize a Paystack transaction and redirect to hosted checkout.
     */
    public function initialize(Request $request, PromoCodeService $promoCodeService)
    {
        $request->validate([
            'plan'       => 'required|in:solo,growth,enterprise',
            'promo_code' => 'nullable|string|max:40',
        ]);

        $user = Auth::user();

        if (!$user->isOwner()) {
            return back()->with('error', 'Only the organization owner can manage subscriptions.');
        }

        $organization = $user->organization;
        $planKey      = $request->plan;
        $plan         = config('plans.org.' . $planKey);
        $amount       = (int) $plan['price'];
        $reference    = 'SUB-' . strtoupper(Str::random(12));
        $promoCode    = null;

        if ($request->filled('promo_code')) {
            $result = $promoCodeService->validate($request->promo_code, $planKey, $organization, $amount);

            if (! $result['valid']) {
                return back()->with('error', $result['message']);
            }

            $promoCode = strtoupper(trim($request->promo_code));
            $amount    = (int) round($result['discountedAmount']);
        }

        try {
            $paystack = new PaystackService();
            $data     = $paystack->initialize(
                email: $user->email,
                amountKes: $amount,
                reference: $reference,
                metadata: [
                    'plan'            => $planKey,
                    'organization_id' => $organization->id,
                    'user_id'         => $user->id,
                    'plan_name'       => $plan['name'],
                ]
            );

            // Record pending transaction so webhook/callback can find it
            PaystackTransaction::create([
                'organization_id' => $organization->id,
                'user_id'         => $user->id,
                'reference'       => $reference,
                'plan'            => $planKey,
                'amount'          => $amount,
                'promo_code'      => $promoCode,
                'status'          => 'pending',
            ]);

            return redirect($data['authorization_url']);

        } catch (\Exception $e) {
            Log::error('Paystack initialize failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'Card payment is not available right now. Please try M-Pesa, or contact support if this continues.');
        }
    }

    // ── Callback (redirect after checkout) ────────────────────────────────

    /**
     * Paystack redirects here after the user completes (or cancels) payment.
     * We verify the transaction before acting on it.
     */
    public function callback(Request $request)
    {
        $reference = $request->query('trxref') ?? $request->query('reference');

        if (!$reference) {
            return redirect()->route('settings.subscription')
                ->with('error', 'Invalid payment callback. Please check your subscription status.');
        }

        $pending = PaystackTransaction::where('reference', $reference)
            ->where('status', 'pending')
            ->first();

        if (!$pending) {
            // Already processed (webhook beat the callback) or unknown reference
            $done = PaystackTransaction::where('reference', $reference)
                ->where('status', 'success')
                ->first();

            if ($done) {
                return redirect()->route('settings.subscription')
                    ->with('success', 'Payment already confirmed. Your subscription is active.');
            }

            return redirect()->route('settings.subscription')
                ->with('error', 'Could not find payment record. Contact support if charged.');
        }

        try {
            $paystack = new PaystackService();
            $data     = $paystack->verify($reference);

            DB::transaction(function () use ($pending, $data, $reference) {
                $this->activateSubscription($pending, (float)($data['amount'] / 100), $reference);
            });

            return redirect()->route('settings.subscription')
                ->with('success', "Payment confirmed! Your {$pending->plan} plan is now active.");

        } catch (\Exception $e) {
            Log::error('Paystack callback verification failed', [
                'reference' => $reference,
                'error'     => $e->getMessage(),
            ]);
            return redirect()->route('settings.subscription')
                ->with('error', 'We could not verify your payment. If you were charged, contact support and we will confirm it manually.');
        }
    }

    // ── Webhook ────────────────────────────────────────────────────────────

    /**
     * Paystack server-to-server webhook.
     * Handles charge.success (covers one-time and recurring charges).
     * No auth/CSRF — exempt via bootstrap/app.php.
     */
    public function webhook(Request $request)
    {
        $signature = $request->header('X-Paystack-Signature', '');
        $payload   = $request->getContent();

        $paystack = new PaystackService();

        if (!$paystack->verifyWebhookSignature($payload, $signature)) {
            // Bumped from warning() — invisible in production, whose
            // LOG_LEVEL only records error and above (see ReceiptService.php).
            // An invalid signature on this endpoint could mean a spoofed
            // webhook attempt, worth actually seeing.
            Log::error('Paystack webhook: invalid signature');
            return response()->json(['status' => 'invalid signature'], 400);
        }

        $event = $request->json('event');
        $data  = $request->json('data');

        Log::info('Paystack webhook received', ['event' => $event]);

        match ($event) {
            'charge.success'      => $this->handleChargeSuccess($data),
            'subscription.create' => $this->handleSubscriptionCreate($data),
            default               => null,
        };

        return response()->json(['status' => 'ok']);
    }

    // ── Private Helpers ────────────────────────────────────────────────────

    private function handleChargeSuccess(array $data): void
    {
        $reference = $data['reference'] ?? null;
        if (!$reference) return;

        // Idempotency
        if (PaystackTransaction::where('reference', $reference)->where('status', 'success')->exists()) {
            Log::info('Paystack webhook: already processed', ['reference' => $reference]);
            return;
        }

        $pending = PaystackTransaction::where('reference', $reference)->first();

        if (!$pending) {
            // Bumped from warning() — see the note on this method's
            // signature check above. Real money moved (Paystack is
            // confirming a completed payment) with nothing here to match it to.
            Log::error('Paystack webhook: no pending transaction found', ['reference' => $reference]);
            return;
        }

        $amount = (float) ($data['amount'] / 100);

        DB::transaction(function () use ($pending, $amount, $reference) {
            $this->activateSubscription($pending, $amount, $reference);
        });
    }

    private function handleSubscriptionCreate(array $data): void
    {
        // Paystack creates a subscription plan object when using recurring billing.
        // Log for now — handle if recurring is enabled later.
        Log::info('Paystack subscription.create', ['data' => $data]);
    }

    private function activateSubscription(
        PaystackTransaction $pending,
        float $amount,
        string $reference
    ): void {
        // Mark transaction as success first (idempotency guard)
        $pending->update(['status' => 'success', 'paid_at' => now()]);

        $organization = Organization::findOrFail($pending->organization_id);
        $planKey      = $pending->plan;

        $subscription = Subscription::create([
            'organization_id'   => $organization->id,
            'plan'              => $planKey,
            'amount'            => $amount,
            'start_date'        => now()->toDateString(),
            'end_date'          => now()->addDays(30)->toDateString(),
            'status'            => 'active',
            'payment_reference' => $reference,
            'payment_channel'   => 'paystack',
        ]);

        $pending->update(['subscription_id' => $subscription->id]);

        $organization->update([
            'subscription_plan' => $planKey,
            'status'            => 'active',
        ]);

        if ($pending->promo_code) {
            $promoCode = PromoCode::whereRaw('UPPER(code) = ?', [$pending->promo_code])->first();
            if ($promoCode) {
                $plan           = config('plans.org.' . $planKey);
                $discountAmount = max(0, (float) $plan['price'] - $amount);
                app(PromoCodeService::class)->redeem($promoCode, $organization, $subscription, (float) $plan['price'], $discountAmount);
            }
        }

        AuditLog::create([
            'business_id'  => null,
            'user_id'      => $pending->user_id,
            'event'        => 'subscription.paid',
            'subject_type' => Subscription::class,
            'subject_id'   => $subscription->id,
            'metadata'     => [
                'organization_id' => $organization->id,
                'plan'            => $planKey,
                'amount'          => $amount,
                'reference'       => $reference,
                'channel'         => 'paystack',
            ],
            'ip_address' => null,
            'created_at' => now(),
        ]);

        $owner = $organization->owner;
        if ($owner?->email) {
            try {
                Mail::to($owner->email)->queue(
                    new SubscriptionConfirmed($organization, $subscription)
                );
            } catch (\Exception $e) {
                // Bumped from warning() — invisible in production, whose
                // LOG_LEVEL only records error and above (see ReceiptService.php).
                Log::error('Subscription confirmation email failed', ['error' => $e->getMessage()]);
            }
        }

        Log::info('Paystack subscription activated', [
            'organization' => $organization->id,
            'plan'         => $planKey,
            'reference'    => $reference,
        ]);
    }
}
