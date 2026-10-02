<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnlineOrder extends Model
{
    protected $fillable = [
        'business_id', 'customer_name', 'customer_email', 'customer_phone',
        'delivery_address', 'status', 'subtotal', 'delivery_fee', 'total',
        'payment_method', 'mpesa_checkout_id', 'payment_confirmed_at',
        'notes', 'reference', 'sale_id', 'coupon_code', 'coupon_discount_amount',
    ];

    protected $casts = ['payment_confirmed_at' => 'datetime'];

    public function business() { return $this->belongsTo(Business::class); }
    public function items()    { return $this->hasMany(OnlineOrderItem::class); }
    public function sale()     { return $this->belongsTo(Sale::class); }
}
