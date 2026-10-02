<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Tests\Feature\Helpers\CreatesOrganization;
use Tests\TestCase;

class OrganizationSubscriptionTest extends TestCase
{
    use CreatesOrganization;

    /** @test */
    public function active_organization_has_access(): void
    {
        [$owner, $org] = $this->scaffoldOrg('growth');

        $this->assertTrue($org->isActive());
    }

    /** @test */
    public function trial_organization_is_on_trial_and_considered_active(): void
    {
        $owner = User::factory()->owner()->create();
        $org   = Organization::factory()->onTrial()->create([
            'owner_user_id' => $owner->id,
        ]);

        $this->assertTrue($org->isOnTrial());
        // isActive() returns true for trials (trial is still access-permitted)
        $this->assertTrue($org->isActive());
    }

    /** @test */
    public function growth_plan_has_payroll_feature(): void
    {
        [, $org] = $this->scaffoldOrg('growth');
        $this->assertTrue($org->hasFeature('payroll'));
    }

    /** @test */
    public function solo_plan_has_payroll_feature(): void
    {
        // config/plans.php grants 'payroll' on every plan including solo — only
        // payroll_statutory/p9_forms/cross_store_reports are Growth+. This used to
        // assert the opposite, which no longer matches that config.
        [, $org] = $this->scaffoldOrg('solo');
        $this->assertTrue($org->hasFeature('payroll'));
    }

    /** @test */
    public function growth_plan_has_p9_forms_feature(): void
    {
        [, $org] = $this->scaffoldOrg('growth');
        $this->assertTrue($org->hasFeature('p9_forms'));
    }

    /** @test */
    public function solo_plan_does_not_have_p9_forms(): void
    {
        [, $org] = $this->scaffoldOrg('solo');
        $this->assertFalse($org->hasFeature('p9_forms'));
    }

    /** @test */
    public function org_dashboard_requires_auth(): void
    {
        $this->get(route('org.dashboard'))->assertRedirect(route('login'));
    }

    /** @test */
    public function owner_can_view_org_dashboard(): void
    {
        [$owner] = $this->scaffoldOrg('growth');

        $this->actingAs($owner)
            ->get(route('org.dashboard'))
            ->assertOk()
            ->assertViewIs('org.dashboard');
    }

    /** @test */
    public function non_owner_is_redirected_from_org_dashboard(): void
    {
        [$owner, , $business] = $this->scaffoldOrg('growth');

        $staff = User::factory()->cashier()->create();
        $business->users()->attach($staff->id, ['role' => 'cashier']);
        session(['active_business_id' => $business->id]);

        $this->actingAs($staff)
            ->get(route('org.dashboard'))
            ->assertRedirect(route('dashboard'));
    }

    /** @test */
    public function enterprise_plan_has_all_features(): void
    {
        // Was scale_plan_has_all_features — the Scale org tier no longer
        // exists after the 3-tier pricing restructure (config/plans.php).
        // Enterprise is now the top org tier with every feature enabled,
        // including api_access (Enterprise-only; Growth doesn't have it).
        [, $org] = $this->scaffoldOrg('enterprise');

        foreach (['payroll', 'payroll_statutory', 'p9_forms', 'cross_store_reports', 'api_access'] as $feature) {
            $this->assertTrue($org->hasFeature($feature), "Expected enterprise plan to have feature: {$feature}");
        }
    }

    /**
     * Regression: an org's status flag stays 'active' even after its paid
     * Subscription row expires (nothing flips it automatically) — so
     * isActive() alone is not a safe proxy for "has working access", even
     * though it looks true. hasActiveAccess() is what should be used
     * wherever real access matters (CheckSubscription, Settings -> Subscription).
     */
    /** @test */
    public function an_active_status_org_with_an_expired_subscription_has_no_real_access(): void
    {
        [$owner, $org] = $this->scaffoldOrg('growth');
        $org->subscriptions()->update(['end_date' => now()->subDay()->toDateString()]);

        $this->assertTrue($org->isActive(), 'status flag is untouched by expiry');
        $this->assertFalse($org->hasActiveAccess(), 'but real access should be gone');
    }

    /** @test */
    public function such_an_org_is_gated_by_the_subscription_middleware(): void
    {
        [$owner, $org] = $this->scaffoldOrg('growth');
        $org->subscriptions()->update(['end_date' => now()->subDay()->toDateString()]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertRedirect(route('subscription.required'));
    }

    /** @test */
    public function the_settings_subscription_page_shows_expired_not_active_for_such_an_org(): void
    {
        [$owner, $org] = $this->scaffoldOrg('growth');
        $org->subscriptions()->update(['end_date' => now()->subDay()->toDateString()]);

        $response = $this->actingAs($owner)->get(route('settings.subscription'));

        $response->assertOk();
        $response->assertSee('Subscription Expired');
        $response->assertDontSee('Active subscription');
    }
}
