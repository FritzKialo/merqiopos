<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class PurchaseRequisition extends Model
{
    protected $fillable = [
        'business_id', 'requested_by', 'approved_by', 'reference',
        'status', 'urgency', 'justification', 'required_by', 'purchase_order_id',
    ];

    protected $casts = [
        'required_by' => 'date',
    ];

    public function business()    { return $this->belongsTo(Business::class); }
    public function requestedBy() { return $this->belongsTo(User::class, 'requested_by'); }
    public function approvedBy()  { return $this->belongsTo(User::class, 'approved_by'); }
    public function items()       { return $this->hasMany(PurchaseRequisitionItem::class); }

    public function scopeForBusiness($q, $id = null)
    {
        return $q->where('business_id', $id ?? Auth::user()->currentBusiness()->id);
    }

    public static function generateReference(int $businessId): string
    {
        $count = static::where('business_id', $businessId)->count() + 1;
        return 'PR-' . date('Ymd') . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
    }
}
