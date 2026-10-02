<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StockTransferItem extends Model {
    protected $fillable = ['stock_transfer_id','product_id','dest_product_id','product_name','quantity_requested','quantity_dispatched','quantity_received','notes'];
    public function transfer()    { return $this->belongsTo(StockTransfer::class,'stock_transfer_id'); }
    public function product()     { return $this->belongsTo(Product::class); }
    public function destProduct() { return $this->belongsTo(Product::class, 'dest_product_id'); }
}
