<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepositUsage extends Model
{
    protected $fillable = [
        'customer_deposit_id', 'invoice_id', 'sale_id',
        'amount_used', 'used_at', 'notes',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    public function customerDeposit()
    {
        return $this->belongsTo(CustomerDeposit::class);
    }
}
