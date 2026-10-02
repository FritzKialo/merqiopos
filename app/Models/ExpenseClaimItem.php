<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseClaimItem extends Model
{
    protected $fillable = [
        'expense_claim_id', 'description', 'expense_date',
        'category', 'amount', 'receipt_path',
    ];

    protected $casts = [
        'expense_date' => 'date',
    ];

    public function expenseClaim() { return $this->belongsTo(ExpenseClaim::class); }
}
