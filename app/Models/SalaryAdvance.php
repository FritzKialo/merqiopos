<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SalaryAdvance extends Model {
    use HasFactory;

    protected $fillable = [
        'business_id', 'user_id', 'approved_by',
        'amount', 'reason', 'status',
        'approved_at', 'deducted_at', 'deduction_period_id',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'approved_at' => 'datetime',
        'deducted_at' => 'datetime',
    ];

    public function business() {
        return $this->belongsTo(Business::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function approver() {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payrollPeriod() {
        return $this->belongsTo(PayrollPeriod::class, 'deduction_period_id');
    }

    public function scopeForBusiness($query, int $businessId) {
        return $query->where('business_id', $businessId);
    }

    public function isPending(): bool {
        return $this->status === 'pending';
    }
}
