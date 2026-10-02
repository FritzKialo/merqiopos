<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\StaffProfile;
use App\Services\PayrollCalculationService;
use Tests\TestCase;

class PayrollCalculationServiceTest extends TestCase
{
    private PayrollCalculationService $svc;
    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc      = new PayrollCalculationService();
        $this->business = Business::factory()->withPayroll()->create();
    }

    // ── NSSF ──────────────────────────────────────────────────────────────────

    /** @test */
    public function nssf_is_zero_for_zero_gross(): void
    {
        [$ee, $er] = $this->svc->calculateNssf(0);
        $this->assertEquals(0, $ee);
        $this->assertEquals(0, $er);
    }

    /** @test */
    public function nssf_tier1_only_below_lel(): void
    {
        // gross = 5,000 < LEL (7,000) → only tier I applies
        [$ee, $er, $det] = $this->svc->calculateNssf(5000);
        $this->assertEquals(300.00, $ee);   // 5,000 × 6%
        $this->assertEquals(300.00, $er);
        $this->assertEquals(0.00,   $det['tier2_ee']);
    }

    /** @test */
    public function nssf_at_lel_boundary(): void
    {
        [$ee] = $this->svc->calculateNssf(7000);
        $this->assertEquals(420.00, $ee);   // 7,000 × 6%
    }

    /** @test */
    public function nssf_tier1_and_tier2_above_lel(): void
    {
        // gross = 30,000, LEL/UEL = 9,000/108,000 (Feb 2026, year 4 schedule) →
        // tier1 = 9,000 × 6% = 540, tier2 = 21,000 × 6% = 1,260
        [$ee, $er, $det] = $this->svc->calculateNssf(30000);
        $this->assertEquals(540.00,  $det['tier1_ee']);
        $this->assertEquals(1260.00, $det['tier2_ee']);
        $this->assertEquals(1800.00, $ee);
        $this->assertEquals(1800.00, $er);
    }

    /** @test */
    public function nssf_caps_at_uel(): void
    {
        // gross = 150,000 → capped at UEL 108,000
        // tier1 = 540, tier2 = (108,000-9,000) × 6% = 5,940 → total = 6,480
        [$ee] = $this->svc->calculateNssf(150000);
        $this->assertEquals(6480.00, $ee);
    }

    // ── SHIF ──────────────────────────────────────────────────────────────────

    /** @test */
    public function shif_is_2_75_percent_of_gross_employee_only(): void
    {
        // SHIF is employee-only — the employer makes no contribution.
        [$ee, $er] = $this->svc->calculateShif(40000);
        $this->assertEquals(1100.00, $ee);  // 40,000 × 2.75%
        $this->assertEquals(0, $er);
    }

    // ── PAYE ──────────────────────────────────────────────────────────────────

    /** @test */
    public function paye_is_zero_within_personal_relief(): void
    {
        // taxable = 10,000: PAYE = 10,000 × 10% = 1,000 − 2,400 relief = 0 (floored)
        $paye = $this->svc->calculatePaye(10000);
        $this->assertEquals(0, $paye);
    }

    /** @test */
    public function paye_first_band_at_24000(): void
    {
        // taxable = 24,000: gross tax = 24,000 × 10% = 2,400 − 2,400 relief = 0
        $paye = $this->svc->calculatePaye(24000);
        $this->assertEquals(0, $paye);
    }

    /** @test */
    public function paye_crosses_first_band(): void
    {
        // taxable = 30,000:
        //   first 24,000 @ 10% = 2,400
        //   next  6,000  @ 25% = 1,500   → gross = 3,900 − 2,400 relief = 1,500
        $paye = $this->svc->calculatePaye(30000);
        $this->assertEquals(1500.00, $paye);
    }

    /** @test */
    public function paye_is_never_negative(): void
    {
        $paye = $this->svc->calculatePaye(5000);
        $this->assertEquals(0, $paye);
    }

    // ── Compute (end-to-end) ─────────────────────────────────────────────────

    /** @test */
    public function compute_retainer_50000_with_all_deductions(): void
    {
        $profile = StaffProfile::factory()->retainer(50000)->make([
            'business_id' => $this->business->id,
        ]);
        $profile->setRelation('business', $this->business);

        $result = $this->svc->compute($profile, $this->business, 0);

        // NSSF (LEL/UEL = 9,000/108,000): tier1=9,000×6%=540 + tier2=(41,000×6%=2,460) = 3,000
        $this->assertEquals(3000.00, $result['nssf_employee']);
        // SHIF: 50,000 × 2.75% = 1,375
        $this->assertEquals(1375.00, $result['shif_employee']);
        // PAYE: taxable = 50,000 - 3,000 - 1,375 = 45,625
        //   first 24,000 @10% = 2,400
        //   next  8,333  @25% = 2,083.25
        //   next  13,292 @30% = 3,987.60 → gross = 8,470.85 − 2,400 relief = 6,070.85
        $this->assertEquals(6070.85, round($result['paye'], 2));

        $expectedDeductions = round(3000 + 1375 + $result['paye'], 2);
        $this->assertEquals($expectedDeductions, $result['total_deductions']);
        $this->assertEquals(round(50000 - $expectedDeductions, 2), $result['net_pay']);
    }

    /** @test */
    public function compute_honours_per_business_deduction_toggle(): void
    {
        $business = Business::factory()->create([
            'payroll_settings' => [
                'enabled'    => true,
                'pay_cycle'  => 'monthly',
                'deductions' => ['paye' => false, 'nssf' => true, 'shif' => true],
            ],
        ]);

        $profile = StaffProfile::factory()->retainer(50000)->make([
            'business_id' => $business->id,
        ]);
        $profile->setRelation('business', $business);

        $result = $this->svc->compute($profile, $business, 0);

        $this->assertEquals(0, $result['paye']);
        $this->assertGreaterThan(0, $result['nssf_employee']);
    }

    /** @test */
    public function compute_honours_employee_deduction_override(): void
    {
        $profile = StaffProfile::factory()->retainer(50000)->make([
            'business_id'        => $this->business->id,
            'deduction_overrides' => ['nssf' => false],
        ]);
        $profile->setRelation('business', $this->business);

        $result = $this->svc->compute($profile, $this->business, 0);

        $this->assertEquals(0, $result['nssf_employee']);
        $this->assertEquals(0, $result['nssf_employer']);
        // The override only excludes NSSF — SHIF (50,000 × 2.75% = 1,375) still applies
        // normally, so taxable income is gross less SHIF alone, not full gross.
        $this->assertEquals(48625, $result['taxable_income']);
    }

    /** @test */
    public function commission_gross_is_rate_times_sales(): void
    {
        $profile = StaffProfile::factory()->commission(10.0)->make([
            'business_id' => $this->business->id,
        ]);
        $profile->setRelation('business', $this->business);

        $result = $this->svc->compute($profile, $this->business, 200000);

        $this->assertEquals(20000.00, $result['gross']);  // 10% × 200,000
    }

    /** @test */
    public function hybrid_gross_is_retainer_plus_commission(): void
    {
        $profile = StaffProfile::factory()->hybrid(20000, 5.0)->make([
            'business_id' => $this->business->id,
        ]);
        $profile->setRelation('business', $this->business);

        $result = $this->svc->compute($profile, $this->business, 100000);

        $this->assertEquals(25000.00, $result['gross']);  // 20,000 + 5% × 100,000
    }
}
