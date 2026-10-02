<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BankAccount extends Model {
    use HasFactory;

    protected $fillable = [
        'business_id', 'name', 'account_number', 'bank_name', 'current_balance',
    ];

    protected $casts = [
        'current_balance' => 'decimal:2',
    ];

    public function business() {
        return $this->belongsTo(Business::class);
    }

    public function imports() {
        return $this->hasMany(BankStatementImport::class);
    }

    public function lines() {
        return $this->hasMany(BankStatementLine::class);
    }

    public function unreconciledCount(): int {
        return $this->lines()->where('is_reconciled', false)->count();
    }

    public function scopeForBusiness($query, int $businessId) {
        return $query->where('business_id', $businessId);
    }
}
