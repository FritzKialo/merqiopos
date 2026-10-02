<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierCreditNoteItem extends Model
{
    protected $fillable = [
        'supplier_credit_note_id', 'description', 'quantity', 'unit_price', 'total',
    ];

    public function supplierCreditNote()
    {
        return $this->belongsTo(SupplierCreditNote::class);
    }
}
