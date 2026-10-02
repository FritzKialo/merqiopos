<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierCreditNote extends Model
{
    protected $fillable = [
        'business_id', 'supplier_id', 'credit_number', 'issue_date',
        'reason', 'status', 'amount', 'applied_amount', 'notes',
    ];

    protected $casts = [
        'issue_date' => 'date',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(SupplierCreditNoteItem::class);
    }

    public function scopeForBusiness($query, $businessId = null)
    {
        return $query->where('business_id', $businessId ?? auth()->user()->currentBusiness()->id);
    }
}
