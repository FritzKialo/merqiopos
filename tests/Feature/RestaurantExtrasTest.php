<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\TableOrder;
use App\Models\TableOrderItem;
use App\Models\TableOrderRequest;
use Tests\Feature\Helpers\CreatesOrganization;
use Tests\TestCase;

/**
 * Restaurant round three: merging two tables' orders, a per-line discount on a
 * table order item, and QR self-ordering (a customer's request always needs staff
 * approval before it becomes a real item — never written directly by the customer).
 */
class RestaurantExtrasTest extends TestCase
{
    use CreatesOrganization;

    private function makeProduct($business, string $name = 'Chips', float $price = 250): Product
    {
        return Product::create([
            'business_id' => $business->id, 'name' => $name, 'sku' => strtoupper(str_replace(' ', '', $name)),
            'buying_price' => $price * 0.6, 'selling_price' => $price, 'stock_qty' => 100,
        ]);
    }

    private function openOrder($owner, $business, string $number = '1'): TableOrder
    {
        $table = RestaurantTable::create(['business_id' => $business->id, 'number' => $number, 'capacity' => 4]);
        $this->actingAs($owner)->post(route('tables.open', $table));

        return $table->fresh()->currentOrder;
    }

    // ── Merge tables ─────────────────────────────────────────────────────────

    /** @test */
    public function merging_a_table_moves_its_outstanding_items_and_frees_the_table(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();
        $product = $this->makeProduct($business);

        $target = $this->openOrder($owner, $business, '1');
        $source = $this->openOrder($owner, $business, '2');

        $this->actingAs($owner)->post(route('tables.orders.items.add', $source), ['product_id' => $product->id, 'quantity' => 2]);
        $sourceItem = TableOrderItem::where('table_order_id', $source->id)->first();

        $this->actingAs($owner)->post(route('tables.orders.merge', $target), ['from_table_id' => $source->table->id])
            ->assertRedirect(route('tables.orders.show', $target));

        $this->assertEquals($target->id, $sourceItem->fresh()->table_order_id, 'the item must move onto the target order');
        $this->assertEquals('merged', $source->fresh()->status);
        $this->assertEquals($target->id, $source->fresh()->merged_into_id);
        $this->assertEquals('available', $source->table->fresh()->status);
        $this->assertNull($source->table->fresh()->current_order_id);
        $this->assertEquals(500, (float) $target->fresh()->subtotal, '', 0.001);
    }

