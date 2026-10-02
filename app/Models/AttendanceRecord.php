<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AttendanceRecord extends Model {
    use HasFactory;

    protected $fillable = [
        'business_id', 'user_id', 'date',
        'clock_in', 'clock_out', 'status', 'shift_id', 'notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function business() {
        return $this->belongsTo(Business::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function shift() {
        return $this->belongsTo(Shift::class);
    }

    public function scopeForBusiness($query, int $businessId) {
        return $query->where('business_id', $businessId);
    }
}
