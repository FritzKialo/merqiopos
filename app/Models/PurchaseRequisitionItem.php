<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseRequisitionItem extends Model
{
    protected $fillable = [
        'purchase_requisition_id', 'description', 'quantity',
        'unit', 'estimated_unit_price', 'product_id',
    ];

    public function purchaseRequisition() { return $this->belongsTo(PurchaseRequisition::class); }
    public function product()             { return $this->belongsTo(Product::class); }
}
