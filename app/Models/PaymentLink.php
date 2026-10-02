<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
class PaymentLink extends Model {
    protected $fillable = ['business_id','customer_id','title','amount','currency','token','status','expires_at','paid_at','mpesa_checkout_id','description','invoice_id'];
    protected $casts = ['expires_at' => 'datetime', 'paid_at' => 'datetime'];
    public function customer() { return $this->belongsTo(Customer::class); }
    public function business() { return $this->belongsTo(Business::class); }
    public function scopeForBusiness($q, $id = null) { return $q->where('business_id', $id ?? Auth::user()->currentBusiness()->id); }
    public function isExpired(): bool { return $this->expires_at && $this->expires_at->isPast(); }
    public function getPublicUrlAttribute(): string { return route('pay.show', $this->token); }
    public static function generateToken(): string { return Str::random(40); }
}
