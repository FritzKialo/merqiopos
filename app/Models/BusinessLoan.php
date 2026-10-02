<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BusinessLoan extends Model {
    use HasFactory;

    protected $fillable = [
        'business_id', 'user_id', 'lender_name', 'loan_type',
        'principal_amount', 'interest_rate', 'disbursement_date',
        'repayment_start_date', 'term_months', 'monthly_installment',
        'outstanding_balance', 'status', 'notes',
    ];

    protected $casts = [
        'disbursement_date'    => 'date',
        'repayment_start_date' => 'date',
        'principal_amount'     => 'decimal:2',
        'interest_rate'        => 'decimal:2',
        'monthly_installment'  => 'decimal:2',
        'outstanding_balance'  => 'decimal:2',
    ];

    // ── View-friendly aliases ──────────────────────────────────────────────
    // The loans index/show views reference `principal` and `balance`.
    public function getPrincipalAttribute() {
        return $this->principal_amount;
    }

    public function getBalanceAttribute() {
        return $this->outstanding_balance;
    }

    // Loan "due date" isn't a stored column — it's the end of the
    // repayment schedule (start date + term). Computed here so the
    // loans/show and loans/index views (which reference $loan->due_date)
    // get a real value instead of silently reading a non-existent
    // attribute as null.
    public function getDueDateAttribute() {
        if (!$this->repayment_start_date || !$this->term_months) {
            return null;
        }
        return $this->repayment_start_date->copy()->addMonths($this->term_months);
    }

    // status only ever stores active/fully_paid/defaulted (see migration) —
    // "overdue" was never a real status the app could set, just dead UI
    // in the views. Compute it instead: still active, balance outstanding,
    // and past the computed due date.
    public function isOverdue(): bool {
        $dueDate = $this->due_date;
        return $this->status === 'active'
            && $this->outstanding_balance > 0
            && $dueDate !== null
            && $dueDate->isPast();
    }

    public function business() {
        return $this->belongsTo(Business::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function repayments() {
        return $this->hasMany(LoanRepayment::class);
    }

    public function scopeForBusiness($query, $businessId) {
        return $query->where('business_id', $businessId);
    }

    public function scopeActive($query) {
        return $query->where('status', 'active');
    }

    /**
     * Compute monthly installment using amortization formula:
     * M = P * r*(1+r)^n / ((1+r)^n - 1)
     */
    public static function computeInstallment(float $principal, float $annualRate, int $termMonths): float {
        if ($annualRate == 0) {
            return $termMonths > 0 ? $principal / $termMonths : $principal;
        }
        $r = ($annualRate / 100) / 12;
        $n = $termMonths;
        return $principal * ($r * pow(1 + $r, $n)) / (pow(1 + $r, $n) - 1);
    }
}
