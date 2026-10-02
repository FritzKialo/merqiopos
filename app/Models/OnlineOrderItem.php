<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnlineOrderItem extends Model
{
    protected $fillable = [
        'online_order_id', 'product_id', 'variant_id',
        'product_name', 'quantity', 'unit_price', 'total', 'is_bundle_summary',
    ];

    protected $casts = [
        'is_bundle_summary' => 'boolean',
    ];

    public function order()   { return $this->belongsTo(OnlineOrder::class, 'online_order_id'); }
    public function product() { return $this->belongsTo(Product::class); }
    public function variant() { return $this->belongsTo(ProductVariant::class, 'variant_id'); }
}
