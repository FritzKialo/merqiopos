<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\PesapalTransaction;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Feature\Helpers\CreatesOrganization;
use Tests\TestCase;

class PesapalControllerTest extends TestCase
{
    use CreatesOrganization;

    private function configuredBusiness(Business $business): Business
    {
        $business->update([
            'pesapal_consumer_key'    => 'test-key',
            'pesapal_consumer_secret' => 'test-secret',
            'pesapal_environment'     => 'sandbox',
            'pesapal_ipn_id'          => 'ipn-123',
        ]);

        return $business->fresh();
    }

    private function makeSale(Business $business, array $overrides = []): Sale
    {
        return Sale::create(array_merge([
            'business_id' => $business->id, 'user_id' => $business->users()->first()->id,
            'invoice_number' => 'INV-' . Str::random(8), 'subtotal' => 1000, 'total_amount' => 1000,
            'paid_amount' => 0, 'balance_due' => 1000,
            'payment_method' => 'cash', 'payment_status' => 'unpaid', 'sale_status' => 'completed',
        ], $overrides));
    }

    // ── initiate ─────────────────────────────────────────────────────────────

    /** @test */
    public function initiate_creates_a_transaction_and_redirects_to_the_hosted_checkout(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();
        $this->configuredBusiness($business);
        $sale = $this->makeSale($business);

        Http::fake([
            '*/api/Auth/RequestToken'              => Http::response(['token' => 'tok'], 200),
            '*/api/Transactions/SubmitOrderRequest' => Http::response([
                'redirect_url' => 'https://cybqa.pesapal.com/pay/xyz', 'order_tracking_id' => 'track-1',
            ], 200),
        ]);

        $response = $this->actingAs($owner)->post(route('pesapal.initiate'), ['sale_id' => $sale->id]);

        $response->assertRedirect('https://cybqa.pesapal.com/pay/xyz');
        $this->assertDatabaseHas('pesapal_transactions', [
            'business_id' => $business->id, 'sale_id' => $sale->id,
            'order_tracking_id' => 'track-1', 'status' => 'PENDING', 'amount' => 1000,
        ]);
    }

