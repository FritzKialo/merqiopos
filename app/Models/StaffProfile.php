<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StaffProfile extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'user_id', 'business_id',
        'pay_type', 'retainer_amount', 'commission_rate',
        'kra_pin', 'nssf_no', 'shif_no', 'id_number',
        'job_title', 'department',
        'employment_date', 'termination_date',
        'deduction_overrides',
        'helb_deduction', 'helb_account_number',
        'credit_limit', 'mpesa_phone',
    ];

    protected $casts = [
        'retainer_amount'    => 'decimal:2',
        'commission_rate'    => 'decimal:2',
        'employment_date'    => 'date',
        'termination_date'   => 'date',
        'deduction_overrides' => 'array',
        'credit_limit'       => 'decimal:2',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isRetainer(): bool
    {
        return $this->pay_type === 'retainer';
    }

    public function isCommission(): bool
    {
        return $this->pay_type === 'commission';
    }

    public function isHybrid(): bool
    {
        return $this->pay_type === 'hybrid';
    }

    public function isActive(): bool
    {
        return $this->termination_date === null
            || $this->termination_date->isFuture();
    }

    /**
     * Resolve whether a specific deduction applies to this employee.
     * Employee-level override takes priority; falls back to business payroll_settings.
     */
    public function deductionEnabled(string $type): bool
    {
        $overrides = $this->deduction_overrides ?? [];

        if (array_key_exists($type, $overrides)) {
            return (bool) $overrides[$type];
        }

        return $this->business->isDeductionEnabled($type);
    }

    /**
     * Gross pay for a given period.
     * Commission-only: commission_rate % of $salesAmount.
     * Retainer: retainer_amount.
     * Hybrid: retainer + commission on sales.
     */
    public function grossPay(float $salesAmount = 0): float
    {
        return match ($this->pay_type) {
            'retainer'   => (float) $this->retainer_amount,
            'commission' => round($salesAmount * $this->commission_rate / 100, 2),
            'hybrid'     => round(
                (float) $this->retainer_amount + $salesAmount * $this->commission_rate / 100,
                2
            ),
        };
    }

    // ── Static helpers ────────────────────────────────────────────────────────

    public static function payTypes(): array
    {
        return [
            'retainer'   => 'Retainer (fixed monthly)',
            'commission' => 'Commission only',
            'hybrid'     => 'Hybrid (retainer + commission)',
        ];
    }
}
