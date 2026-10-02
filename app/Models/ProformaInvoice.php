<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProformaInvoice extends Model
{
    protected $fillable = [
        'business_id', 'customer_id', 'proforma_number', 'issue_date',
        'valid_until', 'status', 'notes', 'subtotal', 'tax_amount',
        'total_amount', 'currency', 'converted_invoice_id',
    ];

    protected $casts = [
        'issue_date'  => 'date',
        'valid_until' => 'date',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(ProformaInvoiceItem::class);
    }

    public function scopeForBusiness($query, $businessId = null)
    {
        return $query->where('business_id', $businessId ?? auth()->user()->currentBusiness()->id);
    }
}
