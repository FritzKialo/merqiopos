<?php

namespace App\Services;

use App\Mail\SubscriptionConfirmed;
use App\Models\AuditLog;
use App\Models\MpesaTransaction;
use App\Models\Organization;
use App\Models\PromoCode;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SubscriptionService
{
    /**
     * Activate a subscription for an organization after successful M-Pesa payment.
     * api_ref format: "plan:organization_id"
     */
    public function activateFromPayment(
        MpesaTransaction $transaction,
        float $amount,
        ?string $receipt
    ): void {
        [$planKey, $orgId] = explode(':', $transaction->api_ref, 2);

        $orgId    = (int) $orgId;
        $planData = config('plans.org.' . $planKey);

        if (!$planData || !$orgId) {
            Log::error('Subscription callback: invalid api_ref', [
                'api_ref' => $transaction->api_ref,
            ]);
            return;
        }

        $organization = Organization::find($orgId);

        if (!$organization) {
            Log::error('Subscription callback: organization not found', [
                'organization_id' => $orgId,
            ]);
            return;
        }

        $subscription = Subscription::create([
            'organization_id'   => $orgId,
            'plan'              => $planKey,
            'amount'            => $amount,
            'start_date'        => now()->toDateString(),
            'end_date'          => now()->addDays(30)->toDateString(),
            'status'            => 'active',
            'payment_reference' => $receipt,
        ]);

        $transaction->update([
            'subscription_id' => $subscription->id,
            'organization_id' => $orgId,
        ]);

        $organization->update([
            'subscription_plan' => $planKey,
            'status'            => 'active',
        ]);

        if ($transaction->promo_code) {
            $promoCode = PromoCode::whereRaw('UPPER(code) = ?', [$transaction->promo_code])->first();
            if ($promoCode) {
                $discountAmount = max(0, (float) $planData['price'] - $amount);
                app(PromoCodeService::class)->redeem($promoCode, $organization, $subscription, (float) $planData['price'], $discountAmount);
            }
        }

        AuditLog::create([
            'business_id'  => null,
            'user_id'      => null,
            'event'        => 'subscription.paid',
            'subject_type' => Subscription::class,
            'subject_id'   => $subscription->id,
            'metadata'     => [
                'organization_id' => $orgId,
                'plan'            => $planKey,
                'amount'          => $amount,
                'receipt'         => $receipt,
            ],
            'ip_address' => null,
            'created_at' => now(),
        ]);

        $owner = $organization->owner;
        if ($owner?->email) {
            try {
                // Pass organization and subscription to the mail
                Mail::to($owner->email)->queue(
                    new SubscriptionConfirmed($organization, $subscription)
                );
            } catch (\Exception $e) {
                // Bumped from warning() — invisible in production, whose
                // LOG_LEVEL only records error and above (see ReceiptService.php,
                // whose "invisible view ParseError" is exactly the failure
                // mode this same try/catch pattern would have hidden here too).
                Log::error('Subscription email failed', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Check whether an organization's active subscription is expired.
     */
    public function isExpired(Organization $organization): bool
    {
        $active = $organization->subscriptions()
            ->where('status', 'active')
            ->latest('end_date')
            ->first();

        return !$active || $active->end_date->isPast();
    }
}
