<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    protected $fillable = [
        'code', 'discount_type', 'discount_value',
        'applicable_plans', 'max_redemptions', 'times_redeemed',
        'expires_at', 'is_active', 'description', 'created_by',
    ];

    protected $casts = [
        'discount_value'   => 'decimal:2',
        'applicable_plans' => 'array',
        'expires_at'       => 'datetime',
        'is_active'        => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function redemptions()
    {
        return $this->hasMany(PromoCodeRedemption::class);
    }

    public function appliesToPlan(string $plan): bool
    {
        return empty($this->applicable_plans) || in_array($plan, $this->applicable_plans, true);
    }

    public function isExhausted(): bool
    {
        return $this->max_redemptions !== null && $this->times_redeemed >= $this->max_redemptions;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function discountFor(float $amount): float
    {
        $discount = $this->discount_type === 'percentage'
            ? $amount * ((float) $this->discount_value / 100)
            : (float) $this->discount_value;

        // Never discount below zero or beyond the amount itself.
        return min($discount, $amount);
    }
}
