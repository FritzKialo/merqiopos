<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SaleItem extends Model {
    use HasFactory;

    protected $fillable = [
        'sale_id', 'product_id', 'variant_id', 'product_name',
        'unit_price', 'buying_price',
        'quantity', 'discount', 'subtotal', 'is_bundle_summary',
    ];

    protected $casts = [
        'unit_price'        => 'decimal:2',
        'buying_price'      => 'decimal:2',
        'discount'          => 'decimal:2',
        'subtotal'          => 'decimal:2',
        'quantity'          => 'integer',
        'is_bundle_summary' => 'boolean',
    ];

    public function sale() {
        return $this->belongsTo(Sale::class);
    }

    public function product() {
        return $this->belongsTo(Product::class);
    }

    public function variant() {
        return $this->belongsTo(ProductVariant::class);
    }

    // Profit for this line item
    public function lineProfit(): float {
        return ($this->unit_price - $this->buying_price)
            * $this->quantity;
    }
}