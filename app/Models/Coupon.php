<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
class Coupon extends Model {
    protected $fillable = ['business_id','code','name','discount_type','discount_value','min_order_amount','max_uses','used_count','is_active','starts_at','expires_at'];
    protected $casts = ['is_active'=>'boolean','starts_at'=>'datetime','expires_at'=>'datetime','discount_value'=>'decimal:2'];
    public function usages() { return $this->hasMany(CouponUsage::class); }
    public function scopeForBusiness($q, $id = null) { return $q->where('business_id', $id ?? Auth::user()->currentBusiness()->id); }
    /**
     * Uses already taken plus unpaid online orders still holding the code.
     * Usage is only recorded once an order is paid, so without counting the
     * pending ones a limited-use coupon could be handed to any number of
     * customers who all check out before the first one pays. Orders left
     * unpaid for over a day stop reserving it, so abandoned carts don't
     * burn the coupon.
     */
    public function effectiveUses(): int {
        if ($this->max_uses === null) return (int) $this->used_count;
        $pending = OnlineOrder::where('business_id', $this->business_id)
            ->where('coupon_code', $this->code)
            ->where('status', 'pending')
            ->whereNull('payment_confirmed_at')
            ->where('created_at', '>=', now()->subDay())
            ->count();
        return (int) $this->used_count + $pending;
    }
    public function isValid(float $orderAmount = 0): bool {
        if (!$this->is_active) return false;
        if ($this->starts_at && $this->starts_at->isFuture()) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        if ($this->max_uses !== null && $this->effectiveUses() >= $this->max_uses) return false;
        if ($orderAmount < $this->min_order_amount) return false;
        return true;
    }
    public function calculateDiscount(float $amount): float {
        if ($this->discount_type === 'percentage') return min($amount, $amount * $this->discount_value / 100);
        return min($amount, $this->discount_value);
    }
}