    /** @test */
    public function initiate_is_blocked_when_pesapal_is_not_configured(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();
        $sale = $this->makeSale($business);

        $response = $this->actingAs($owner)->post(route('pesapal.initiate'), ['sale_id' => $sale->id]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('pesapal_transactions', 0);
    }

    /** @test */
    public function initiate_is_blocked_for_an_already_paid_sale(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();
        $this->configuredBusiness($business);
        $sale = $this->makeSale($business, ['payment_status' => 'paid', 'paid_amount' => 1000, 'balance_due' => 0]);

        $response = $this->actingAs($owner)->post(route('pesapal.initiate'), ['sale_id' => $sale->id]);

        $response->assertSessionHas('error', 'This sale is already paid.');
        $this->assertDatabaseCount('pesapal_transactions', 0);
    }

    /** @test */
    public function initiate_rejects_a_sale_belonging_to_another_business(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();
        $this->configuredBusiness($business);
        [, , $otherBusiness] = $this->scaffoldOrg();
        $foreignSale = $this->makeSale($otherBusiness);

        $response = $this->actingAs($owner)->post(route('pesapal.initiate'), ['sale_id' => $foreignSale->id]);

        $response->assertSessionHas('error', 'Unauthorized.');
        $this->assertDatabaseCount('pesapal_transactions', 0);
    }

    // ── callback ─────────────────────────────────────────────────────────────

    /** @test */
    public function callback_with_missing_params_redirects_with_an_error(): void
    {
        [$owner] = $this->scaffoldOrg();

        $response = $this->actingAs($owner)->get(route('pesapal.callback'));

        $response->assertRedirect(route('sales.index'));
        $response->assertSessionHas('error');
    }

    /** @test */
    public function callback_for_an_unknown_merchant_reference_redirects_with_an_error(): void
    {
        [$owner] = $this->scaffoldOrg();

        $response = $this->actingAs($owner)->get(route('pesapal.callback', [
            'OrderTrackingId' => 'track-x', 'OrderMerchantReference' => 'PSP-UNKNOWN',
        ]));

        $response->assertRedirect(route('sales.index'));
        $response->assertSessionHas('error');
    }

    /** @test */
    public function callback_short_circuits_when_already_complete(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();
        $sale = $this->makeSale($business);
        PesapalTransaction::create([
            'business_id' => $business->id, 'sale_id' => $sale->id,
            'merchant_reference' => 'PSP-DONE', 'order_tracking_id' => 'track-done',
            'amount' => 1000, 'status' => 'COMPLETE',
        ]);

        $response = $this->actingAs($owner)->get(route('pesapal.callback', [
            'OrderTrackingId' => 'track-done', 'OrderMerchantReference' => 'PSP-DONE',
        ]));

        $response->assertRedirect(route('sales.show', $sale));
        $response->assertSessionHas('success');
    }

    /** @test */
    public function a_successful_callback_marks_the_sale_paid(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();
        $this->configuredBusiness($business);
        $sale = $this->makeSale($business);
        PesapalTransaction::create([
            'business_id' => $business->id, 'sale_id' => $sale->id,
            'merchant_reference' => 'PSP-PEND', 'order_tracking_id' => 'track-pend',
            'amount' => 1000, 'status' => 'PENDING',
        ]);

        Http::fake([
            '*/api/Auth/RequestToken'                  => Http::response(['token' => 'tok'], 200),
            '*/api/Transactions/GetTransactionStatus*' => Http::response([
                'payment_status_description' => 'COMPLETED', 'confirmation_code' => 'CONF1', 'payment_method' => 'Visa',
            ], 200),
        ]);

        $response = $this->actingAs($owner)->get(route('pesapal.callback', [
            'OrderTrackingId' => 'track-pend', 'OrderMerchantReference' => 'PSP-PEND',
        ]));

        $response->assertRedirect(route('sales.show', $sale));
        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'payment_status' => 'paid', 'balance_due' => 0]);
        $this->assertDatabaseHas('pesapal_transactions', ['merchant_reference' => 'PSP-PEND', 'status' => 'COMPLETE', 'confirmation_code' => 'CONF1']);
    }

    // ── ipn ──────────────────────────────────────────────────────────────────

    /** @test */
    public function ipn_acks_with_the_expected_shape_for_an_unknown_transaction(): void
    {
        $response = $this->post(route('pesapal.ipn', ['OrderTrackingId' => 'track-z', 'OrderMerchantReference' => 'PSP-GHOST']));

        $response->assertOk();
        $response->assertJson(['orderNotificationType' => 'IPNCHANGE', 'orderTrackingId' => 'track-z', 'orderMerchantReference' => 'PSP-GHOST', 'status' => '200']);
    }

    /** @test */
    public function ipn_short_circuits_when_already_complete_without_calling_pesapal(): void
    {
        [, , $business] = $this->scaffoldOrg();
        $sale = $this->makeSale($business);
        PesapalTransaction::create([
            'business_id' => $business->id, 'sale_id' => $sale->id,
            'merchant_reference' => 'PSP-DONE2', 'order_tracking_id' => 'track-done2',
            'amount' => 1000, 'status' => 'COMPLETE',
        ]);

        Http::fake(); // any outbound call would fail the test via assertNothingSent below

        $response = $this->post(route('pesapal.ipn', ['OrderTrackingId' => 'track-done2', 'OrderMerchantReference' => 'PSP-DONE2']));

        $response->assertOk();
        Http::assertNothingSent();
    }

    /** @test */
    public function ipn_verifies_a_pending_transaction_and_completes_the_sale(): void
    {
        [, , $business] = $this->scaffoldOrg();
        $this->configuredBusiness($business);
        $sale = $this->makeSale($business);
        PesapalTransaction::create([
            'business_id' => $business->id, 'sale_id' => $sale->id,
            'merchant_reference' => 'PSP-IPN', 'order_tracking_id' => 'track-ipn',
            'amount' => 1000, 'status' => 'PENDING',
        ]);

        Http::fake([
            '*/api/Auth/RequestToken'                  => Http::response(['token' => 'tok'], 200),
            '*/api/Transactions/GetTransactionStatus*' => Http::response([
                'payment_status_description' => 'COMPLETED', 'confirmation_code' => 'CONF2', 'payment_method' => 'Mpesa',
            ], 200),
        ]);

        $response = $this->post(route('pesapal.ipn', ['OrderTrackingId' => 'track-ipn', 'OrderMerchantReference' => 'PSP-IPN']));

        $response->assertOk();
        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'payment_status' => 'paid']);
    }

    // ── status ───────────────────────────────────────────────────────────────

    /** @test */
    public function status_reports_complete_for_a_paid_sale_without_checking_the_transaction(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();
        $sale = $this->makeSale($business, ['payment_status' => 'paid', 'paid_amount' => 1000, 'balance_due' => 0]);

        $response = $this->actingAs($owner)->get(route('pesapal.status', ['sale_id' => $sale->id]));

        $response->assertOk()->assertJson(['status' => 'COMPLETE']);
    }

    /** @test */
    public function status_reports_the_latest_transactions_status_for_an_unpaid_sale(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();
        $sale = $this->makeSale($business);
        $old = PesapalTransaction::create([
            'business_id' => $business->id, 'sale_id' => $sale->id,
            'merchant_reference' => 'PSP-OLD', 'order_tracking_id' => 'track-old',
            'amount' => 1000, 'status' => 'FAILED',
        ]);
        $old->forceFill(['created_at' => now()->subMinute()])->save();

        PesapalTransaction::create([
            'business_id' => $business->id, 'sale_id' => $sale->id,
            'merchant_reference' => 'PSP-NEW', 'order_tracking_id' => 'track-new',
            'amount' => 1000, 'status' => 'PENDING',
        ]);

        $response = $this->actingAs($owner)->get(route('pesapal.status', ['sale_id' => $sale->id]));

        $response->assertOk()->assertJson(['status' => 'PENDING']);
    }

    // ── settings: register IPN ──────────────────────────────────────────────

    /** @test */
    public function registering_ipn_stores_the_returned_id(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();
        $business->update([
            'pesapal_consumer_key' => 'test-key', 'pesapal_consumer_secret' => 'test-secret', 'pesapal_environment' => 'sandbox',
        ]);

        Http::fake([
            '*/api/Auth/RequestToken'    => Http::response(['token' => 'tok'], 200),
            '*/api/URLSetup/RegisterIPN' => Http::response(['ipn_id' => 'ipn-new'], 200),
        ]);

        $response = $this->actingAs($owner)->post(route('pesapal.register-ipn'));

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('businesses', ['id' => $business->id, 'pesapal_ipn_id' => 'ipn-new']);
    }

    /** @test */
    public function registering_ipn_without_saved_credentials_fails_cleanly(): void
    {
        [$owner] = $this->scaffoldOrg();

        $response = $this->actingAs($owner)->post(route('pesapal.register-ipn'));

        $response->assertSessionHas('error', 'Save your Pesapal credentials first.');
    }
}
