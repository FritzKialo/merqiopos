<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\ErrorLog;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SupportCannedReply;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Helpers\CreatesOrganization;
use Tests\TestCase;

/**
 * A live sweep of the super-admin panel: every action checked against real DB state
 * (not just a status code), plus access control for non-admins and logged-out visitors.
 */
class AdminPanelTest extends TestCase
{
    use CreatesOrganization;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'is_super_admin'          => true,
            'two_factor_enabled'      => true,
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /** Requests as the admin with a fresh 'sudo' (recent 2FA) window. */
    private function asAdmin()
    {
        return $this->actingAs($this->admin)->withSession(['2fa_verified' => true, '2fa_last_verified' => time()]);
    }

    // ── Access control ──────────────────────────────────────────────────────

    /** @test */
    public function a_non_admin_cannot_reach_the_admin_panel(): void
    {
        [$owner] = $this->scaffoldOrg();

        $this->actingAs($owner)->get(route('admin.dashboard'))->assertForbidden();
    }

    /** @test */
    public function a_logged_out_visitor_is_redirected_from_the_admin_panel(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    /** @test */
    public function admin_without_two_factor_is_sent_to_set_it_up_not_given_access(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true, 'two_factor_enabled' => false]);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertRedirect(route('settings.2fa.setup'));
    }

    // ── Businesses ────────────────────────────────────────────────────────────

    /** @test */
    public function admin_can_suspend_and_reactivate_a_business(): void
    {
        [, , $business] = $this->scaffoldOrg();

        $this->asAdmin()->patch(route('admin.businesses.suspend', $business));
        $this->assertEquals('suspended', $business->fresh()->status);

        $this->asAdmin()->patch(route('admin.businesses.activate', $business));
        $this->assertNotEquals('suspended', $business->fresh()->status);
    }

    // ── Organizations ─────────────────────────────────────────────────────────

    /** @test */
    public function admin_can_suspend_and_reactivate_an_organization(): void
    {
        [, $org] = $this->scaffoldOrg();

        $this->asAdmin()->patch(route('admin.organizations.suspend', $org));
        $this->assertEquals('suspended', $org->fresh()->status);

        $this->asAdmin()->patch(route('admin.organizations.activate', $org));
        $this->assertEquals('active', $org->fresh()->status);
    }

    /** @test */
    public function admin_can_extend_an_organizations_trial(): void
    {
        [, $org] = $this->scaffoldOrg();
        $before = $org->trial_ends_at;

        $this->asAdmin()->patch(route('admin.organizations.extend-trial', $org), ['days' => 7]);

        $after = $org->fresh()->trial_ends_at;
        $this->assertTrue(! $before || $after->gt($before));
    }

    /** @test */
    public function admin_can_grant_a_subscription(): void
    {
        [, $org] = $this->scaffoldOrg();

        $this->asAdmin()->post(route('admin.organizations.grant-subscription', $org), [
            'plan' => 'growth', 'months' => 1,
        ]);

        $this->assertTrue(
            Subscription::where('organization_id', $org->id)->where('plan', 'growth')->where('status', 'active')->exists()
        );
    }

    /** @test */
    public function admin_cannot_cancel_a_subscription_belonging_to_a_different_organization(): void
    {
        [, $org] = $this->scaffoldOrg();
        [, $otherOrg] = $this->scaffoldOrg();
        $sub = Subscription::where('organization_id', $otherOrg->id)->first();

        $this->asAdmin()->patch(route('admin.organizations.subscriptions.cancel', [$org, $sub]))
            ->assertNotFound();
    }

    /** @test */
    public function admin_can_cancel_a_subscription_belonging_to_the_right_organization(): void
    {
        [, $org] = $this->scaffoldOrg();
        $sub = Subscription::where('organization_id', $org->id)->first();

        $this->asAdmin()->patch(route('admin.organizations.subscriptions.cancel', [$org, $sub]));

        $this->assertEquals('cancelled', $sub->fresh()->status);
    }

    /** @test */
    public function admin_can_save_notes_on_an_organization(): void
    {
        [, $org] = $this->scaffoldOrg();

        $this->asAdmin()->patch(route('admin.organizations.notes', $org), ['admin_notes' => 'flagged for review']);

        $this->assertEquals('flagged for review', $org->fresh()->admin_notes);
    }

    // ── Admin's own password ───────────────────────────────────────────────────

    /** @test */
    public function admin_password_change_rejects_the_wrong_current_password(): void
    {
        $this->admin->forceFill(['password' => Hash::make('RealPass#1')])->save();
        $originalHash = $this->admin->password;

        $this->asAdmin()->post(route('admin.password.update'), [
            'current_password' => 'totally-wrong',
            'password' => 'NewPass#123', 'password_confirmation' => 'NewPass#123',
        ]);

        $this->assertEquals($originalHash, $this->admin->fresh()->password);
    }

    /** @test */
    public function admin_password_change_accepts_the_correct_current_password(): void
    {
        $this->admin->forceFill(['password' => Hash::make('RealPass#1')])->save();

        $this->asAdmin()->post(route('admin.password.update'), [
            'current_password' => 'RealPass#1',
            'password' => 'NewPass#123!', 'password_confirmation' => 'NewPass#123!',
        ]);

        $this->assertTrue(Hash::check('NewPass#123!', $this->admin->fresh()->password));
    }

    // ── Developer logs ───────────────────────────────────────────────────────

    /** @test */
    public function admin_can_resolve_an_error(): void
    {
        $error = ErrorLog::create([
            'fingerprint' => str_repeat('9', 40), 'exception' => 'RuntimeException', 'message' => 'x',
            'method' => 'GET', 'path' => '/x', 'count' => 1,
            'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);

        $this->asAdmin()->post(route('admin.logs.errors.resolve', $error->fingerprint));

        $this->assertNotNull($error->fresh()->resolved_at);
    }

    // ── Canned replies ───────────────────────────────────────────────────────

    /** @test */
    public function admin_can_create_and_delete_a_canned_reply(): void
    {
        $this->asAdmin()->post(route('admin.support.canned.store'), ['title' => 'Greeting', 'body' => 'Hi there!']);
        $canned = SupportCannedReply::where('title', 'Greeting')->first();
        $this->assertNotNull($canned);

        $this->asAdmin()->delete(route('admin.support.canned.destroy', $canned));
        $this->assertNull(SupportCannedReply::find($canned->id));
    }

    // ── Dashboard stats exclude demo orgs ────────────────────────────────────

    /**
     * Regression: every platform-health count on the admin dashboard
     * (org totals, active/trial/expired breakdowns, plan breakdown, recent
     * orgs, new-signups-this-week, suspended count, the M-Pesa-not-
     * configured count, and the trial-ending "Needs Attention" list) used
     * to include throwaway "Try it now" demo orgs — a self-purging 24h
     * sandbox with zero real business value. A demo click bumped every
     * number on the dashboard and put a meaningless "Demo Mini-Mart —
     * trial ends in N hours" in Needs Attention, since there's nothing an
     * admin needs to do about it (PurgeDemoStores deletes it on schedule).
     */
    /** @test */
    public function demo_organizations_are_excluded_from_every_dashboard_stat(): void
    {
        [, $realOrg, $realBusiness] = $this->scaffoldOrg();

        $demoOwner = User::factory()->owner()->create();
        $demoOrg = Organization::factory()->create([
            'owner_user_id'  => $demoOwner->id,
            'status'         => 'trial',
            'is_demo'        => true,
            'trial_ends_at'  => now()->addHours(18),
        ]);
        Business::factory()->create(['organization_id' => $demoOrg->id]);

        $response = $this->asAdmin()->get(route('admin.dashboard'));
        $response->assertOk();

        $realOrgCount = Organization::where('is_demo', false)->count();
        $this->assertEquals($realOrgCount, $response->viewData('totalOrgs'));
        // The only trial-status org created in this test is the demo one —
        // it must not be counted.
        $this->assertEquals(0, $response->viewData('trialOrgs'));

        $recentOrgIds = $response->viewData('recentOrgs')->pluck('id');
        $this->assertFalse($recentOrgIds->contains($demoOrg->id), 'demo org should not appear in Recent Organizations');

        $trialsEndingSoonIds = $response->viewData('trialsEndingSoon')->pluck('id');
        $this->assertFalse($trialsEndingSoonIds->contains($demoOrg->id), 'demo org should not appear in the Needs Attention trial-ending list');

        $response->assertDontSee($demoOrg->name);
    }

    /**
     * Same bug, different controller: the Organizations list page's own KPI
     * strip (Total/Active/On Trial/Trial Ending Soon/Suspended/Total
     * Stores/users) is computed separately from the dashboard's and had the
     * identical gap — confirmed live: two demo orgs inflated "On Trial" and
     * "Trial Ending Soon" to 2 each on a platform with zero real trials.
     * The list itself is left showing demo rows (an admin may legitimately
     * want to find one, e.g. to debug the demo feature) — only the KPI
     * numbers must exclude them.
     */
    /** @test */
    public function demo_organizations_are_excluded_from_the_organizations_list_kpi_strip(): void
    {
        [, $realOrg, $realBusiness] = $this->scaffoldOrg();

        foreach (range(1, 2) as $i) {
            $demoOwner = User::factory()->owner()->create();
            $demoOrg = Organization::factory()->create([
                'owner_user_id' => $demoOwner->id,
                'status'        => 'trial',
                'is_demo'       => true,
                'trial_ends_at' => now()->addHours(18),
            ]);
            Business::factory()->create(['organization_id' => $demoOrg->id]);
        }

        $response = $this->asAdmin()->get(route('admin.organizations.index'));
        $response->assertOk();

        $stats = $response->viewData('stats');

        $this->assertEquals(Organization::where('is_demo', false)->count(), $stats['total']);
        $this->assertEquals(0, $stats['trial'], 'the 2 demo orgs must not count toward On Trial');
        $this->assertEquals(0, $stats['expiring_soon'], 'the 2 demo orgs must not count toward Trial Ending Soon');
        $this->assertEquals(
            Business::where('id', $realBusiness->id)->count(),
            Business::whereHas('organization', fn ($q) => $q->real())->count(),
            'sanity check: only the real business should be counted as real'
        );
    }
}
