<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPriceTier extends Model {
    protected $table = 'product_price_tier';

    protected $fillable = [
        'price_tier_id',
        'product_id',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function priceTier() {
        return $this->belongsTo(PriceTier::class);
    }

    public function product() {
        return $this->belongsTo(Product::class);
    }
}
