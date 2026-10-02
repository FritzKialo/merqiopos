<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class QuoteItem extends Model {
    use HasFactory;

    protected $fillable = [
        'quote_id',
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

    public function quote() {
        return $this->belongsTo(Quote::class);
    }

    public function product() {
        return $this->belongsTo(Product::class);
    }
}
