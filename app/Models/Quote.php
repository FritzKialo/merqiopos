<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quote extends Model {
    use HasFactory, SoftDeletes, BelongsToBusiness;

    protected $fillable = [
        'business_id',
        'customer_id',
        'user_id',
        'quote_number',
        'quote_date',
        'valid_until',
        'subtotal',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'total',
        'status',
        'notes',
        'terms',
        'converted_to_sale_id',
    ];

    protected $casts = [
        'quote_date'      => 'date',
        'valid_until'     => 'date',
        'subtotal'        => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_rate'        => 'decimal:2',
        'tax_amount'      => 'decimal:2',
        'total'           => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────

    public function customer() {
        return $this->belongsTo(Customer::class);
    }

    public function business() {
        return $this->belongsTo(\App\Models\Business::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function items() {
        return $this->hasMany(QuoteItem::class);
    }

    public function convertedSale() {
        return $this->belongsTo(Sale::class, 'converted_to_sale_id');
    }

    // business() is provided by BelongsToBusiness trait

    // ── Quote Number Generator ─────────────────────

    public static function generateQuoteNumber(
        int $businessId
    ): string {
        $count = self::where('business_id', $businessId)
                    ->withTrashed()
                    ->count() + 1;
        return 'QT-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    // ── Status Helpers ─────────────────────────────

    public function isExpired(): bool {
        if (in_array($this->status, ['converted', 'accepted'])) {
            return false;
        }
        return $this->valid_until !== null
            && $this->valid_until->isPast();
    }

    public function canBeConverted(): bool {
        return in_array($this->status, [
            'draft', 'sent', 'accepted'
        ]);
    }

    // ── Scopes ─────────────────────────────────────

    public function scopePending($query) {
        return $query->whereIn('status', ['draft', 'sent']);
    }

    public function scopeExpired($query) {
        return $query
            ->whereDate('valid_until', '<', today())
            ->whereNotIn('status', ['converted', 'expired']);
    }
}
