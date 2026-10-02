<?php

namespace App\Services;

use App\Models\Business;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\Sale;
use App\Models\SalaryAdvance;
use App\Models\StaffProfile;
use App\Models\User;

/**
 * Kenyan statutory payroll calculations.
 *
 * NSSF  — NSSF Act 2013 (Tier I + II, 6% each side; limits from Feb 2026)
 * SHIF  — Social Health Insurance Fund, 2.75% of gross, EMPLOYEE only (min KSh 300)
 * AHL   — Affordable Housing Levy, 1.5% of gross employee + 1.5% employer
 * PAYE  — KRA progressive bands, personal relief KSh 2,400/mo. NSSF, SHIF and
 *         the housing levy are all deductible before tax.
 *
 * Statutory rates change — verify these constants with KRA / NSSF / SHA
 * each February and July.
 *
 * Each calculation method returns a breakdown array so callers can render
 * payslips or audit trails without re-running the math.
 */
class PayrollCalculationService
{
    // ── NSSF constants (NSSF Act 2013) ───────────────────────────────────────

    const NSSF_RATE        = 0.06;   // 6% each side
    const NSSF_LEL         = 9000;   // Lower Earnings Limit (Feb 2026, year 4)
    const NSSF_UEL         = 108000; // Upper Earnings Limit (Feb 2026, year 4)

    // ── SHIF constants ────────────────────────────────────────────────────────

    const SHIF_RATE        = 0.0275; // 2.75% of gross, employee only
    const SHIF_MINIMUM     = 300;    // minimum monthly contribution

    // ── Affordable Housing Levy ───────────────────────────────────────────────

    const HOUSING_LEVY_RATE = 0.015; // 1.5% employee + 1.5% employer

    // ── PAYE bands (monthly, KSh) — KRA 2024/25 ──────────────────────────────
    // Defined as successive band widths so there are no off-by-one issues.
    // The last band has PHP_INT_MAX width (catches everything above 800,000).

    const PAYE_BANDS = [
        ['width' =>  24000, 'rate' => 0.10],   // first 24,000
        ['width' =>   8333, 'rate' => 0.25],   // next 8,333  (up to 32,333)
        ['width' => 467667, 'rate' => 0.30],   // next 467,667 (up to 500,000)
        ['width' => 300000, 'rate' => 0.325],  // next 300,000 (up to 800,000)
        ['width' => PHP_INT_MAX, 'rate' => 0.35], // above 800,000
    ];

    const PAYE_PERSONAL_RELIEF = 2400; // KSh/month

    // ── Public entry point ────────────────────────────────────────────────────

    /**
     * Calculate and persist a PayrollItem for one employee in a period.
     * Recalculates if an item already exists (idempotent).
     */
    public function calculateItem(
        PayrollPeriod $period,
        User $user
    ): PayrollItem {
        $business = $period->business;
        $profile  = $user->staffProfileFor($business->id);

        if (!$profile) {
            throw new \RuntimeException(
                "No staff profile found for user #{$user->id} in business #{$business->id}"
            );
        }

        $salesAmount = $this->periodSales($user, $business->id, $period);
        $breakdown   = $this->compute($profile, $business, $salesAmount);

        $item = PayrollItem::updateOrCreate(
            [
                'payroll_period_id' => $period->id,
                'user_id'           => $user->id,
            ],
            [
                'business_id'       => $business->id,
                'pay_type'          => $profile->pay_type,
                'retainer_amount'   => $breakdown['retainer_amount'],
                'commission_rate'   => $breakdown['commission_rate'],
                'commission_sales'  => $breakdown['commission_sales'],
                'gross_pay'         => $breakdown['gross'],
                'nssf_employee'     => $breakdown['nssf_employee'],
                'nssf_employer'     => $breakdown['nssf_employer'],
                'shif_employee'     => $breakdown['shif_employee'],
                'housing_levy_employee' => $breakdown['housing_levy_employee'],
                'housing_levy_employer' => $breakdown['housing_levy_employer'],
                'shif_employer'     => $breakdown['shif_employer'],
                'helb'              => $breakdown['helb'],
                'paye'              => $breakdown['paye'],
                'total_deductions'  => $breakdown['total_deductions'],
                'net_pay'           => $breakdown['net_pay'],
                'deduction_details' => $breakdown,
                'status'            => 'pending',
            ]
        );

        // Deduct any approved salary advances for this employee. Also re-include
        // advances already marked 'deducted' against THIS period: calculateItem()
        // is re-run on every recalculation of a draft period (updateOrCreate above
        // resets net_pay to the pre-advance amount each time), and without this,
        // a second recalculation would silently drop the advance deduction —
        // since the advance is no longer 'approved' — restoring the employee's
        // full pay while the advance stays marked as already deducted.
        $advances = SalaryAdvance::where('user_id', $user->id)
            ->where('business_id', $business->id)
            ->where(function ($q) use ($period) {
                $q->where('status', 'approved')
                  ->orWhere(function ($q2) use ($period) {
                      $q2->where('status', 'deducted')
                         ->where('deduction_period_id', $period->id);
                  });
            })
            ->get();

        if ($advances->isNotEmpty()) {
            $totalAdvance = $advances->sum('amount');
            $details      = $item->deduction_details ?? [];
            $details['salary_advances']    = $advances->map(fn($a) => ['id' => $a->id, 'amount' => $a->amount])->toArray();
            $details['total_advance_deduction'] = $totalAdvance;

            $newNet = max(0, $item->net_pay - $totalAdvance);
            $item->update([
                'net_pay'           => $newNet,
                'deduction_details' => $details,
            ]);

            $advances->each(fn($a) => $a->update([
                'status'              => 'deducted',
                'deducted_at'         => now(),
                'deduction_period_id' => $period->id,
            ]));
        }

        return $item;
    }

