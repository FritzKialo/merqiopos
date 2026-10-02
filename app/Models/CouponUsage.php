<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CouponUsage extends Model {
    protected $fillable = ['coupon_id','business_id','customer_id','sale_id','invoice_id','online_order_id','discount_applied','used_at'];
    protected $casts = ['used_at' => 'datetime'];
    public function coupon() { return $this->belongsTo(Coupon::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
}
