<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SerialNumber;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\SaleService;
use Tests\Feature\Helpers\CreatesOrganization;
use Tests\TestCase;

/**
 * Regression coverage for the 2026-08-16 bug sweep. Each test pins down the
 * exact failure mode that was found so it can't silently come back.
 */
class BugFixVerificationTest extends TestCase
{
    use CreatesOrganization;

    private function makeProductWithVariant(Business $business): array
    {
        $product = Product::create([
            'business_id'    => $business->id,
            'name'           => 'T-Shirt',
            'sku'            => 'TSHIRT-1',
            'buying_price'   => 500,
            'selling_price'  => 1000,
            'stock_qty'      => 0, // parent has no stock of its own — sold via variants
            'has_variants'   => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'name'       => 'Medium',
            'sku'        => 'TSHIRT-1-M',
            'price'      => 1000,
            'cost_price' => 500,
            'stock_qty'  => 10,
        ]);

        return [$product, $variant];
    }

    // ── Bug 1: variant stock on full-sale cancellation ─────────────────────────

    /** @test */
    public function cancelling_a_variant_sale_restores_the_variants_stock_not_the_products(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();
        [$product, $variant] = $this->makeProductWithVariant($business);

        $sale = app(SaleService::class)->createSale([
            'items' => [[
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'quantity'   => 3,
                'unit_price' => 1000,
                'discount'   => 0,
            ]],
            'paid_amount'    => 3000,
            'payment_method' => 'cash',
        ], $business->id, $owner->id);

        $variant->refresh();
        $product->refresh();
        $this->assertEquals(7, $variant->stock_qty, 'variant stock should decrement on sale');
        $this->assertEquals(0, $product->stock_qty, 'parent product stock must be untouched by a variant sale');

        $saleItem = SaleItem::where('sale_id', $sale->id)->first();
        $this->assertEquals($variant->id, $saleItem->variant_id, 'sale item must record which variant was sold');

        app(SaleService::class)->cancelSale($sale->fresh());

        $variant->refresh();
        $product->refresh();
        $this->assertEquals(10, $variant->stock_qty, 'cancelling must restore the VARIANT stock');
        $this->assertEquals(0, $product->stock_qty, 'cancelling a variant sale must NOT touch the parent product stock');
    }

    /** @test */
    public function cancelling_a_serialized_sale_releases_the_serial_back_to_stock(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();

        $product = Product::create([
            'business_id'    => $business->id,
            'name'           => 'iPhone 15',
            'sku'            => 'IPH15',
            'buying_price'   => 80000,
            'selling_price'  => 100000,
            'stock_qty'      => 1,
            'track_serials'  => true,
        ]);

        $serial = SerialNumber::create([
            'business_id'   => $business->id,
            'product_id'    => $product->id,
            'serial_number' => 'IMEI-12345',
            'status'        => 'in_stock',
        ]);

        $sale = app(SaleService::class)->createSale([
            'items' => [[
                'product_id' => $product->id,
                'serial_id'  => $serial->id,
                'quantity'   => 1,
                'unit_price' => 100000,
                'discount'   => 0,
            ]],
            'paid_amount'    => 100000,
            'payment_method' => 'cash',
        ], $business->id, $owner->id);

        $this->assertEquals('sold', $serial->fresh()->status);

        app(SaleService::class)->cancelSale($sale->fresh());

        $serial->refresh();
        $this->assertEquals('in_stock', $serial->status, 'cancelling must release the serial back to stock');
        $this->assertNull($serial->sale_id);
    }

    // ── Bug 2: variant stock on partial returns ─────────────────────────────────

