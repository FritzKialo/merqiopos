<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseOrderItem extends Model {
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'product_name',
        'quantity_ordered',
        'quantity_received',
        'unit_cost',
        'subtotal',
        'vat_rate',
        'vat_amount',
    ];

    protected $casts = [
        'unit_cost'         => 'decimal:2',
        'subtotal'          => 'decimal:2',
        'quantity_ordered'  => 'integer',
        'quantity_received' => 'integer',
        'vat_rate'          => 'decimal:2',
        'vat_amount'        => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────

    public function purchaseOrder() {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product() {
        return $this->belongsTo(Product::class);
    }

    // ── Helpers ────────────────────────────────────

    public function quantityPending(): int {
        return $this->quantity_ordered - $this->quantity_received;
    }
}
