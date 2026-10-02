<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BankStatementLine extends Model {
    use HasFactory;

    protected $fillable = [
        'bank_statement_import_id', 'bank_account_id',
        'transaction_date', 'description', 'reference',
        'debit', 'credit', 'balance',
        'matched_type', 'matched_id', 'is_reconciled',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'debit'            => 'decimal:2',
        'credit'           => 'decimal:2',
        'balance'          => 'decimal:2',
        'is_reconciled'    => 'boolean',
    ];

    public function import() {
        return $this->belongsTo(BankStatementImport::class, 'bank_statement_import_id');
    }

    public function bankAccount() {
        return $this->belongsTo(BankAccount::class);
    }

    public function amount(): float {
        return (float) ($this->credit ?? $this->debit ?? 0);
    }
}
