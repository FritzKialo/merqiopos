<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\StaffProfile;
use Tests\TestCase;

class StaffProfileTest extends TestCase
{
    // ── grossPay ──────────────────────────────────────────────────────────────

    /** @test */
    public function retainer_gross_pay_ignores_sales(): void
    {
        $profile = StaffProfile::factory()->retainer(45000)->make();
        $this->assertEquals(45000.0, $profile->grossPay(999999));
    }

    /** @test */
    public function commission_gross_pay_is_rate_times_sales(): void
    {
        $profile = StaffProfile::factory()->commission(8.0)->make();
        $this->assertEquals(8000.0, $profile->grossPay(100000));
    }

    /** @test */
    public function hybrid_gross_pay_adds_retainer_and_commission(): void
    {
        $profile = StaffProfile::factory()->hybrid(15000, 4.0)->make();
        $this->assertEquals(15000 + 4000.0, $profile->grossPay(100000));
    }

    // ── deductionEnabled ──────────────────────────────────────────────────────

    /** @test */
    public function deduction_enabled_falls_back_to_business_setting(): void
    {
        $business = Business::factory()->create([
            'payroll_settings' => [
                'deductions' => ['paye' => true, 'nssf' => false, 'shif' => true],
            ],
        ]);
        $profile = StaffProfile::factory()->make([
            'business_id'        => $business->id,
            'deduction_overrides' => null,
        ]);
        $profile->setRelation('business', $business);

        $this->assertTrue($profile->deductionEnabled('paye'));
        $this->assertFalse($profile->deductionEnabled('nssf'));
        $this->assertTrue($profile->deductionEnabled('shif'));
    }

    /** @test */
    public function employee_override_takes_precedence_over_business_setting(): void
    {
        $business = Business::factory()->create([
            'payroll_settings' => [
                'deductions' => ['paye' => true, 'nssf' => true, 'shif' => true],
            ],
        ]);
        $profile = StaffProfile::factory()->make([
            'business_id'        => $business->id,
            'deduction_overrides' => ['nssf' => false],
        ]);
        $profile->setRelation('business', $business);

        // Business says NSSF enabled; employee override says disabled
        $this->assertFalse($profile->deductionEnabled('nssf'));
        // Other deductions unaffected
        $this->assertTrue($profile->deductionEnabled('paye'));
    }

    // ── isActive ──────────────────────────────────────────────────────────────

    /** @test */
    public function profile_is_active_without_termination_date(): void
    {
        $profile = StaffProfile::factory()->make(['termination_date' => null]);
        $this->assertTrue($profile->isActive());
    }

    /** @test */
    public function profile_is_inactive_after_termination(): void
    {
        $profile = StaffProfile::factory()->make([
            'termination_date' => now()->subDay(),
        ]);
        $this->assertFalse($profile->isActive());
    }

    /** @test */
    public function profile_is_inactive_on_termination_day(): void
    {
        // termination_date = today is not in the future, so isActive() returns false
        $profile = StaffProfile::factory()->make([
            'termination_date' => today(),
        ]);
        $this->assertFalse($profile->isActive());
    }
}
