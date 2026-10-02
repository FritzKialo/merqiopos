<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TableOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'table_order_id', 'product_id', 'product_name', 'quantity',
        'unit_price', 'discount', 'total', 'notes', 'status', 'sent_to_kitchen_at', 'sale_id',
    ];

    protected $casts = [
        'quantity'   => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount'   => 'decimal:2',
        'total'      => 'decimal:2',
        'sent_to_kitchen_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(TableOrder::class, 'table_order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
