<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MpesaTransaction extends Model
{
    protected $fillable = [
        'sale_id',
        'subscription_id',
        'organization_id',
        // business_id/bill_ref_number/shortcode were added by a later
        // migration specifically "needed for C2B shortcode matching" (its
        // own comment) but never added here — MpesaController's C2B handler
        // has been silently dropping all three on every single C2B payment
        // it records, including business_id, which is the only way to tell
        // which business an UNMATCHED C2B payment (no linked sale) even
        // belongs to.
        'business_id',
        'bill_ref_number',
        'shortcode',
        'type',
        'phone',
        'amount',
        'api_ref',
        'promo_code',
        'checkout_id',
        'mpesa_receipt',
        'status',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
