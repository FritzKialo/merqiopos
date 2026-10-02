<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model {
    use HasFactory, SoftDeletes, BelongsToBusiness;

    protected $fillable = [
        'business_id',
        'expense_category_id',
        'user_id',
        'title',
        'description',
        'amount',
        'payment_method',
        'reference',
        'expense_date',
        'receipt',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'expense_date' => 'date',
    ];

    // Relationships
    public function category() {
        return $this->belongsTo(
            ExpenseCategory::class,
            'expense_category_id'
        );
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeThisMonth($query) {
        return $query
            ->whereMonth('expense_date', now()->month)
            ->whereYear('expense_date',  now()->year);
    }

    public function scopeThisYear($query) {
        return $query->whereYear(
            'expense_date', now()->year
        );
    }

    public function scopeForMonth(
        $query, $month, $year
    ) {
        return $query
            ->whereMonth('expense_date', $month)
            ->whereYear('expense_date',  $year);
    }

    public function scopeForDateRange(
        $query, $from, $to
    ) {
        return $query
            ->whereDate('expense_date', '>=', $from)
            ->whereDate('expense_date', '<=', $to);
    }
}