<?php

namespace Tests\Feature;

use App\Models\PurchaseRequisition;
use App\Models\Supplier;
use Tests\Feature\Helpers\CreatesOrganization;
use Tests\TestCase;

/**
 * Regression coverage for a static project-wide sweep: cross-referencing
 * every named GET route against whether it's ever linked to from a Blade
 * view turned up two fully-built features with no way to reach them from
 * the UI (same "orphaned feature" shape found repeatedly this engagement —
 * Proforma Invoices, VAT Return report, stock-count, etc. earlier on):
 *
 * 1. purchase-requisitions.edit — the edit page and its update() guard
 *    (pending status only) were fully built, but neither the index nor
 *    show page ever linked to it.
 * 2. suppliers.payments — the payments page (record/view payments against
 *    a supplier's outstanding balance) existed and worked, but the
 *    supplier show page — which prominently displays that same Outstanding
 *    Balance as a KPI — never linked to it.
 */
class OrphanedFeatureLinksTest extends TestCase
{
    use CreatesOrganization;

    /** @test */
    public function a_pending_requisitions_show_page_links_to_edit(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();

        $pr = PurchaseRequisition::create([
            'business_id' => $business->id, 'requested_by' => $owner->id,
            'reference' => 'PR-TEST-1', 'status' => 'pending', 'urgency' => 'normal',
        ]);

        $response = $this->actingAs($owner)->get(route('purchase-requisitions.show', $pr));

        $response->assertOk();
        $response->assertSee(route('purchase-requisitions.edit', $pr), false);
    }

    /** @test */
    public function a_non_pending_requisitions_show_page_does_not_link_to_edit(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();

        $pr = PurchaseRequisition::create([
            'business_id' => $business->id, 'requested_by' => $owner->id,
            'reference' => 'PR-TEST-2', 'status' => 'converted', 'urgency' => 'normal',
        ]);

        $response = $this->actingAs($owner)->get(route('purchase-requisitions.show', $pr));

        $response->assertOk();
        $response->assertDontSee(route('purchase-requisitions.edit', $pr), false);
    }

    /** @test */
    public function a_suppliers_show_page_links_to_its_payments_page(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();

        $supplier = Supplier::create([
            'business_id' => $business->id, 'name' => 'Test Supplier Ltd', 'is_active' => true,
        ]);

        $response = $this->actingAs($owner)->get(route('suppliers.show', $supplier));

        $response->assertOk();
        $response->assertSee(route('suppliers.payments', $supplier), false);
    }
}
