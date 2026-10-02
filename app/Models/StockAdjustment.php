<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StockAdjustment extends Model {
    protected $fillable = ['business_id','product_id','user_id','type','quantity_before','quantity_change','quantity_after','reason','notes','reference'];
    public function product() { return $this->belongsTo(Product::class); }
    public function user()    { return $this->belongsTo(User::class); }
    public function business(){ return $this->belongsTo(Business::class); }
    public function scopeForBusiness($q, int $id) { return $q->where('business_id', $id); }
    public static function typeLabels(): array {
        return ['correction'=>'Correction','damage'=>'Damage','theft'=>'Theft','wastage'=>'Wastage','recount'=>'Recount / Stocktake','return_in'=>'Return (restocked)'];
    }
}
