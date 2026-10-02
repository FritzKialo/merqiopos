<?php

namespace Tests\Feature;

use App\Models\MpesaTransaction;
use App\Models\PaystackTransaction;
use App\Models\PromoCode;
use App\Models\PromoCodeRedemption;
use App\Models\User;
use App\Services\PromoCodeService;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Helpers\CreatesOrganization;
use Tests\TestCase;

class PromoCodeTest extends TestCase
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

    private function asAdmin()
    {
        return $this->actingAs($this->admin)->withSession(['2fa_verified' => true, '2fa_last_verified' => time()]);
    }

    // ── PromoCodeService::validate() ────────────────────────────────────────

    /** @test */
    public function a_percentage_code_discounts_the_amount_correctly(): void
    {
        [, $org] = $this->scaffoldOrg('growth');
        $promo = PromoCode::create(['code' => 'SAVE20', 'discount_type' => 'percentage', 'discount_value' => 20]);

        $result = app(PromoCodeService::class)->validate('save20', 'growth', $org, 1000);

        $this->assertTrue($result['valid']);
        $this->assertEquals(200, $result['discountAmount']);
        $this->assertEquals(800, $result['discountedAmount']);
    }

    /** @test */
    public function a_fixed_code_discounts_the_amount_correctly(): void
    {
        [, $org] = $this->scaffoldOrg('growth');
        PromoCode::create(['code' => 'FLAT500', 'discount_type' => 'fixed', 'discount_value' => 500]);

        $result = app(PromoCodeService::class)->validate('FLAT500', 'growth', $org, 2999);

        $this->assertTrue($result['valid']);
        $this->assertEquals(500, $result['discountAmount']);
        $this->assertEquals(2499, $result['discountedAmount']);
    }

    /** @test */
    public function an_unknown_code_is_rejected(): void
    {
        [, $org] = $this->scaffoldOrg('growth');

        $result = app(PromoCodeService::class)->validate('NOPE', 'growth', $org, 1000);

        $this->assertFalse($result['valid']);
    }

    /** @test */
    public function an_inactive_code_is_rejected(): void
    {
        [, $org] = $this->scaffoldOrg('growth');
        PromoCode::create(['code' => 'OFF', 'discount_type' => 'fixed', 'discount_value' => 100, 'is_active' => false]);

        $result = app(PromoCodeService::class)->validate('OFF', 'growth', $org, 1000);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('no longer active', $result['message']);
    }

    /** @test */
    public function an_expired_code_is_rejected(): void
    {
        [, $org] = $this->scaffoldOrg('growth');
        PromoCode::create(['code' => 'OLD', 'discount_type' => 'fixed', 'discount_value' => 100, 'expires_at' => now()->subDay()]);

        $result = app(PromoCodeService::class)->validate('OLD', 'growth', $org, 1000);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('expired', $result['message']);
    }

    /** @test */
    public function an_exhausted_code_is_rejected(): void
    {
        [, $org] = $this->scaffoldOrg('growth');
        PromoCode::create([
            'code' => 'LIMITED', 'discount_type' => 'fixed', 'discount_value' => 100,
            'max_redemptions' => 1, 'times_redeemed' => 1,
        ]);

        $result = app(PromoCodeService::class)->validate('LIMITED', 'growth', $org, 1000);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('redemption limit', $result['message']);
    }

    /** @test */
    public function a_code_restricted_to_other_plans_does_not_apply(): void
    {
        [, $org] = $this->scaffoldOrg('growth');
        PromoCode::create([
            'code' => 'SOLOONLY', 'discount_type' => 'fixed', 'discount_value' => 100,
            'applicable_plans' => ['solo'],
        ]);

        $result = app(PromoCodeService::class)->validate('SOLOONLY', 'growth', $org, 1000);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('does not apply', $result['message']);
    }

    /** @test */
    public function a_code_already_redeemed_by_this_organization_cannot_be_reused(): void
    {
        [, $org] = $this->scaffoldOrg('growth');
        $promo = PromoCode::create(['code' => 'ONEUSE', 'discount_type' => 'fixed', 'discount_value' => 100]);

        app(PromoCodeService::class)->redeem($promo, $org, null, 1000, 100);

        $result = app(PromoCodeService::class)->validate('ONEUSE', 'growth', $org, 1000);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('already used', $result['message']);
    }

    /** @test */
    public function redeeming_increments_the_usage_counter_and_records_a_redemption(): void
    {
        [, $org] = $this->scaffoldOrg('growth');
        $promo = PromoCode::create(['code' => 'TRACK', 'discount_type' => 'fixed', 'discount_value' => 100]);

        app(PromoCodeService::class)->redeem($promo, $org, null, 1000, 100);

        $this->assertEquals(1, $promo->fresh()->times_redeemed);
        $this->assertDatabaseHas('promo_code_redemptions', [
            'promo_code_id'   => $promo->id,
            'organization_id' => $org->id,
            'original_amount' => 1000,
            'discount_amount' => 100,
            'final_amount'    => 900,
        ]);
    }

    // ── Settings preview endpoint ────────────────────────────────────────────

    /** @test */
    public function the_preview_endpoint_returns_the_discounted_price_for_a_valid_code(): void
    {
        [$owner, $org] = $this->scaffoldOrg('growth');
        PromoCode::create(['code' => 'PREVIEW10', 'discount_type' => 'percentage', 'discount_value' => 10]);

        $response = $this->actingAs($owner)
            ->postJson(route('settings.subscription.apply-promo'), ['code' => 'PREVIEW10', 'plan' => 'growth']);

        $response->assertOk()->assertJsonPath('valid', true)
            ->assertJsonPath('originalAmount', 2999)
            ->assertJsonPath('discountedAmount', 2699.1);
        $this->assertEqualsWithDelta(299.9, $response->json('discountAmount'), 0.001);
    }

    /** @test */
    public function the_preview_endpoint_reports_an_invalid_code(): void
    {
        [$owner] = $this->scaffoldOrg('growth');

        $response = $this->actingAs($owner)
            ->postJson(route('settings.subscription.apply-promo'), ['code' => 'NOPE', 'plan' => 'growth']);

        $response->assertOk()->assertJson(['valid' => false]);
    }

    // ── Admin CRUD ────────────────────────────────────────────────────────────

    /** @test */
    public function admin_can_create_a_promo_code(): void
    {
        $this->asAdmin()
            ->post(route('admin.promo-codes.store'), [
                'code'           => 'launch20',
                'discount_type'  => 'percentage',
                'discount_value' => 20,
            ])
            ->assertRedirect(route('admin.promo-codes.index'));

        $this->assertDatabaseHas('promo_codes', ['code' => 'LAUNCH20', 'created_by' => $this->admin->id]);
    }

    /** @test */
    public function admin_cannot_create_a_percentage_discount_over_100(): void
    {
        $this->asAdmin()
            ->post(route('admin.promo-codes.store'), [
                'code'           => 'TOOMUCH',
                'discount_type'  => 'percentage',
                'discount_value' => 150,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('promo_codes', ['code' => 'TOOMUCH']);
    }

    /** @test */
    public function admin_can_toggle_and_delete_a_promo_code(): void
    {
        $promo = PromoCode::create(['code' => 'TOGGLE', 'discount_type' => 'fixed', 'discount_value' => 50]);

        $this->asAdmin()->patch(route('admin.promo-codes.toggle', $promo))->assertRedirect();
        $this->assertFalse($promo->fresh()->is_active);

        $this->asAdmin()->delete(route('admin.promo-codes.destroy', $promo))->assertRedirect();
        $this->assertDatabaseMissing('promo_codes', ['id' => $promo->id]);
    }

    /** @test */
    public function a_non_admin_cannot_manage_promo_codes(): void
    {
        [$owner] = $this->scaffoldOrg('growth');

        $this->actingAs($owner)->get(route('admin.promo-codes.index'))->assertForbidden();
    }

    // ── Checkout wiring ───────────────────────────────────────────────────────

    /** @test */
    public function paystack_checkout_charges_the_discounted_amount_and_stores_the_code(): void
    {
        [$owner, $org] = $this->scaffoldOrg('growth');
        PromoCode::create(['code' => 'PAYDISC', 'discount_type' => 'fixed', 'discount_value' => 500]);

        Http::fake([
            'api.paystack.co/*' => Http::response([
                'status' => true,
                'data'   => ['authorization_url' => 'https://paystack.test/checkout', 'access_code' => 'abc', 'reference' => 'ref'],
            ], 200),
        ]);

        $this->actingAs($owner)
            ->post(route('paystack.subscribe'), ['plan' => 'growth', 'promo_code' => 'PAYDISC'])
            ->assertRedirect('https://paystack.test/checkout');

        $this->assertDatabaseHas('paystack_transactions', [
            'organization_id' => $org->id,
            'plan'            => 'growth',
            'amount'          => 2499,
            'promo_code'      => 'PAYDISC',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'transaction/initialize')
                && $request['amount'] === 2499 * 100;
        });
    }

    /** @test */
    public function an_invalid_promo_code_blocks_paystack_checkout(): void
    {
        [$owner] = $this->scaffoldOrg('growth');

        $this->actingAs($owner)
            ->post(route('paystack.subscribe'), ['plan' => 'growth', 'promo_code' => 'GARBAGE'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('paystack_transactions', ['promo_code' => 'GARBAGE']);
    }

    /** @test */
    public function completing_a_discounted_paystack_payment_redeems_the_code_once(): void
    {
        [$owner, $org] = $this->scaffoldOrg('growth');
        $promo = PromoCode::create(['code' => 'REDEEMME', 'discount_type' => 'fixed', 'discount_value' => 500]);

        $pending = PaystackTransaction::create([
            'organization_id' => $org->id,
            'user_id'         => $owner->id,
            'reference'       => 'SUB-TEST1',
            'plan'            => 'growth',
            'amount'          => 2499,
            'promo_code'      => 'REDEEMME',
            'status'          => 'pending',
        ]);

        Http::fake([
            'api.paystack.co/*' => Http::response([
                'data' => ['status' => 'success', 'amount' => 249900],
            ], 200),
        ]);

        $this->actingAs($owner)
            ->get(route('paystack.callback', ['reference' => 'SUB-TEST1']))
            ->assertRedirect(route('settings.subscription'));

        $this->assertEquals(1, $promo->fresh()->times_redeemed);
        $this->assertDatabaseHas('promo_code_redemptions', [
            'promo_code_id'   => $promo->id,
            'organization_id' => $org->id,
            'discount_amount' => 500,
        ]);
    }

    /** @test */
    public function mpesa_subscribe_sends_the_stk_push_for_the_discounted_amount(): void
    {
        [$owner] = $this->scaffoldOrg('growth');
        PromoCode::create(['code' => 'MPESA10', 'discount_type' => 'percentage', 'discount_value' => 10]);

        Http::fake([
            '*oauth/v1/generate*' => Http::response(['access_token' => 'fake-token'], 200),
            '*stkpush*'           => Http::response([
                'CheckoutRequestID' => 'ws_CO_TEST123',
                'MerchantRequestID' => 'merchant_1',
                'ResponseCode'      => '0',
            ], 200),
        ]);

        $response = $this->actingAs($owner)
            ->postJson(route('mpesa.subscribe'), [
                'plan'       => 'growth',
                'phone'      => '0712345678',
                'promo_code' => 'MPESA10',
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('mpesa_transactions', [
            'type'        => 'subscription',
            'amount'      => 2699.1,
            'promo_code'  => 'MPESA10',
            'checkout_id' => 'ws_CO_TEST123',
        ]);
    }

    /** @test */
    public function completing_a_discounted_mpesa_subscription_redeems_the_code(): void
    {
        [$owner, $org] = $this->scaffoldOrg('growth');
        $promo = PromoCode::create(['code' => 'MPREDEEM', 'discount_type' => 'fixed', 'discount_value' => 300]);

        $transaction = MpesaTransaction::create([
            'sale_id'     => null,
            'type'        => 'subscription',
            'phone'       => '254712345678',
            'amount'      => 2699,
            'checkout_id' => 'ws_CO_REDEEM',
            'api_ref'     => 'growth:' . $org->id,
            'promo_code'  => 'MPREDEEM',
            'status'      => 'PENDING',
        ]);

        $callbackPayload = [
            'Body' => [
                'stkCallback' => [
                    'CheckoutRequestID' => 'ws_CO_REDEEM',
                    'ResultCode'        => 0,
                    'CallbackMetadata'  => [
                        'Item' => [
                            ['Name' => 'Amount', 'Value' => 2699],
                            ['Name' => 'MpesaReceiptNumber', 'Value' => 'TEST123'],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson(route('mpesa.callback'), $callbackPayload)->assertOk();

        $this->assertEquals(1, $promo->fresh()->times_redeemed);
        $this->assertDatabaseHas('promo_code_redemptions', [
            'promo_code_id'   => $promo->id,
            'organization_id' => $org->id,
        ]);
    }
}
