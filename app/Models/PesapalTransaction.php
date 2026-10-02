<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PesapalTransaction extends Model
{
    protected $fillable = [
        'business_id', 'sale_id', 'merchant_reference', 'order_tracking_id',
        'amount', 'currency', 'status', 'payment_method', 'confirmation_code', 'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'amount'  => 'float',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
