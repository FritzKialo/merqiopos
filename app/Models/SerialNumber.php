<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SerialNumber extends Model {
    use HasFactory;

    protected $fillable = [
        'business_id',
        'product_id',
        'variant_id',
        'serial_number',
        'status',
        'purchase_order_id',
        'sale_id',
        'sale_return_id',
        'received_date',
        'sold_date',
        'notes',
    ];

    protected $casts = [
        'received_date' => 'date',
        'sold_date'     => 'date',
    ];

    public function product() {
        return $this->belongsTo(Product::class);
    }

    public function variant() {
        return $this->belongsTo(ProductVariant::class);
    }

    public function sale() {
        return $this->belongsTo(Sale::class);
    }

    public function purchaseOrder() {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function saleReturn() {
        return $this->belongsTo(SaleReturn::class);
    }

    public function scopeForBusiness($query, int $id) {
        return $query->where('business_id', $id);
    }

    public function scopeInStock($query) {
        return $query->where('status', 'in_stock');
    }
}
