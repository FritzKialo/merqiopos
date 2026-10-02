<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\User;
use Tests\TestCase;

class PayrollPeriodTest extends TestCase
{
    /** @test */
    public function recalculate_totals_sums_all_items(): void
    {
        $business = Business::factory()->withPayroll()->create();
        $period   = PayrollPeriod::factory()->create(['business_id' => $business->id]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        PayrollItem::factory()->create([
            'payroll_period_id' => $period->id,
            'business_id'       => $business->id,
            'user_id'           => $user1->id,
            'gross_pay'         => 50000,
            'nssf_employee'     => 2160,
            'nssf_employer'     => 2160,
            'shif_employee'     => 1375,
            'shif_employer'     => 1375,
            'paye'              => 6735.35,
            'total_deductions'  => 10270.35,
            'net_pay'           => 39729.65,
        ]);

        PayrollItem::factory()->create([
            'payroll_period_id' => $period->id,
            'business_id'       => $business->id,
            'user_id'           => $user2->id,
            'gross_pay'         => 30000,
            'nssf_employee'     => 1800,
            'nssf_employer'     => 1800,
            'shif_employee'     => 825,
            'shif_employer'     => 825,
            'paye'              => 1500,
            'total_deductions'  => 4125,
            'net_pay'           => 25875,
        ]);

        $period->recalculateTotals();
        $period->refresh();

        $this->assertEquals(80000,   $period->total_gross);
        $this->assertEquals(3960,    $period->total_nssf_ee);
        $this->assertEquals(3960,    $period->total_nssf_er);
        $this->assertEquals(2200,    $period->total_shif_ee);
        $this->assertEquals(2200,    $period->total_shif_er);
        $this->assertEquals(8235.35, $period->total_paye);
        $this->assertEquals(65604.65, $period->total_net);
    }

    /** @test */
    public function status_helpers_return_correct_values(): void
    {
        $business = Business::factory()->withPayroll()->create();

        // Distinct period ranges — the factory defaults all land in the current month,
        // and (business_id, period_start, period_end) is uniquely constrained, so three
        // periods for one business with no override collide on creation.
        $draft    = PayrollPeriod::factory()->create(['business_id' => $business->id, 'status' => 'draft', 'period_start' => '2026-01-01', 'period_end' => '2026-01-31']);
        $approved = PayrollPeriod::factory()->approved()->create(['business_id' => $business->id, 'period_start' => '2026-02-01', 'period_end' => '2026-02-28']);
        $paid     = PayrollPeriod::factory()->paid()->create(['business_id' => $business->id, 'period_start' => '2026-03-01', 'period_end' => '2026-03-31']);

        $this->assertTrue($draft->isDraft());
        $this->assertFalse($draft->isApproved());

        $this->assertTrue($approved->isApproved());
        $this->assertFalse($approved->isDraft());

        $this->assertTrue($paid->isPaid());
        $this->assertFalse($paid->isDraft());
    }

    /** @test */
    public function unique_constraint_prevents_overlapping_periods(): void
    {
        $business = Business::factory()->withPayroll()->create();

        PayrollPeriod::factory()->create([
            'business_id'  => $business->id,
            'period_start' => '2025-06-01',
            'period_end'   => '2025-06-30',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        PayrollPeriod::factory()->create([
            'business_id'  => $business->id,
            'period_start' => '2025-06-01',
            'period_end'   => '2025-06-30',
        ]);
    }
}
