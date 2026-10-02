<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StockReceiveItem extends Model {
    protected $fillable = ['stock_receive_id','product_id','purchase_order_item_id','product_name','quantity_received','unit_cost','subtotal','received_unit','received_qty_in_unit'];
    public function stockReceive()      { return $this->belongsTo(StockReceive::class); }
    public function product()           { return $this->belongsTo(Product::class); }
    public function purchaseOrderItem() { return $this->belongsTo(PurchaseOrderItem::class); }
}
