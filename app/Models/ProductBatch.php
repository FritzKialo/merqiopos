<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductBatch extends Model {
    use HasFactory;

    protected $fillable = [
        'business_id',
        'product_id',
        'variant_id',
        'batch_number',
        'expiry_date',
        'quantity',
        'cost_price',
        'supplier_id',
        'received_date',
        'notes',
    ];

    protected $casts = [
        'expiry_date'   => 'date',
        'received_date' => 'date',
        'quantity'      => 'decimal:2',
        'cost_price'    => 'decimal:2',
    ];

    public function product() {
        return $this->belongsTo(Product::class);
    }

    public function variant() {
        return $this->belongsTo(ProductVariant::class);
    }

    public function supplier() {
        return $this->belongsTo(Supplier::class);
    }

    public function scopeForBusiness($query, int $id) {
        return $query->where('business_id', $id);
    }

    public function scopeExpiringSoon($query, int $days = 30) {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '>=', now()->toDateString())
            ->where('expiry_date', '<=', now()->addDays($days)->toDateString());
    }

    public function scopeExpired($query) {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->toDateString());
    }

    public function isExpired(): bool {
        return $this->expiry_date && $this->expiry_date->lt(now()->startOfDay());
    }

    public function daysUntilExpiry(): ?int {
        if (!$this->expiry_date) return null;
        return (int) now()->startOfDay()->diffInDays($this->expiry_date, false);
    }

    /**
     * First-expiry-first-out: take a sold quantity off the product's batches,
     * earliest expiry first (no-expiry batches last), so batch quantities and
     * expiry alerts track real stock instead of drifting. Does nothing for a
     * product that has no batches, and never touches product stock itself.
     */
    public static function consume(int $productId, ?int $variantId, float $qty): void {
        if ($qty <= 0) return;
        $batches = static::where('product_id', $productId)
            ->when($variantId, fn ($q) => $q->where('variant_id', $variantId))
            ->where('quantity', '>', 0)
            // A bound literal date rather than CURDATE() (MySQL-only, breaks on sqlite) —
            // works identically on every driver since it's just a value comparison.
            ->orderByRaw('(expiry_date IS NOT NULL AND expiry_date < ?)', [now()->toDateString()])
            ->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        foreach ($batches as $b) {
            if ($qty <= 0) break;
            $take = min((float) $b->quantity, $qty);
            $b->update(['quantity' => (float) $b->quantity - $take]);
            $qty -= $take;
        }
    }

    /** Put quantity back (a cancelled sale) onto the earliest-expiry batch. */
    public static function restore(int $productId, ?int $variantId, float $qty): void {
        if ($qty <= 0) return;
        $b = static::where('product_id', $productId)
            ->when($variantId, fn ($q) => $q->where('variant_id', $variantId))
            ->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->first();
        if ($b) $b->update(['quantity' => (float) $b->quantity + $qty]);
    }
}
