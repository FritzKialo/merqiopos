<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CommissionRule extends Model {
    protected $fillable = ['business_id','staff_profile_id','rule_type','rate','min_sales_amount','is_active'];
    protected $casts = ['is_active' => 'boolean', 'rate' => 'decimal:4', 'min_sales_amount' => 'decimal:2'];

    public function staffProfile() { return $this->belongsTo(StaffProfile::class); }

    public function scopeForBusiness($q, $id = null) {
        return $q->where('business_id', $id ?? Auth::user()->currentBusiness()->id);
    }

    public function scopeActive($q) { return $q->where('is_active', true); }
}
