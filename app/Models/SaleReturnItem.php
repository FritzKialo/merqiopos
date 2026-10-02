<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SaleReturnItem extends Model {
    protected $fillable = ['sale_return_id','sale_item_id','product_id','variant_id','product_name','quantity_returned','unit_price','subtotal'];
    public function saleReturn() { return $this->belongsTo(SaleReturn::class); }
    public function saleItem()   { return $this->belongsTo(SaleItem::class); }
    public function product()    { return $this->belongsTo(Product::class); }
    public function variant()    { return $this->belongsTo(ProductVariant::class); }
}
