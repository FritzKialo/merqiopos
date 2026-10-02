<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BankStatementImport extends Model {
    use HasFactory;

    protected $fillable = [
        'business_id', 'bank_account_id', 'user_id',
        'filename', 'period_start', 'period_end',
        'imported_at', 'total_credits', 'total_debits',
    ];

    protected $casts = [
        'period_start'   => 'date',
        'period_end'     => 'date',
        'imported_at'    => 'datetime',
        'total_credits'  => 'decimal:2',
        'total_debits'   => 'decimal:2',
    ];

    public function bankAccount() {
        return $this->belongsTo(BankAccount::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function lines() {
        return $this->hasMany(BankStatementLine::class);
    }
}
