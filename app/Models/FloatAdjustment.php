<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FloatAdjustment extends Model {
    use HasFactory;

    protected $fillable = [
        'business_id', 'shift_id', 'user_id', 'type', 'amount', 'recorded_by', 'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function business() {
        return $this->belongsTo(Business::class);
    }

    public function shift() {
        return $this->belongsTo(Shift::class);
    }

    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function recordedBy() {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeForBusiness($query, int $businessId) {
        return $query->where('business_id', $businessId);
    }

    public function scopeForShiftUser($query, int $shiftId, int $userId) {
        return $query->where('shift_id', $shiftId)->where('user_id', $userId);
    }
}
