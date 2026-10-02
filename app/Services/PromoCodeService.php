<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\PromoCode;
use App\Models\PromoCodeRedemption;
use App\Models\Subscription;

class PromoCodeService
{
    /**
     * Checks a submitted code against every rule (exists, active, not
     * expired, not exhausted, applies to this plan, not already used by
     * this organization) and returns the discounted price if it passes.
     * One place for this logic since it's checked from both the "apply
     * code" preview endpoint and the actual checkout initiation — a code
     * that previewed as valid must be re-validated at checkout time too,
     * since time/usage can pass between the two.
     */
    public function validate(string $code, string $plan, Organization $organization, float $amount): array
    {
        $promoCode = PromoCode::whereRaw('UPPER(code) = ?', [strtoupper(trim($code))])->first();

        if (! $promoCode) {
            return ['valid' => false, 'message' => 'That promo code was not found.'];
        }

        if (! $promoCode->is_active) {
            return ['valid' => false, 'message' => 'That promo code is no longer active.'];
        }

        if ($promoCode->isExpired()) {
            return ['valid' => false, 'message' => 'That promo code has expired.'];
        }

        if ($promoCode->isExhausted()) {
            return ['valid' => false, 'message' => 'That promo code has reached its redemption limit.'];
        }

        if (! $promoCode->appliesToPlan($plan)) {
            return ['valid' => false, 'message' => 'That promo code does not apply to this plan.'];
        }

        $alreadyUsed = PromoCodeRedemption::where('promo_code_id', $promoCode->id)
            ->where('organization_id', $organization->id)
            ->exists();

        if ($alreadyUsed) {
            return ['valid' => false, 'message' => 'You have already used this promo code.'];
        }

        $discount = $promoCode->discountFor($amount);

        return [
            'valid'            => true,
            'promoCode'        => $promoCode,
            'originalAmount'   => $amount,
            'discountAmount'   => $discount,
            'discountedAmount' => round($amount - $discount, 2),
        ];
    }

    /**
     * Records the redemption and increments the usage counter. Called only
     * once a payment has actually completed (not when the code is merely
     * applied at checkout), so an abandoned checkout never burns a
     * one-time-per-organization code.
     */
    public function redeem(PromoCode $promoCode, Organization $organization, ?Subscription $subscription, float $originalAmount, float $discountAmount): void
    {
        PromoCodeRedemption::create([
            'promo_code_id'   => $promoCode->id,
            'organization_id' => $organization->id,
            'subscription_id' => $subscription?->id,
            'original_amount' => $originalAmount,
            'discount_amount' => $discountAmount,
            'final_amount'    => round($originalAmount - $discountAmount, 2),
        ]);

        $promoCode->increment('times_redeemed');
    }
}
