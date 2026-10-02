<?php

namespace Tests\Feature;

use App\Console\Commands\SendLowStockAlerts;
use App\Console\Commands\SendPaymentReminders;
use App\Mail\PaymentReminderDigest;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\User;
use App\Support\DemoStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Helpers\CreatesOrganization;
use Tests\TestCase;

/**
 * The "Try the demo" sandbox: a full, throw-away sample store per visitor, safely
 * isolated from real customer data and from the platform's own outbound mail.
 */
class DemoStoreTest extends TestCase
{
    use CreatesOrganization;

    /** @test */
    public function creating_a_demo_builds_a_populated_sandbox(): void
    {
        $userId = DemoStore::create();
        $user   = User::find($userId);
        $org    = $user->organization;
        $business = $user->currentBusiness();

        $this->assertTrue((bool) $org->is_demo); // is_demo isn't cast to boolean on the model
        $this->assertGreaterThan(0, Product::where('business_id', $business->id)->count());
        $this->assertGreaterThan(0, Customer::where('business_id', $business->id)->count());
        $this->assertGreaterThan(0, Sale::where('business_id', $business->id)->count());
        $this->assertGreaterThan(0, Quote::where('business_id', $business->id)->count());
        $this->assertGreaterThan(0, DB::table('invoices')->where('business_id', $business->id)->count());
        $this->assertGreaterThan(0, DB::table('purchase_orders')->where('business_id', $business->id)->count());
        // Nothing in the sandbox should be reachable by real email/SMS.
        $this->assertStringEndsWith('@demo.invalid', $user->email);
    }

    /** @test */
    public function two_demos_created_back_to_back_do_not_collide_on_document_numbers(): void
    {
        // Several document-number columns (invoices, sale_returns, credit_notes...) are
        // globally unique across the whole platform, not per business — a fixed number
        // like "INV-2026-0001" collided with real customer data in production. Every
        // demo now mixes in its own random token specifically to avoid that.
        $userId1 = DemoStore::create();
        $userId2 = DemoStore::create();

        $this->assertNotNull($userId1);
        $this->assertNotNull($userId2);
        $this->assertNotEquals($userId1, $userId2);
    }

    /** @test */
    public function purging_a_demo_leaves_no_trace_and_does_not_touch_other_data(): void
    {
        [$realOwner, $realOrg, $realBusiness] = $this->scaffoldOrg();
        // sqlite_sequence is excluded: it's sqlite's own internal bookkeeping of each
        // table's autoincrement high-water mark, which — correctly — never shrinks back
        // down just because rows were deleted; it's not user-visible "leftover" data.
        $tableCounts = fn () => collect(DB::select('select name from sqlite_master where type = "table"'))
            ->reject(fn ($t) => $t->name === 'sqlite_sequence')
            ->mapWithKeys(fn ($t) => [$t->name => DB::table($t->name)->count()]);
        $before = $tableCounts();

        $demoUserId = DemoStore::create();
        $demoOrgId  = User::find($demoUserId)->organization_id;

        DemoStore::purge($demoOrgId);

        $this->assertEquals($before->all(), $tableCounts()->all());
        $this->assertNull(Organization::find($demoOrgId));
        $this->assertNotNull($realOrg->fresh(), 'purging a demo must never touch a real organization');
    }

    /** @test */
    public function purge_only_removes_demos_older_than_the_lifetime_by_default(): void
    {
        $recentUserId = DemoStore::create();
        $recentOrgId  = User::find($recentUserId)->organization_id;

        $removed = DemoStore::purge(); // no explicit id — the "sweep everything overdue" mode

        $this->assertEquals(0, $removed);
        $this->assertNotNull(Organization::find($recentOrgId));
    }

    /** @test */
    public function purge_sweeps_a_demo_once_it_is_older_than_the_lifetime(): void
    {
        $userId = DemoStore::create();
        $orgId  = User::find($userId)->organization_id;
        Organization::where('id', $orgId)->update(['created_at' => now()->subHours(DemoStore::LIFETIME_HOURS + 1)]);

        $removed = DemoStore::purge();

        $this->assertEquals(1, $removed);
        $this->assertNull(Organization::find($orgId));
    }

