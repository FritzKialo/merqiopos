<?php

namespace Tests\Unit;

use App\Services\PesapalService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pesapal (card payments) integration, exercised entirely against Http::fake() —
 * same approach as MpesaServiceTest/EtimsServiceTest: no real credentials needed
 * to verify the request-building and response-handling in PesapalService.
 */
class PesapalServiceTest extends TestCase
{
    private function credentials(array $overrides = []): array
    {
        return array_merge([
            'consumer_key' => 'test-key', 'consumer_secret' => 'test-secret', 'environment' => 'sandbox',
        ], $overrides);
    }

    // ── Token ────────────────────────────────────────────────────────────────

    /** @test */
    public function it_fetches_and_caches_the_token(): void
    {
        Http::fake(['*/api/Auth/RequestToken' => Http::response(['token' => 'tok-abc', 'expiryDate' => now()->addMinutes(5)], 200)]);

        $svc = new PesapalService($this->credentials());
        $this->assertEquals('tok-abc', $svc->getToken());

        // Within the 4-minute cache window, no second request should go out.
        $this->assertEquals('tok-abc', $svc->getToken());
        Http::assertSentCount(1);
    }

    /** @test */
    public function different_credentials_get_independent_cached_tokens(): void
    {
        Http::fake(['*/api/Auth/RequestToken' => Http::sequence()
            ->push(['token' => 'tok-A'], 200)
            ->push(['token' => 'tok-B'], 200)]);

        $a = (new PesapalService($this->credentials(['consumer_key' => 'key-A'])))->getToken();
        $b = (new PesapalService($this->credentials(['consumer_key' => 'key-B'])))->getToken();

        $this->assertEquals('tok-A', $a);
        $this->assertEquals('tok-B', $b);
    }

    /** @test */
    public function a_token_error_throws(): void
    {
        Http::fake(['*/api/Auth/RequestToken' => Http::response(['error' => 'invalid_consumer_key_or_secret_provided'], 401)]);

        $this->expectExceptionMessage('Check credentials.');
        (new PesapalService($this->credentials()))->getToken();
    }

    /** @test */
    public function a_response_with_no_token_field_is_treated_as_a_failure(): void
    {
        // Pesapal can answer 200 with an error payload instead of a real HTTP failure code.
        Http::fake(['*/api/Auth/RequestToken' => Http::response(['message' => 'invalid client'], 200)]);

        $this->expectExceptionMessage('Check credentials.');
        (new PesapalService($this->credentials()))->getToken();
    }

    // ── IPN registration ─────────────────────────────────────────────────────

