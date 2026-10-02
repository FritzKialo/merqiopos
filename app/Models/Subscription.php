<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'business_id',
        'plan', 'amount',
        'start_date', 'end_date',
        'status', 'payment_reference',
        // Column defaults to 'mpesa' (correct for the M-Pesa creation path,
        // which never explicitly sets it), but PaystackController explicitly
        // passes 'paystack' here — silently dropped without this, so every
        // Paystack-paid subscription was mislabeled as 'mpesa'.
        'payment_channel',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    // Kept for backward compatibility — legacy subscriptions still have business_id
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->end_date !== null
            && $this->end_date->isFuture();
    }
}
