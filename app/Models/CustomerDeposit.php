<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerDeposit extends Model
{
    protected $fillable = [
        'business_id', 'customer_id', 'reference', 'amount', 'currency',
        'payment_method', 'mpesa_code', 'received_at', 'notes',
        'status', 'used_amount',
    ];

    protected $casts = [
        'received_at' => 'date',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function usages()
    {
        return $this->hasMany(DepositUsage::class);
    }

    public function availableBalance(): float
    {
        return (float) $this->amount - (float) $this->used_amount;
    }

    public function scopeForBusiness($query, $businessId = null)
    {
        return $query->where('business_id', $businessId ?? auth()->user()->currentBusiness()->id);
    }
}
