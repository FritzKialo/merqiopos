<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Discount extends Model {
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'code',
        'type',
        'value',
        'min_order_amount',
        'max_uses',
        'uses_count',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected $casts = [
        'value'            => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'valid_from'       => 'date',
        'valid_until'      => 'date',
        'is_active'        => 'boolean',
    ];

    public function scopeForBusiness($query, int $id) {
        return $query->where('business_id', $id);
    }

    public function scopeActive($query) {
        return $query->where('is_active', true);
    }

    public function isValid(): bool {
        if (!$this->is_active) return false;
        $today = now()->startOfDay();
        if ($this->valid_from && $this->valid_from->gt($today)) return false;
        if ($this->valid_until && $this->valid_until->lt($today)) return false;
        if ($this->max_uses !== null && $this->uses_count >= $this->max_uses) return false;
        return true;
    }

    public function calculate(float $orderAmount): float {
        if ($this->min_order_amount && $orderAmount < $this->min_order_amount) {
            return 0;
        }
        if ($this->type === 'percentage') {
            return round($orderAmount * ($this->value / 100), 2);
        }
        return min((float) $this->value, $orderAmount);
    }
}