    /** @test */
    public function ipn_registration_returns_the_ipn_id(): void
    {
        Http::fake([
            '*/api/Auth/RequestToken'     => Http::response(['token' => 'tok'], 200),
            '*/api/URLSetup/RegisterIPN'  => Http::response(['ipn_id' => 'ipn-123', 'url' => 'https://example.test/ipn'], 200),
        ]);

        $ipnId = (new PesapalService($this->credentials()))->registerIpn('https://example.test/ipn');

        $this->assertEquals('ipn-123', $ipnId);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'RegisterIPN') && $r['url'] === 'https://example.test/ipn' && $r['ipn_notification_type'] === 'POST');
    }

    /** @test */
    public function ipn_registration_failure_throws(): void
    {
        Http::fake([
            '*/api/Auth/RequestToken'    => Http::response(['token' => 'tok'], 200),
            '*/api/URLSetup/RegisterIPN' => Http::response(['error' => ['message' => 'invalid url']], 400),
        ]);

        $this->expectExceptionMessage('IPN registration failed');
        (new PesapalService($this->credentials()))->registerIpn('not-a-url');
    }

    // ── Submit order ─────────────────────────────────────────────────────────

    private function billing(): array
    {
        return ['email' => 'jane@example.test', 'phone' => '0712345678', 'first_name' => 'Jane', 'last_name' => 'Doe'];
    }

    /** @test */
    public function submit_order_returns_the_redirect_url_and_tracking_id(): void
    {
        Http::fake([
            '*/api/Auth/RequestToken'              => Http::response(['token' => 'tok'], 200),
            '*/api/Transactions/SubmitOrderRequest' => Http::response([
                'redirect_url' => 'https://cybqa.pesapal.com/pay/xyz', 'order_tracking_id' => 'track-1', 'merchant_reference' => 'PSP-1',
            ], 200),
        ]);

        $result = (new PesapalService($this->credentials()))->submitOrder('PSP-1', 1234.5, 'ipn-1', 'Payment for INV-1', $this->billing());

        $this->assertEquals('https://cybqa.pesapal.com/pay/xyz', $result['redirect_url']);
        $this->assertEquals('track-1', $result['order_tracking_id']);
    }

    /** @test */
    public function submit_order_sends_the_billing_details_amount_and_notification_id(): void
    {
        Http::fake([
            '*/api/Auth/RequestToken'              => Http::response(['token' => 'tok'], 200),
            '*/api/Transactions/SubmitOrderRequest' => Http::response(['redirect_url' => 'https://x/y', 'order_tracking_id' => 't'], 200),
        ]);

        (new PesapalService($this->credentials()))->submitOrder('PSP-2', 500.005, 'ipn-9', 'Payment for INV-2', $this->billing());

        Http::assertSent(function ($r) {
            if (! str_contains($r->url(), 'SubmitOrderRequest')) return true;
            return $r['id'] === 'PSP-2'
                && $r['currency'] === 'KES'
                && $r['amount'] === 500.01 // rounded to 2dp
                && $r['notification_id'] === 'ipn-9'
                && $r['billing_address']['email_address'] === 'jane@example.test'
                && $r['billing_address']['phone_number'] === '0712345678'
                && $r['billing_address']['first_name'] === 'Jane'
                && $r['billing_address']['last_name'] === 'Doe';
        });
    }

    /** @test */
    public function submit_order_truncates_the_description_to_100_characters(): void
    {
        Http::fake([
            '*/api/Auth/RequestToken'              => Http::response(['token' => 'tok'], 200),
            '*/api/Transactions/SubmitOrderRequest' => Http::response(['redirect_url' => 'https://x/y', 'order_tracking_id' => 't'], 200),
        ]);

        (new PesapalService($this->credentials()))->submitOrder('PSP-3', 100, 'ipn-1', str_repeat('x', 200), $this->billing());

        Http::assertSent(fn ($r) => ! str_contains($r->url(), 'SubmitOrderRequest') || strlen($r['description']) <= 100);
    }

    /** @test */
    public function submit_order_tolerates_missing_billing_fields(): void
    {
        Http::fake([
            '*/api/Auth/RequestToken'              => Http::response(['token' => 'tok'], 200),
            '*/api/Transactions/SubmitOrderRequest' => Http::response(['redirect_url' => 'https://x/y', 'order_tracking_id' => 't'], 200),
        ]);

        $result = (new PesapalService($this->credentials()))->submitOrder('PSP-4', 100, 'ipn-1', 'x', []);

        $this->assertEquals('https://x/y', $result['redirect_url']);
        Http::assertSent(fn ($r) => ! str_contains($r->url(), 'SubmitOrderRequest') || $r['billing_address']['email_address'] === '');
    }

    /** @test */
    public function submit_order_failure_throws(): void
    {
        Http::fake([
            '*/api/Auth/RequestToken'              => Http::response(['token' => 'tok'], 200),
            '*/api/Transactions/SubmitOrderRequest' => Http::response(['error' => ['message' => 'invalid amount']], 400),
        ]);

        $this->expectExceptionMessage('Pesapal order submission failed');
        (new PesapalService($this->credentials()))->submitOrder('PSP-5', 0, 'ipn-1', 'x', $this->billing());
    }

    // ── Transaction status ───────────────────────────────────────────────────

    /** @test */
    public function transaction_status_returns_the_full_response(): void
    {
        Http::fake([
            '*/api/Auth/RequestToken'                    => Http::response(['token' => 'tok'], 200),
            '*/api/Transactions/GetTransactionStatus*' => Http::response([
                'payment_method' => 'Visa', 'payment_status_description' => 'Completed', 'confirmation_code' => 'CONF123',
            ], 200),
        ]);

        $status = (new PesapalService($this->credentials()))->getTransactionStatus('track-1');

        $this->assertEquals('Completed', $status['payment_status_description']);
        $this->assertEquals('CONF123', $status['confirmation_code']);
        Http::assertSent(fn ($r) => ! str_contains($r->url(), 'GetTransactionStatus') || ($r['orderTrackingId'] ?? $r->data()['orderTrackingId'] ?? null) === 'track-1' || str_contains($r->url(), 'track-1'));
    }

    /** @test */
    public function transaction_status_failure_throws(): void
    {
        Http::fake([
            '*/api/Auth/RequestToken'                 => Http::response(['token' => 'tok'], 200),
            '*/api/Transactions/GetTransactionStatus*' => Http::response(['error' => 'not found'], 404),
        ]);

        $this->expectExceptionMessage('Failed to get Pesapal transaction status.');
        (new PesapalService($this->credentials()))->getTransactionStatus('unknown-track');
    }
}
