<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringInvoice extends Model {
    use HasFactory, SoftDeletes, BelongsToBusiness;

    protected $fillable = [
        'business_id',
        'customer_id',
        'user_id',
        'title',
        'frequency',
        'next_run_date',
        'last_run_date',
        'end_date',
        'is_active',
        'subtotal',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'total',
        'notes',
        'run_count',
    ];

    protected $casts = [
        'next_run_date'   => 'date',
        'last_run_date'   => 'date',
        'end_date'        => 'date',
        'is_active'       => 'boolean',
        'subtotal'        => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_rate'        => 'decimal:2',
        'tax_amount'      => 'decimal:2',
        'total'           => 'decimal:2',
        'run_count'       => 'integer',
    ];

    // ── Relationships ──────────────────────────────

    public function customer() {
        return $this->belongsTo(Customer::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function items() {
        return $this->hasMany(RecurringInvoiceItem::class);
    }

    // business() is provided by BelongsToBusiness trait

    // ── Business Logic Helpers ─────────────────────

    public function isDue(): bool {
        return $this->is_active
            && $this->next_run_date->lte(today());
    }

    /**
     * Compute the next run date after a given date,
     * based on the recurring frequency.
     */
    public function nextRunAfter(Carbon $date): Carbon {
        return match ($this->frequency) {
            'weekly'    => $date->copy()->addWeek(),
            'quarterly' => $date->copy()->addMonths(3),
            'yearly'    => $date->copy()->addYear(),
            default     => $date->copy()->addMonth(), // monthly
        };
    }

    // ── Scopes ─────────────────────────────────────

    public function scopeActive($query) {
        return $query->where('is_active', true);
    }

    public function scopeDue($query) {
        return $query
            ->where('is_active', true)
            ->whereDate('next_run_date', '<=', today());
    }
}
