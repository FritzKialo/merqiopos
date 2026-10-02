<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LoyaltyTransaction extends Model {
    use HasFactory;

    protected $fillable = [
        'business_id', 'customer_id', 'user_id', 'sale_id',
        'type', 'points', 'balance_after', 'description',
    ];

    protected $casts = [
        'points'        => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function business() {
        return $this->belongsTo(Business::class);
    }

    public function customer() {
        return $this->belongsTo(Customer::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function sale() {
        return $this->belongsTo(Sale::class);
    }

    public function scopeForBusiness($query, int $businessId) {
        return $query->where('business_id', $businessId);
    }
}