    /**
     * Calculate all employees for a period and update period totals.
     * Returns the list of PayrollItems created/updated.
     */
    public function calculatePeriod(PayrollPeriod $period): array
    {
        $business = $period->business->load('users');
        $items    = [];

        foreach ($business->users as $user) {
            $profile = $user->staffProfileFor($business->id);
            if (!$profile || !$profile->isActive()) continue;

            $items[] = $this->calculateItem($period, $user);
        }

        $period->recalculateTotals();

        return $items;
    }

    // ── Core computation ──────────────────────────────────────────────────────

    /**
     * Compute full deduction breakdown for a staff profile.
     * Returns an array suitable for storage in deduction_details.
     */
    public function compute(
        StaffProfile $profile,
        Business $business,
        float $salesAmount = 0
    ): array {
        $gross = max(0, $profile->grossPay($salesAmount));

        $nssfEnabled = $profile->deductionEnabled('nssf');
        $shifEnabled = $profile->deductionEnabled('shif');
        $payeEnabled = $profile->deductionEnabled('paye');

        // NSSF
        [$nssfEE, $nssfER, $nssfDetails] = $nssfEnabled
            ? $this->calculateNssf($gross)
            : [0, 0, ['tier1_ee' => 0, 'tier2_ee' => 0, 'tier1_er' => 0, 'tier2_er' => 0]];

        // SHIF
        [$shifEE, $shifER] = $shifEnabled
            ? $this->calculateShif($gross)
            : [0, 0];

        // Affordable Housing Levy
        $housingEnabled = $profile->deductionEnabled('housing_levy');
        $housingEE = $housingEnabled ? round($gross * self::HOUSING_LEVY_RATE, 2) : 0;
        $housingER = $housingEE;

        // PAYE — taxable income = gross less NSSF, SHIF and housing levy
        // (all three are deductible before tax)
        $taxableIncome = max(0, $gross - $nssfEE - $shifEE - $housingEE);
        $paye = $payeEnabled
            ? $this->calculatePaye($taxableIncome)
            : 0;

        // HELB — fixed monthly deduction from staff profile
        $helb = round((float) ($profile->helb_deduction ?? 0), 2);

        $totalDeductions = round($nssfEE + $shifEE + $housingEE + $paye + $helb, 2);
        $netPay          = round($gross - $totalDeductions, 2);

        return [
            // Inputs
            'pay_type'         => $profile->pay_type,
            'retainer_amount'  => (float) $profile->retainer_amount,
            'commission_rate'  => (float) $profile->commission_rate,
            'commission_sales' => round($salesAmount, 2),

            // Gross
            'gross'            => round($gross, 2),

            // Employee deductions
            'nssf_employee'    => round($nssfEE, 2),
            'shif_employee'    => round($shifEE, 2),
            'housing_levy_employee' => round($housingEE, 2),
            'paye'             => round($paye, 2),
            'helb'             => $helb,
            'total_deductions' => $totalDeductions,

            // Employer contributions
            'nssf_employer'    => round($nssfER, 2),
            'shif_employer'    => round($shifER, 2),
            'housing_levy_employer' => round($housingER, 2),

            // Net
            'net_pay'          => max(0, $netPay),

            // Audit trail
            'taxable_income'   => round($taxableIncome, 2),
            'nssf_details'     => $nssfDetails,
            'nssf_enabled'     => $nssfEnabled,
            'shif_enabled'     => $shifEnabled,
            'housing_levy_enabled' => $housingEnabled,
            'paye_enabled'     => $payeEnabled,
        ];
    }

