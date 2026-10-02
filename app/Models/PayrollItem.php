<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PayrollItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_period_id', 'user_id', 'business_id',
        'pay_type',
        'retainer_amount', 'commission_rate', 'commission_sales',
        'gross_pay',
        'nssf_employee', 'shif_employee', 'helb', 'paye', 'total_deductions',
        'nssf_employer', 'shif_employer',
        'housing_levy_employee', 'housing_levy_employer',
        'net_pay',
        'deduction_details',
        'status', 'notes',
        'mpesa_conversation_id', 'mpesa_result_code', 'mpesa_result_desc',
    ];

    protected $casts = [
        'deduction_details' => 'array',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function period()
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isPendingPayment(): bool
    {
        return $this->status === 'pending_payment';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isSettled(): bool
    {
        return $this->isPaid() || $this->isFailed();
    }

    /**
     * Total employer cost for this item (gross + employer contributions).
     */
    public function employerCost(): float
    {
        return round(
            (float) $this->gross_pay
            + (float) $this->nssf_employer
            + (float) $this->shif_employer
            + (float) $this->housing_levy_employer,
            2
        );
    }

    /**
     * Formatted deduction breakdown for display / payslip.
     */
    public function deductionSummary(): array
    {
        return [
            'gross'           => (float) $this->gross_pay,
            'nssf_employee'   => (float) $this->nssf_employee,
            'shif_employee'   => (float) $this->shif_employee,
            'housing_levy_employee' => (float) $this->housing_levy_employee,
            'paye'            => (float) $this->paye,
            'total_deductions'=> (float) $this->total_deductions,
            'net_pay'         => (float) $this->net_pay,
            'nssf_employer'   => (float) $this->nssf_employer,
            'shif_employer'   => (float) $this->shif_employer,
            'housing_levy_employer' => (float) $this->housing_levy_employer,
        ];
    }
}