    /** @test */
    public function partial_return_of_a_variant_item_restocks_the_variant_not_the_product(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();
        [$product, $variant] = $this->makeProductWithVariant($business);

        $sale = app(SaleService::class)->createSale([
            'items' => [[
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'quantity'   => 4,
                'unit_price' => 1000,
                'discount'   => 0,
            ]],
            'paid_amount'    => 4000,
            'payment_method' => 'cash',
        ], $business->id, $owner->id);

        $saleItem = SaleItem::where('sale_id', $sale->id)->first();
        $variant->refresh();
        $this->assertEquals(6, $variant->stock_qty);

        $response = $this->actingAs($owner)->post(route('returns.store'), [
            'sale_id'       => $sale->id,
            'items'         => [[
                'sale_item_id'      => $saleItem->id,
                'quantity_returned' => 2,
            ]],
            'stock_action'  => 'restock',
            'refund_method' => 'cash',
            'reason'        => 'wrong size',
        ]);

        $response->assertRedirect(route('returns.index'));

        $variant->refresh();
        $product->refresh();
        $this->assertEquals(8, $variant->stock_qty, 'returned units must go back to the VARIANT');
        $this->assertEquals(0, $product->stock_qty, 'a variant return must NOT touch the parent product stock');
    }

    // ── Bug 3: manual sale payment must reduce customer balance_owed ───────────

    /** @test */
    public function manually_recording_a_sale_payment_reduces_customer_balance_owed(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();

        $customer = Customer::create([
            'business_id' => $business->id,
            'name'        => 'Jane Regular',
            'phone'       => '0722000000',
        ]);

        // A credit sale: nothing paid up front, full amount owed.
        $sale = app(SaleService::class)->createSale([
            'items' => [[
                'product_id' => Product::create([
                    'business_id'   => $business->id,
                    'name'          => 'Sugar 1kg',
                    'sku'           => 'SUGAR1',
                    'buying_price'  => 100,
                    'selling_price' => 150,
                    'stock_qty'     => 50,
                ])->id,
                'quantity'   => 2,
                'unit_price' => 150,
                'discount'   => 0,
            ]],
            'customer_id'    => $customer->id,
            'paid_amount'    => 0,
            'payment_method' => 'credit',
        ], $business->id, $owner->id);

        $customer->refresh();
        $this->assertEquals(300, $customer->balance_owed, 'sale creation should put the sale on the customer tab');

        // Customer comes back and pays cash — cashier clicks "Record Payment".
        $response = $this->actingAs($owner)->post(route('sales.record-payment', $sale), [
            'amount'         => 300,
            'payment_method' => 'cash',
        ]);

        $response->assertRedirect(route('sales.show', $sale));

        $customer->refresh();
        $this->assertEquals(
            0,
            (float) $customer->balance_owed,
            'recording a manual payment must reduce the customer\'s outstanding balance'
        );
    }

    // ── Bug 4: payroll staff list must not leak across tenants ─────────────────

    /** @test */
    public function payroll_period_staff_list_does_not_leak_other_businesses_staff(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();

        $ownStaff = User::factory()->create(['organization_id' => null, 'role' => 'cashier']);
        $business->users()->attach($ownStaff->id, ['role' => 'cashier']);
        StaffProfile::create([
            'user_id'     => $ownStaff->id,
            'business_id' => $business->id,
        ]);

        // A second, unrelated business with a staff member whose termination
        // date is in the future — this is exactly the row that leaked before
        // the fix, because the un-scoped orWhere matched it regardless of
        // business_id.
        $otherBusiness = Business::factory()->create();
        $otherStaff = User::factory()->create(['organization_id' => null, 'role' => 'cashier']);
        $otherBusiness->users()->attach($otherStaff->id, ['role' => 'cashier']);
        StaffProfile::create([
            'user_id'          => $otherStaff->id,
            'business_id'      => $otherBusiness->id,
            'termination_date' => now()->addMonth(),
        ]);

        $period = \App\Models\PayrollPeriod::factory()->create([
            'business_id'  => $business->id,
            'period_start' => now()->startOfMonth(),
            'period_end'   => now()->endOfMonth(),
        ]);

        $response = $this->actingAs($owner)->get(route('payroll.show', $period));

        $response->assertOk();
        $staffIds = $response->viewData('staffProfiles')->pluck('user_id')->all();

        $this->assertContains($ownStaff->id, $staffIds, 'the business\'s own staff must still appear');
        $this->assertNotContains($otherStaff->id, $staffIds, 'another business\'s staff must never leak into this list');
    }
}
