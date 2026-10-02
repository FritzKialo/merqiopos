<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class StockCountItem extends Model {
    protected $fillable = ['stock_count_id','product_id','variant_id','system_qty','counted_qty','notes'];
    public function stockCount() { return $this->belongsTo(StockCount::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function getVarianceAttribute(): ?float {
        if ($this->counted_qty === null) return null;
        return $this->counted_qty - $this->system_qty;
    }
}
