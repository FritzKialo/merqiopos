<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A customer's scan-to-order request from the table QR page — held for staff to
 * approve before it becomes a real TableOrderItem on the bill and reaches the
 * kitchen. A customer's phone never writes directly into a live order.
 */
class TableOrderRequest extends Model
{
    protected $fillable = [
        'business_id', 'restaurant_table_id', 'product_id', 'product_name',
        'quantity', 'notes', 'status', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'quantity'    => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function table()
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeForBusiness($query, int $businessId)
    {
        return $query->where('business_id', $businessId);
    }
}
