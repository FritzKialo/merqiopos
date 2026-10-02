<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LeaveType extends Model {
    use HasFactory;

    protected $fillable = [
        'business_id', 'name', 'days_per_year', 'is_paid', 'requires_approval',
    ];

    protected $casts = [
        'is_paid'           => 'boolean',
        'requires_approval' => 'boolean',
    ];

    public function business() {
        return $this->belongsTo(Business::class);
    }

    public function leaveRequests() {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveBalances() {
        return $this->hasMany(LeaveBalance::class);
    }

    public function scopeForBusiness($query, int $businessId) {
        return $query->where('business_id', $businessId);
    }
}
