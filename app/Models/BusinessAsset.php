<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BusinessAsset extends Model {
    use HasFactory;

    protected $fillable = [
        'business_id', 'user_id', 'name', 'category',
        'description', 'purchase_date', 'purchase_cost',
        'depreciation_method', 'useful_life_years', 'residual_value',
        'current_value', 'status', 'disposal_date',
        'disposal_proceeds', 'notes',
    ];

    protected $casts = [
        'purchase_date'  => 'date',
        'disposal_date'  => 'date',
        'purchase_cost'  => 'decimal:2',
        'residual_value' => 'decimal:2',
        'current_value'  => 'decimal:2',
        'disposal_proceeds' => 'decimal:2',
    ];

    public function business() {
        return $this->belongsTo(Business::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function computeCurrentValue(): float {
        $cost     = (float) $this->purchase_cost;
        $residual = (float) $this->residual_value;

        if ($this->depreciation_method === 'none') {
            return $cost;
        }

        // straight_line
        $yearsElapsed = $this->purchase_date
            ? $this->purchase_date->diffInDays(now()) / 365.25
            : 0;

        $useful = max(1, (int) $this->useful_life_years);
        $depreciationPerYear = ($cost - $residual) / $useful;
        $value = $cost - ($depreciationPerYear * $yearsElapsed);

        return max($residual, $value);
    }

    public function annualDepreciation(): float {
        if ($this->depreciation_method === 'none') return 0;
        $cost     = (float) $this->purchase_cost;
        $residual = (float) $this->residual_value;
        $useful   = max(1, (int) $this->useful_life_years);
        return ($cost - $residual) / $useful;
    }

    protected static function booted(): void {
        static::saving(function (BusinessAsset $asset) {
            $asset->current_value = $asset->computeCurrentValue();
        });
    }

    public function scopeForBusiness($query, $businessId) {
        return $query->where('business_id', $businessId);
    }

    public function scopeActive($query) {
        return $query->where('status', 'active');
    }
}
