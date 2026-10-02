<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\Feature\Helpers\CreatesOrganization;
use Tests\TestCase;

/**
 * Regression: found via a project-wide static sweep cross-referencing every
 * route('name') call against the actual registered route list.
 * resources/views/audit/index.blade.php used route('audit.index') — the
 * name a now-removed duplicate in routes/web.php registered — while the
 * real, live route (routes/features_ux.php) is named 'audit-log.index'.
 * Since the form action and "Clear" link are evaluated on render (not
 * lazily on click), this crashed the page with RouteNotFoundException on
 * every single load, for every business owner.
 */
class AuditLogPageTest extends TestCase
{
    use CreatesOrganization;

    /** @test */
    public function the_owner_can_load_the_audit_log_page_without_a_route_not_found_crash(): void
    {
        [$owner] = $this->scaffoldOrg();

        $response = $this->actingAs($owner)->get(route('audit-log.index'));

        $response->assertOk();
        $response->assertSee(route('audit-log.index'), false);
    }

    /** @test */
    public function a_non_owner_cannot_reach_the_audit_log(): void
    {
        [, , $business] = $this->scaffoldOrg();
        $cashier = User::factory()->cashier()->create();
        $business->users()->attach($cashier->id, ['role' => 'cashier']);
        session(['active_business_id' => $business->id]);

        // This app's 'role' middleware redirects unauthorized users rather
        // than aborting 403 (matches its behavior elsewhere in the app).
        $this->actingAs($cashier)->get(route('audit-log.index'))->assertRedirect();
    }
}
