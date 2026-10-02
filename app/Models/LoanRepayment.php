<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LoanRepayment extends Model {
    use HasFactory;

    protected $fillable = [
        'business_loan_id', 'business_id', 'user_id',
        'amount', 'principal_portion', 'interest_portion',
        'payment_date', 'payment_method', 'reference',
        'balance_after', 'notes',
    ];

    protected $casts = [
        'payment_date'      => 'date',
        'amount'            => 'decimal:2',
        'principal_portion' => 'decimal:2',
        'interest_portion'  => 'decimal:2',
        'balance_after'     => 'decimal:2',
    ];

    public function loan() {
        return $this->belongsTo(BusinessLoan::class, 'business_loan_id');
    }

    public function business() {
        return $this->belongsTo(Business::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function scopeForBusiness($query, $businessId) {
        return $query->where('business_id', $businessId);
    }
}
