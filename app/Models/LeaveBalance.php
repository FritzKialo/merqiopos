<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LeaveBalance extends Model {
    use HasFactory;

    protected $fillable = [
        'business_id', 'user_id', 'leave_type_id',
        'year', 'entitled_days', 'used_days', 'remaining_days',
    ];

    protected $casts = [
        'entitled_days'  => 'decimal:1',
        'used_days'      => 'decimal:1',
        'remaining_days' => 'decimal:1',
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

    public function scopeForBusiness($query, int $businessId) {
        return $query->where('business_id', $businessId);
    }

    /**
     * Get or create a balance record for the given user, type, and year.
     */
    public static function getOrCreate(int $businessId, int $userId, LeaveType $leaveType, int $year): self {
        return self::firstOrCreate(
            ['user_id' => $userId, 'leave_type_id' => $leaveType->id, 'year' => $year],
            [
                'business_id'    => $businessId,
                'entitled_days'  => $leaveType->days_per_year,
                'used_days'      => 0,
                'remaining_days' => $leaveType->days_per_year,
            ]
        );
    }
}
