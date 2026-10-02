<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LoyaltyProgram extends Model {
    use HasFactory;

    protected $fillable = [
        'business_id', 'name', 'points_per_shilling',
        'redemption_rate', 'min_redemption_points', 'is_active',
    ];

    protected $casts = [
        'points_per_shilling'   => 'decimal:4',
        'redemption_rate'       => 'decimal:4',
        'min_redemption_points' => 'integer',
        'is_active'             => 'boolean',
    ];

    public function business() {
        return $this->belongsTo(Business::class);
    }

    public function transactions() {
        return $this->hasMany(LoyaltyTransaction::class, 'business_id', 'business_id');
    }

    /**
     * Convert points to KSh value.
     */
    public function pointsToKsh(float $points): float {
        return round($points * $this->redemption_rate, 2);
    }

    /**
     * Convert KSh amount to points earned.
     */
    public function kshToPoints(float $amount): float {
        return round($amount * $this->points_per_shilling, 2);
    }
}