    /** @test */
    public function merging_leaves_already_paid_items_on_the_source_order(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();
        $product = $this->makeProduct($business);

        $target = $this->openOrder($owner, $business, '1');
        $source = $this->openOrder($owner, $business, '2');
        $this->actingAs($owner)->post(route('tables.orders.items.add', $source), ['product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($owner)->post(route('tables.orders.pay', $source), ['payment_method' => 'cash']);

        $paidItem = TableOrderItem::where('table_order_id', $source->id)->first();
        $this->assertNotNull($paidItem->sale_id, 'setup: the item should already be paid');

        // Nothing outstanding left on the source order — merging it in should fail cleanly.
        $this->actingAs($owner)->post(route('tables.orders.merge', $target), ['from_table_id' => $source->table->fresh()->id ?? $source->restaurant_table_id])
            ->assertRedirect();

        $this->assertEquals($source->id, $paidItem->fresh()->table_order_id, 'a paid item must never move to another order');
    }

    /** @test */
    public function cannot_merge_a_table_that_has_no_open_order(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();
        $target = $this->openOrder($owner, $business, '1');
        $emptyTable = RestaurantTable::create(['business_id' => $business->id, 'number' => '2', 'capacity' => 4]);

        $response = $this->actingAs($owner)->post(route('tables.orders.merge', $target), ['from_table_id' => $emptyTable->id]);

        $response->assertSessionHas('error');
        $this->assertEquals('available', $emptyTable->fresh()->status);
    }

    // ── Per-item discount ────────────────────────────────────────────────────

    /** @test */
    public function a_flat_discount_reduces_the_items_total_and_the_orders_total(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();
        $product = $this->makeProduct($business, 'Burger', 500);
        $order = $this->openOrder($owner, $business);
        $this->actingAs($owner)->post(route('tables.orders.items.add', $order), ['product_id' => $product->id, 'quantity' => 1]);
        $item = TableOrderItem::where('table_order_id', $order->id)->first();

        $this->actingAs($owner)->post(route('tables.orders.items.discount', [$order, $item]), ['discount' => 100])
            ->assertRedirect();

        $item->refresh();
        $this->assertEquals(100, (float) $item->discount);
        $this->assertEquals(400, (float) $item->total);
        $this->assertEquals(400, (float) $order->fresh()->subtotal);
    }

    /** @test */
    public function a_discount_cannot_exceed_the_lines_own_value(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();
        $product = $this->makeProduct($business, 'Soda', 100);
        $order = $this->openOrder($owner, $business);
        $this->actingAs($owner)->post(route('tables.orders.items.add', $order), ['product_id' => $product->id, 'quantity' => 1]);
        $item = TableOrderItem::where('table_order_id', $order->id)->first();

        $this->actingAs($owner)->post(route('tables.orders.items.discount', [$order, $item]), ['discount' => 150])
            ->assertSessionHasErrors('discount');

        $this->assertEquals(0, (float) $item->fresh()->discount);
    }

    /** @test */
    public function the_discount_carries_onto_the_sale_as_an_equivalent_percentage(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();
        $product = $this->makeProduct($business, 'Pizza', 1000);
        $order = $this->openOrder($owner, $business);
        $this->actingAs($owner)->post(route('tables.orders.items.add', $order), ['product_id' => $product->id, 'quantity' => 1]);
        $item = TableOrderItem::where('table_order_id', $order->id)->first();
        $this->actingAs($owner)->post(route('tables.orders.items.discount', [$order, $item]), ['discount' => 250]); // 25% of 1000

        $this->actingAs($owner)->post(route('tables.orders.pay', $order), ['payment_method' => 'cash']);

        $saleItem = SaleItem::where('sale_id', Sale::where('table_order_id', $order->id)->value('id'))->first();
        $this->assertEquals(750, (float) $saleItem->subtotal, 'the sale line should be net of the discount');
        $this->assertEqualsWithDelta(25.0, (float) $saleItem->discount, 0.01, 'sale_items.discount is a PERCENTAGE, not a flat amount');
    }

    // ── QR self-ordering ─────────────────────────────────────────────────────

    /** @test */
    public function a_customer_can_view_the_menu_and_submit_a_request_with_no_login(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();
        $product = $this->makeProduct($business, 'Nyama Choma', 800);
        $table = RestaurantTable::create(['business_id' => $business->id, 'number' => '5', 'capacity' => 4]);

        $menu = $this->get(route('table-order.public', $table->qrToken()));
        $menu->assertOk()->assertSee('Nyama Choma');

        $this->post(route('table-order.public.store', $table->qr_token), [
            'product_id' => $product->id, 'quantity' => 2, 'notes' => 'extra spicy',
        ])->assertRedirect();

        $this->assertDatabaseHas('table_order_requests', [
            'restaurant_table_id' => $table->id, 'product_id' => $product->id, 'status' => 'pending', 'notes' => 'extra spicy',
        ]);
    }

    /** @test */
    public function a_qr_token_from_another_business_cannot_be_used_to_order_a_product_here(): void
    {
        [, , $businessA] = $this->scaffoldOrg();
        [, , $businessB] = $this->scaffoldOrg();
        $table = RestaurantTable::create(['business_id' => $businessA->id, 'number' => '1', 'capacity' => 4]);
        $foreignProduct = $this->makeProduct($businessB, 'Foreign Dish');

        $this->post(route('table-order.public.store', $table->qrToken()), [
            'product_id' => $foreignProduct->id, 'quantity' => 1,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('table_order_requests', ['restaurant_table_id' => $table->id]);
    }

    /** @test */
    public function approving_a_request_opens_the_table_and_adds_the_item(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();
        $product = $this->makeProduct($business, 'Samosa', 50);
        $table = RestaurantTable::create(['business_id' => $business->id, 'number' => '7', 'capacity' => 2]);
        $this->post(route('table-order.public.store', $table->qrToken()), ['product_id' => $product->id, 'quantity' => 3]);
        $request = TableOrderRequest::first();

        $response = $this->actingAs($owner)->post(route('tables.requests.approve', [$table, $request]));

        $table->refresh();
        $this->assertEquals('occupied', $table->status, 'approving the first request should open the table');
        $this->assertNotNull($table->current_order_id);
        $item = TableOrderItem::where('table_order_id', $table->current_order_id)->first();
        $this->assertNotNull($item);
        $this->assertEquals('Samosa', $item->product_name);
        $this->assertEquals(3, (float) $item->quantity);
        $this->assertEquals('approved', $request->fresh()->status);
        $response->assertRedirect(route('tables.orders.show', $table->current_order_id));
    }

    /** @test */
    public function approving_a_request_for_an_already_open_table_adds_to_the_existing_order(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();
        $product = $this->makeProduct($business);
        $order = $this->openOrder($owner, $business);
        $this->post(route('table-order.public.store', $order->table->qrToken()), ['product_id' => $product->id, 'quantity' => 1]);
        $request = TableOrderRequest::first();

        $this->actingAs($owner)->post(route('tables.requests.approve', [$order->table, $request]));

        $this->assertEquals(1, TableOrder::where('restaurant_table_id', $order->table->id)->count(), 'must not open a second order for the same table');
        $this->assertEquals($order->id, TableOrderItem::first()->table_order_id);
    }

    /** @test */
    public function rejecting_a_request_never_creates_an_order_or_item(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();
        $product = $this->makeProduct($business);
        $table = RestaurantTable::create(['business_id' => $business->id, 'number' => '9', 'capacity' => 2]);
        $this->post(route('table-order.public.store', $table->qrToken()), ['product_id' => $product->id, 'quantity' => 1]);
        $request = TableOrderRequest::first();

        $this->actingAs($owner)->post(route('tables.requests.reject', [$table, $request]))->assertRedirect();

        $this->assertEquals('rejected', $request->fresh()->status);
        $this->assertEquals('available', $table->fresh()->status);
        $this->assertEquals(0, TableOrder::where('restaurant_table_id', $table->id)->count());
        $this->assertEquals(0, TableOrderItem::count());
    }

    /** @test */
    public function a_request_for_another_tables_business_cannot_be_approved_here(): void
    {
        [$owner, , $businessA] = $this->scaffoldOrg();
        [, , $businessB] = $this->scaffoldOrg();
        $tableA = RestaurantTable::create(['business_id' => $businessA->id, 'number' => '1', 'capacity' => 2]);
        $tableB = RestaurantTable::create(['business_id' => $businessB->id, 'number' => '1', 'capacity' => 2]);
        $productB = $this->makeProduct($businessB);
        $this->post(route('table-order.public.store', $tableB->qrToken()), ['product_id' => $productB->id, 'quantity' => 1]);
        $request = TableOrderRequest::first();

        // Owner of business A tries to approve business B's request via A's own table.
        $this->actingAs($owner)->post(route('tables.requests.approve', [$tableA, $request]))->assertForbidden();
    }

    /** @test */
    public function a_staff_member_from_a_different_business_cannot_approve_a_request_here(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();
        $product = $this->makeProduct($business);
        $table = RestaurantTable::create(['business_id' => $business->id, 'number' => '3', 'capacity' => 2]);
        $this->post(route('table-order.public.store', $table->qrToken()), ['product_id' => $product->id, 'quantity' => 1]);
        $request = TableOrderRequest::first();

        // A cashier who genuinely belongs to a different business, not an orphaned account.
        [$otherOwner, , $otherBusiness] = $this->scaffoldOrg();
        $staffOutsider = \App\Models\User::factory()->cashier()->create(['organization_id' => $otherOwner->organization_id]);
        $otherBusiness->users()->attach($staffOutsider->id, ['role' => 'cashier']);
        session(['active_business_id' => $otherBusiness->id]);

        $this->actingAs($staffOutsider)->post(route('tables.requests.approve', [$table, $request]))->assertForbidden();
    }

    /** @test */
    public function every_table_gets_its_own_unique_qr_token_generated_on_first_use(): void
    {
        [, , $business] = $this->scaffoldOrg();
        $t1 = RestaurantTable::create(['business_id' => $business->id, 'number' => '1', 'capacity' => 2]);
        $t2 = RestaurantTable::create(['business_id' => $business->id, 'number' => '2', 'capacity' => 2]);

        $this->assertNull($t1->qr_token);
        $token1 = $t1->qrToken();
        $token2 = $t2->qrToken();

        $this->assertNotEmpty($token1);
        $this->assertNotEquals($token1, $token2);
        $this->assertEquals($token1, $t1->fresh()->qr_token);
    }
}
