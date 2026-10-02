<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RestaurantTable extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id', 'number', 'name', 'capacity',
        'status', 'current_order_id', 'sort_order', 'qr_token',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function currentOrder()
    {
        return $this->belongsTo(TableOrder::class, 'current_order_id');
    }

    public function orders()
    {
        return $this->hasMany(TableOrder::class);
    }

    public function scopeForBusiness($query, int $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    public function requests()
    {
        return $this->hasMany(TableOrderRequest::class);
    }

    /** Every table gets a QR code lazily — created the first time it's asked for. */
    public function qrToken(): string
    {
        if (! $this->qr_token) {
            $this->qr_token = \Illuminate\Support\Str::random(32);
            $this->save();
        }

        return $this->qr_token;
    }
}
