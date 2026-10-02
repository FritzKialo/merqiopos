<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseStock extends Model
{
    protected $table = 'warehouse_stock';

    protected $fillable = ['warehouse_id', 'product_id', 'business_id', 'quantity', 'bin_location'];

    protected $casts = ['quantity' => 'decimal:2'];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
