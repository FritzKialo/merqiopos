<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PayrollPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'period_start', 'period_end',
        'pay_cycle', 'status',
        'approved_at', 'paid_at',
        'total_gross',
        'total_nssf_ee', 'total_nssf_er',
        'total_shif_ee', 'total_shif_er',
        'total_paye', 'total_net',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end'   => 'date',
        'approved_at'  => 'datetime',
        'paid_at'      => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function items()
    {
        return $this->hasMany(PayrollItem::class);
    }

    // ── Status helpers ────────────────────────────────────────────────────────

    public function isDraft(): bool    { return $this->status === 'draft'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isPaid(): bool     { return $this->status === 'paid'; }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft'    => 'Draft',
            'approved' => 'Approved',
            'paid'     => 'Paid',
            default    => ucfirst($this->status),
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'draft'    => 'badge-secondary',
            'approved' => 'badge-warning',
            'paid'     => 'badge-success',
            default    => 'badge-secondary',
        };
    }

    // ── Expense posting ───────────────────────────────────────────────────────

    /**
     * Post this period's payroll as a business expense, once. The amount is
     * the employer's real cost — gross pay plus employer contributions — not
     * net pay. The three places that closed a period (bulk mark-paid, the
     * M-Pesa payout job and its result callback) each had their own copy of
     * this, two of them still posting net pay only.
     */
    public function postExpenseOnce(?int $userId = null): void
    {
        $exists = \App\Models\Expense::where('business_id', $this->business_id)
            ->where('reference', 'PAYROLL-' . $this->id)
            ->exists();
        if ($exists) return;

        $category = \App\Models\ExpenseCategory::firstOrCreate(
            ['business_id' => $this->business_id, 'name' => 'Salaries & Wages'],
            ['description' => 'Payroll disbursements']
        );

        $paid   = $this->items()->where('status', 'paid')->get();
        $amount = $paid->sum(fn ($i) => $i->employerCost()) ?: $this->total_net;

        if ($userId === null) {
            $owner  = $this->business?->users()->wherePivot('role', 'owner')->first();
            $userId = $owner?->id ?? $this->business?->organization?->owner_user_id;
        }

        \App\Models\Expense::create([
            'business_id'         => $this->business_id,
            'expense_category_id' => $category->id,
            'user_id'             => $userId,
            'title'               => 'Payroll: ' . $this->period_start->format('d M') . ' – ' . $this->period_end->format('d M Y'),
            'description'         => $this->items()->count() . ' employee(s), ' . $paid->count() . ' paid. Amount = gross pay + employer contributions.',
            'amount'              => $amount,
            'payment_method'      => 'bank_transfer',
            'reference'           => 'PAYROLL-' . $this->id,
            'expense_date'        => ($this->paid_at ?? now())->toDateString(),
        ]);
    }

    // ── Totals ────────────────────────────────────────────────────────────────

    /**
     * Recompute summary totals from items and save.
     */
    public function recalculateTotals(): void
    {
        $items = $this->items;

        $this->update([
            'total_gross'   => $items->sum('gross_pay'),
            'total_nssf_ee' => $items->sum('nssf_employee'),
            'total_nssf_er' => $items->sum('nssf_employer'),
            'total_shif_ee' => $items->sum('shif_employee'),
            'total_shif_er' => $items->sum('shif_employer'),
            'total_paye'    => $items->sum('paye'),
            'total_net'     => $items->sum('net_pay'),
        ]);
    }

    /**
     * If every item in this approved period is now paid, close the period.
     * Returns true when the period was just closed.
     */
    public function checkAndClose(): bool
    {
        if (! $this->isApproved()) {
            return false;
        }

        $remaining = $this->items()->where('status', 'pending')->count();

        if ($remaining > 0) {
            return false;
        }

        $this->update([
            'status'  => 'paid',
            'paid_at' => now(),
        ]);

        return true;
    }

    // ── Scope ─────────────────────────────────────────────────────────────────

    public function scopeForBusiness($query, int $businessId)
    {
        return $query->where('business_id', $businessId);
    }
}
