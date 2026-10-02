<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSupplier extends Model {
    protected $fillable = [
        'product_id',
        'supplier_id',
        'supplier_part_no',
        'supplier_price',
    ];

    protected $casts = [
        'supplier_price' => 'decimal:2',
    ];

    public function product() {
        return $this->belongsTo(Product::class);
    }

    public function supplier() {
        return $this->belongsTo(Supplier::class);
    }
}
