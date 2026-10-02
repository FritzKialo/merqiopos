<?php

namespace Tests\Feature;

use Tests\Feature\Helpers\CreatesOrganization;
use Tests\TestCase;

class PayrollSettingsTest extends TestCase
{
    use CreatesOrganization;

    /** @test */
    public function owner_can_view_payroll_settings(): void
    {
        [$owner] = $this->scaffoldOrg('growth');

        $this->actingAs($owner)
            ->get(route('settings.payroll'))
            ->assertOk()
            ->assertViewIs('settings.payroll');
    }

    /** @test */
    public function non_owner_cannot_access_payroll_settings(): void
    {
        [$owner, , $business] = $this->scaffoldOrg('growth');

        $staff = \App\Models\User::factory()->cashier()->create([
            'organization_id' => $owner->organization_id,
        ]);
        $business->users()->attach($staff->id, ['role' => 'cashier']);
        session(['active_business_id' => $business->id]);

        // RequireRole (the 'role:' middleware) always redirects on a role mismatch — it
        // never returns 403 — so that's the real, intentional behavior to assert here.
        $this->actingAs($staff)
            ->get(route('settings.payroll'))
            ->assertRedirect(route('dashboard'));
    }

    /** @test */
    public function owner_can_save_payroll_settings(): void
    {
        [$owner, , $business] = $this->scaffoldOrg('growth');

        $this->actingAs($owner)->post(route('settings.payroll.update'), [
            'enabled'      => '1',
            'pay_cycle'    => 'monthly',
            'employer_pin' => 'A001234567T',
            'deduct_paye'  => '1',
            'deduct_nssf'  => '1',
            'deduct_shif'  => '0',
        ]);

        $settings = $business->fresh()->payroll_settings;

        $this->assertTrue($settings['enabled']);
        $this->assertEquals('monthly', $settings['pay_cycle']);
        $this->assertEquals('A001234567T', $settings['employer_pin']);
        $this->assertTrue($settings['deductions']['paye']);
        $this->assertTrue($settings['deductions']['nssf']);
        $this->assertFalse($settings['deductions']['shif']);
    }

    /** @test */
    public function pay_cycle_is_required(): void
    {
        [$owner] = $this->scaffoldOrg('growth');

        $this->actingAs($owner)
            ->post(route('settings.payroll.update'), [
                'enabled'   => '1',
                'pay_cycle' => '',
            ])
            ->assertSessionHasErrors('pay_cycle');
    }

    /** @test */
    public function invalid_pay_cycle_is_rejected(): void
    {
        [$owner] = $this->scaffoldOrg('growth');

        $this->actingAs($owner)
            ->post(route('settings.payroll.update'), [
                'pay_cycle' => 'quarterly',
            ])
            ->assertSessionHasErrors('pay_cycle');
    }

    /** @test */
    public function solo_plan_can_still_access_payroll_settings(): void
    {
        // config/plans.php grants 'payroll' on every plan including solo (only
        // payroll_statutory/p9_forms are Growth+) — this used to assert the opposite
        // (a redirect to the subscription page), which no longer matches the actual,
        // intentional plan config and was failing for the wrong reason.
        [$owner] = $this->scaffoldOrg('solo');

        $this->actingAs($owner)
            ->get(route('settings.payroll'))
            ->assertOk();
    }
}
