<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExpenseCategory extends Model {
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'description',
    ];

    // Relationships
    public function business() {
        return $this->belongsTo(Business::class);
    }

    public function expenses() {
        return $this->hasMany(Expense::class);
    }

    // Total spent in this category
    public function totalSpent(): float {
        return $this->expenses()
            ->where('business_id', $this->business_id)
            ->sum('amount');
    }

    // Scope: for this business
    public function scopeForBusiness(
        $query, $businessId
    ) {
        return $query->where(
            'business_id', $businessId
        );
    }
}