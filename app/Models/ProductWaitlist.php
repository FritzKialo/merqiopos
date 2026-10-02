<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
class ProductWaitlist extends Model {
    protected $fillable = ['business_id','product_id','customer_name','customer_email','customer_phone','quantity_wanted','notified_at','status','notes'];
    protected $casts = ['notified_at' => 'datetime'];
    public function product() { return $this->belongsTo(Product::class); }
    public function scopeForBusiness($q, $id = null) { return $q->where('business_id', $id ?? Auth::user()->currentBusiness()->id); }
    public function scopeWaiting($q) { return $q->where('status', 'waiting'); }
}
