<?php
namespace App\Models;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class StockReceive extends Model {
    use SoftDeletes, LogsActivity;
    protected $fillable = ['business_id','purchase_order_id','supplier_id','user_id','receive_number','received_date','total_cost','invoice_ref','notes'];
    protected $casts = ['received_date'=>'date'];
    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class); }
    public function supplier()      { return $this->belongsTo(Supplier::class); }
    public function user()          { return $this->belongsTo(User::class); }
    public function items()         { return $this->hasMany(StockReceiveItem::class); }
    public function scopeForBusiness($q, int $id) { return $q->where('business_id', $id); }
}
