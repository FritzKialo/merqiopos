<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CommissionEarning extends Model {
    protected $fillable = ['business_id','staff_profile_id','sale_id','commission_rule_id','period_month','period_year','gross_sales','commission_amount','status','payroll_item_id'];

    public function staffProfile() { return $this->belongsTo(StaffProfile::class); }
    public function commissionRule() { return $this->belongsTo(CommissionRule::class); }

    public function scopeForBusiness($q, $id = null) {
        return $q->where('business_id', $id ?? Auth::user()->currentBusiness()->id);
    }
}
