<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductVariant extends Model {
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'barcode',
        'price',
        'cost_price',
        'stock_qty',
        'reorder_level',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price'         => 'decimal:2',
        'cost_price'    => 'decimal:2',
        'stock_qty'     => 'decimal:2',
        'reorder_level' => 'decimal:2',
        'is_active'     => 'boolean',
    ];

    public function product() {
        return $this->belongsTo(Product::class);
    }

    public function effectivePrice(): float {
        return $this->price !== null
            ? (float) $this->price
            : (float) $this->product->selling_price;
    }

    public function scopeActive($query) {
        return $query->where('is_active', true);
    }
}