    // ── NSSF ──────────────────────────────────────────────────────────────────

    /**
     * NSSF Act 2013 — Tier I + Tier II at 6% each side.
     *
     * Tier I  = 6% of min(gross, LEL)          [LEL = 7,000]
     * Tier II = 6% of (min(gross, UEL) - LEL)  [UEL = 36,000], only if gross > LEL
     *
     * Returns [employee_total, employer_total, details_array]
     */
    public function calculateNssf(float $gross): array
    {
        // Tier I
        $tier1Base = min($gross, self::NSSF_LEL);
        $tier1EE   = round($tier1Base * self::NSSF_RATE, 2);
        $tier1ER   = $tier1EE;

        // Tier II
        $tier2Base = max(0, min($gross, self::NSSF_UEL) - self::NSSF_LEL);
        $tier2EE   = round($tier2Base * self::NSSF_RATE, 2);
        $tier2ER   = $tier2EE;

        $totalEE = round($tier1EE + $tier2EE, 2);
        $totalER = round($tier1ER + $tier2ER, 2);

        return [
            $totalEE,
            $totalER,
            [
                'tier1_base' => $tier1Base,
                'tier1_ee'   => $tier1EE,
                'tier1_er'   => $tier1ER,
                'tier2_base' => $tier2Base,
                'tier2_ee'   => $tier2EE,
                'tier2_er'   => $tier2ER,
                'total_ee'   => $totalEE,
                'total_er'   => $totalER,
            ],
        ];
    }

    // ── SHIF ──────────────────────────────────────────────────────────────────

    /**
     * SHIF — 2.75% of gross (minimum KSh 300), paid by the employee only;
     * the employer makes no SHIF contribution. Returns [employee, employer].
     */
    public function calculateShif(float $gross): array
    {
        if ($gross <= 0) return [0, 0];
        $ee = round(max($gross * self::SHIF_RATE, self::SHIF_MINIMUM), 2);

        return [$ee, 0];
    }

    // ── PAYE ──────────────────────────────────────────────────────────────────

    /**
     * KRA progressive PAYE (monthly, 2024/25).
     * Input: taxable income = gross less NSSF, SHIF and housing levy.
     * Returns PAYE after personal relief (minimum 0).
     */
    public function calculatePaye(float $taxableIncome): float
    {
        if ($taxableIncome <= 0) return 0;

        $tax = 0;

        $remaining = $taxableIncome;

        foreach (self::PAYE_BANDS as $band) {
            if ($remaining <= 0) break;

            $inBand    = min($remaining, $band['width']);
            $tax      += $inBand * $band['rate'];
            $remaining -= $inBand;
        }

        // Apply personal relief
        $paye = max(0, round($tax - self::PAYE_PERSONAL_RELIEF, 2));

        return $paye;
    }

    /**
     * PAYE band-by-band breakdown for display / P9 form.
     */
    public function payeBandBreakdown(float $taxableIncome): array
    {
        $breakdown = [];
        $remaining = $taxableIncome;

        $from = 1;

        foreach (self::PAYE_BANDS as $band) {
            if ($remaining <= 0) break;

            $inBand = min($remaining, $band['width']);
            if ($inBand <= 0) continue;

            $to = $band['width'] === PHP_INT_MAX ? '∞' : ($from + $band['width'] - 1);

            $breakdown[] = [
                'from'   => $from,
                'to'     => $to,
                'rate'   => ($band['rate'] * 100) . '%',
                'amount' => round($inBand, 2),
                'tax'    => round($inBand * $band['rate'], 2),
            ];

            $from      += $band['width'];
            $remaining -= $inBand;
        }

        return $breakdown;
    }

    // ── Commission helper ─────────────────────────────────────────────────────

    /**
     * Total sales amount attributed to a user in a business during a period.
     */
    public function periodSales(User $user, int $businessId, PayrollPeriod $period): float
    {
        return (float) Sale::where('user_id',     $user->id)
            ->where('business_id', $businessId)
            ->whereBetween('created_at', [
                $period->period_start->startOfDay(),
                $period->period_end->endOfDay(),
            ])
            ->sum('total_amount');
    }
}
