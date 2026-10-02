<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class SaleReturn extends Model {
    use SoftDeletes;
    protected $fillable = ['business_id','sale_id','user_id','customer_id','return_number','total_refund','stock_action','refund_method','reason','notes'];
    public function sale()     { return $this->belongsTo(Sale::class); }
    public function user()     { return $this->belongsTo(User::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function items()    { return $this->hasMany(SaleReturnItem::class); }
    public function scopeForBusiness($q, int $id) { return $q->where('business_id', $id); }
}
