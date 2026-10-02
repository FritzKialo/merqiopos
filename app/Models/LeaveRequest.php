<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LeaveRequest extends Model {
    use HasFactory;

    protected $fillable = [
        'business_id', 'user_id', 'leave_type_id',
        'start_date', 'end_date', 'days_requested',
        'reason', 'status', 'approved_by', 'approved_at', 'rejection_reason',
    ];

    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'approved_at' => 'datetime',
        'days_requested' => 'decimal:1',
    ];

    public function business() {
        return $this->belongsTo(Business::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function leaveType() {
        return $this->belongsTo(LeaveType::class);
    }

    public function approver() {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeForBusiness($query, int $businessId) {
        return $query->where('business_id', $businessId);
    }

    public function isPending(): bool {
        return $this->status === 'pending';
    }
}
