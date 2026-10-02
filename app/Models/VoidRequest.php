<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VoidRequest extends Model {
    use HasFactory;

    protected $fillable = [
        'business_id', 'sale_id', 'void_reason_id', 'note',
        'status', 'requested_by', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function business() {
        return $this->belongsTo(Business::class);
    }

    public function sale() {
        return $this->belongsTo(Sale::class);
    }

    public function voidReason() {
        return $this->belongsTo(VoidReason::class);
    }

    public function requestedBy() {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy() {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeForBusiness($query, int $businessId) {
        return $query->where('business_id', $businessId);
    }

    public function scopePending($query) {
        return $query->where('status', 'pending');
    }

    public function isPending(): bool {
        return $this->status === 'pending';
    }
}
