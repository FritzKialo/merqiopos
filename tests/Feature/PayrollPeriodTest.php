<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\PayrollPeriod;
use App\Models\StaffProfile;
use App\Models\User;
use Tests\Feature\Helpers\CreatesOrganization;
use Tests\TestCase;

class PayrollPeriodTest extends TestCase
{
    use CreatesOrganization;

    // ── Create period ─────────────────────────────────────────────────────────

    /** @test */
    public function owner_can_create_payroll_period(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();

        $response = $this->actingAs($owner)->post(route('payroll.store'), [
            'period_start' => '2025-06-01',
            'period_end'   => '2025-06-30',
            'pay_cycle'    => 'monthly',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('payroll_periods', [
            'business_id' => $business->id,
            'status'      => 'draft',
        ]);
    }

    /** @test */
    public function overlapping_period_is_rejected(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();

        PayrollPeriod::factory()->create([
            'business_id'  => $business->id,
            'period_start' => '2025-06-01',
            'period_end'   => '2025-06-30',
        ]);

        $response = $this->actingAs($owner)->post(route('payroll.store'), [
            'period_start' => '2025-06-15',
            'period_end'   => '2025-07-14',
            'pay_cycle'    => 'monthly',
        ]);

        $response->assertSessionHasErrors('period_start');
        $this->assertCount(1, PayrollPeriod::where('business_id', $business->id)->get());
    }

    // ── Approve / paid lifecycle ───────────────────────────────────────────────

    /** @test */
    public function draft_period_can_be_approved(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();

        $period = PayrollPeriod::factory()->create([
            'business_id' => $business->id,
            'status'      => 'draft',
        ]);

        // Need at least one item so approve is allowed
        \App\Models\PayrollItem::factory()->create([
            'payroll_period_id' => $period->id,
            'business_id'       => $business->id,
            'user_id'           => $owner->id,
        ]);

        $this->actingAs($owner)
            ->patch(route('payroll.approve', $period));

        $this->assertEquals('approved', $period->fresh()->status);
    }

    /** @test */
    public function approved_period_can_be_marked_paid(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();

        $period = PayrollPeriod::factory()->approved()->create([
            'business_id' => $business->id,
            'total_net'   => 80000,
        ]);

        \App\Models\PayrollItem::factory()->create([
            'payroll_period_id' => $period->id,
            'business_id'       => $business->id,
            'user_id'           => $owner->id,
            'status'            => 'pending',
            'net_pay'           => 80000,
        ]);

        $this->actingAs($owner)
            ->patch(route('payroll.mark-paid', $period));

        $this->assertEquals('paid', $period->fresh()->status);
    }

    /** @test */
    public function marking_paid_posts_expense(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();

        $period = PayrollPeriod::factory()->approved()->create([
            'business_id' => $business->id,
            'total_net'   => 90000,
        ]);

        $item = \App\Models\PayrollItem::factory()->create([
            'payroll_period_id' => $period->id,
            'business_id'       => $business->id,
            'user_id'           => $owner->id,
            'status'            => 'pending',
            'net_pay'           => 90000,
        ]);

        $this->actingAs($owner)
            ->patch(route('payroll.mark-paid', $period));

        // The posted expense is gross pay + employer contributions (PayrollPeriod::
        // postExpenseOnce() sums each paid item's employerCost()), not net_pay — this
        // used to assert amount === net_pay, which never matched that documented
        // formula and was failing for the wrong reason regardless of any fixture
        // values chosen.
        $this->assertDatabaseHas('expenses', [
            'business_id' => $business->id,
            'reference'   => 'PAYROLL-' . $period->id,
            'amount'      => $item->fresh()->employerCost(),
        ]);
    }

    /** @test */
    public function marking_paid_twice_does_not_duplicate_expense(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();

        $period = PayrollPeriod::factory()->paid()->create([
            'business_id' => $business->id,
            'total_net'   => 50000,
        ]);

        // Simulate: expense already posted from first payment
        $category = \App\Models\ExpenseCategory::create([
            'business_id' => $business->id,
            'name'        => 'Salaries & Wages',
        ]);
        \App\Models\Expense::create([
            'business_id'         => $business->id,
            'expense_category_id' => $category->id,
            'user_id'             => $owner->id,
            'title'               => 'Payroll: test',
            'amount'              => 50000,
            'payment_method'      => 'bank_transfer',
            'reference'           => 'PAYROLL-' . $period->id,
            'expense_date'        => today(),
        ]);

        // Calling mark-paid again on an already-paid period does nothing extra
        $this->actingAs($owner)
            ->patch(route('payroll.mark-paid', $period));

        $this->assertCount(
            1,
            \App\Models\Expense::where('reference', 'PAYROLL-' . $period->id)->get()
        );
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    /** @test */
    public function draft_period_can_be_deleted(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();

        $period = PayrollPeriod::factory()->create(['business_id' => $business->id]);

        $this->actingAs($owner)
            ->delete(route('payroll.destroy', $period));

        $this->assertDatabaseMissing('payroll_periods', ['id' => $period->id]);
    }

    /** @test */
    public function paid_period_cannot_be_deleted(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();

        $period = PayrollPeriod::factory()->paid()->create([
            'business_id' => $business->id,
        ]);

        $this->actingAs($owner)
            ->delete(route('payroll.destroy', $period));

        $this->assertDatabaseHas('payroll_periods', ['id' => $period->id]);
    }

    // ── Cross-business isolation ──────────────────────────────────────────────

    /** @test */
    public function owner_cannot_access_another_businesss_period(): void
    {
        [$owner] = $this->scaffoldOrg();

        $other = Business::factory()->create();
        $period = PayrollPeriod::factory()->create(['business_id' => $other->id]);

        $this->actingAs($owner)
            ->get(route('payroll.show', $period))
            ->assertForbidden();
    }
}