    // ── DemoGuard ────────────────────────────────────────────────────────────

    /** @test */
    public function a_demo_visitor_cannot_invite_team_members_or_change_the_password(): void
    {
        $userId = DemoStore::create();
        $user   = User::find($userId);

        // JSON requests so DemoGuard's block renders as a 403 to assert on directly —
        // a normal form submission gets a friendlier redirect-back-with-flash-message instead.
        $this->actingAs($user)->postJson(route('settings.team.store'), [
            'name' => 'x', 'email' => 'x@example.com', 'role' => 'cashier', 'password' => 'Password#123',
        ])->assertStatus(403);

        $this->actingAs($user)->putJson(route('settings.password.update'), [
            'current_password' => 'x', 'password' => 'NewPass#123', 'password_confirmation' => 'NewPass#123',
        ])->assertStatus(403);
    }

    /** @test */
    public function a_demo_visitor_blocked_action_via_a_normal_form_submit_gets_a_friendly_redirect(): void
    {
        $userId = DemoStore::create();
        $user   = User::find($userId);

        $response = $this->actingAs($user)->from(route('settings.team'))->post(route('settings.team.store'), [
            'name' => 'x', 'email' => 'x@example.com', 'role' => 'cashier', 'password' => 'Password#123',
        ]);

        $response->assertRedirect(route('settings.team'));
        $response->assertSessionHas('error');
    }

    /** @test */
    public function a_demo_visitor_can_still_browse_and_create_ordinary_records(): void
    {
        $userId = DemoStore::create();
        $user   = User::find($userId);
        $business = $user->currentBusiness();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $this->actingAs($user)->post(route('customers.store'), [
            'name' => 'A New Demo Customer', 'phone' => '0712345678',
        ])->assertRedirect();

        $this->assertTrue(Customer::where('business_id', $business->id)->where('name', 'A New Demo Customer')->exists());
    }

    /** @test */
    public function a_real_business_is_completely_unaffected_by_demo_guard(): void
    {
        [$owner] = $this->scaffoldOrg();

        $this->actingAs($owner)->put(route('settings.password.update'), [
            'current_password' => 'wrong-password', 'password' => 'NewPass#123', 'password_confirmation' => 'NewPass#123',
        ])->assertStatus(302); // rejected by password validation, not by DemoGuard's 403
    }

    // ── Scheduled commands must never touch demo sandboxes ─────────────────────

    /** @test */
    public function payment_reminders_skip_demo_businesses_but_still_reach_real_ones(): void
    {
        Mail::fake();

        [$realOwner, , $realBusiness] = $this->scaffoldOrg();
        $realCustomer = Customer::create([
            'business_id' => $realBusiness->id, 'name' => 'Real Debtor', 'phone' => '0700000001', 'balance_owed' => 5000,
        ]);

        $demoUserId = DemoStore::create();
        $demoBusiness = User::find($demoUserId)->currentBusiness();
        $demoCustomer = Customer::where('business_id', $demoBusiness->id)->first();
        $demoCustomer->update(['balance_owed' => 5000, 'payment_reminder_sent_at' => null]);

        $this->artisan(SendPaymentReminders::class)->run();

        Mail::assertQueued(PaymentReminderDigest::class, fn ($mail) => $mail->business->id === $realBusiness->id);
        Mail::assertNotQueued(PaymentReminderDigest::class, fn ($mail) => $mail->business->id === $demoBusiness->id);
    }

    /** @test */
    public function low_stock_alerts_skip_demo_businesses(): void
    {
        Mail::fake();

        $demoUserId = DemoStore::create();
        $demoBusiness = User::find($demoUserId)->currentBusiness();
        Product::where('business_id', $demoBusiness->id)->first()->update(['stock_qty' => 0, 'reorder_level' => 10]);

        $this->artisan(SendLowStockAlerts::class)->run();

        Mail::assertNothingQueued();
    }
}
