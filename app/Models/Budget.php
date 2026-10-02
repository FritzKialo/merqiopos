<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Budget extends Model {
    use HasFactory;

    protected $fillable = [
        'business_id',
        'expense_category_id',
        'name',
        'period_type',
        'year',
        'month',
        'quarter',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function business() {
        return $this->belongsTo(Business::class);
    }

    public function expenseCategory() {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function scopeForBusiness($query, $businessId) {
        return $query->where('business_id', $businessId);
    }
}
