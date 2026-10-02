<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
class ProductReview extends Model {
    protected $fillable = ['business_id','product_id','reviewer_name','reviewer_email','rating','review_body','status','ip_address'];
    public function product() { return $this->belongsTo(Product::class); }
    public function scopeForBusiness($q, $id = null) { return $q->where('business_id', $id ?? Auth::user()->currentBusiness()->id); }
    public function scopeApproved($q) { return $q->where('status', 'approved'); }
}
