<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model {
    use HasFactory, SoftDeletes, BelongsToBusiness, LogsActivity;

    protected $fillable = [
        'business_id',
        'supplier_id',
        'user_id',
        'po_number',
        'order_date',
        'expected_date',
        'received_date',
        'subtotal',
        'tax_amount',
        'total',
        'amount_paid',
        'status',
        'payment_status',
        'notes',
    ];

    protected $casts = [
        'order_date'    => 'date',
        'expected_date' => 'date',
        'received_date' => 'date',
        'subtotal'      => 'decimal:2',
        'tax_amount'    => 'decimal:2',
        'total'         => 'decimal:2',
        'amount_paid'   => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────

    public function supplier() {
        return $this->belongsTo(Supplier::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function items() {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    // business() is provided by BelongsToBusiness trait

    // ── PO Number Generator ────────────────────────

    public static function generatePoNumber(
        int $businessId
    ): string {
        $count = self::where('business_id', $businessId)
                    ->withTrashed()
                    ->count() + 1;
        return 'PO-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    // ── Business Logic Helpers ─────────────────────

    public function amountDue(): float {
        return (float) ($this->total - $this->amount_paid);
    }

    public function isFullyReceived(): bool {
        return $this->status === 'received';
    }

    // ── Scopes ─────────────────────────────────────

    public function scopePending($query) {
        return $query->whereIn('status', [
            'draft', 'ordered', 'partially_received'
        ]);
    }
}
