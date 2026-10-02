<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A request to Safaricom's Transaction Status service to confirm that a typed-in
 * M-Pesa receipt code is a real, completed payment to this business.
 */
class MpesaStatusCheck extends Model
{
    protected $fillable = [
        'business_id', 'sale_id', 'receipt', 'claimed_amount', 'status',
        'conversation_id', 'result_desc', 'confirmed_amount', 'response', 'resolved_at',
    ];

    protected $casts = [
        'claimed_amount'   => 'decimal:2',
        'confirmed_amount' => 'decimal:2',
        'response'         => 'array',
        'resolved_at'      => 'datetime',
    ];

    public function sale() { return $this->belongsTo(Sale::class); }

    /** Latest check for a sale, if any. */
    public static function latestForSale(int $saleId): ?self
    {
        return static::where('sale_id', $saleId)->latest('id')->first();
    }
}
