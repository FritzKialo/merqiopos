<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RecurringInvoiceItem extends Model {
    use HasFactory;

    protected $fillable = [
        'recurring_invoice_id',
        'product_id',
        'product_name',
        'description',
        'unit_price',
        'quantity',
        'subtotal',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'subtotal'   => 'decimal:2',
        'quantity'   => 'integer',
    ];

    // ── Relationships ──────────────────────────────

    public function recurringInvoice() {
        return $this->belongsTo(RecurringInvoice::class);
    }

    public function product() {
        return $this->belongsTo(Product::class);
    }
}
