<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ExpenseClaim extends Model
{
    use LogsActivity;

    protected $fillable = [
        'business_id', 'user_id', 'approved_by', 'reference',
        'title', 'total_amount', 'status', 'submitted_at', 'paid_at', 'rejection_reason',
    ];

    protected $casts = [
        'submitted_at' => 'date',
        'paid_at'      => 'date',
    ];

    public function user()      { return $this->belongsTo(User::class); }
    public function approver()  { return $this->belongsTo(User::class, 'approved_by'); }
    public function business()  { return $this->belongsTo(Business::class); }
    public function items()     { return $this->hasMany(ExpenseClaimItem::class); }

    public function scopeForBusiness($q, $id = null)
    {
        return $q->where('business_id', $id ?? Auth::user()->currentBusiness()->id);
    }

    public static function generateReference(int $businessId): string
    {
        $count = static::where('business_id', $businessId)->count() + 1;
        return 'EC-' . date('Ymd') . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
    }
}
