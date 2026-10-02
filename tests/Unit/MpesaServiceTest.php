<?php

namespace Tests\Unit;

use App\Services\MpesaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Daraja (M-Pesa) integration, exercised entirely against Http::fake() — no real
 * credentials or network calls needed. Written specifically so it can be trusted
 * without live Daraja access: verifies the request-building, response-handling and
 * error paths in app/Services/MpesaService.php.
 */
class MpesaServiceTest extends TestCase
{
    private function credentials(array $overrides = []): array
    {
        return array_merge([
            'consumer_key' => 'test-key', 'consumer_secret' => 'test-secret',
            'shortcode' => '174379', 'passkey' => 'test-passkey', 'environment' => 'sandbox',
        ], $overrides);
    }

    // ── Access token ─────────────────────────────────────────────────────────

    /** @test */
    public function it_fetches_and_caches_the_access_token(): void
    {
        Http::fake(['*/oauth/v1/generate*' => Http::response(['access_token' => 'tok-123', 'expires_in' => 3599], 200)]);

        $svc = new MpesaService($this->credentials());
        $this->assertEquals('tok-123', $svc->getAccessToken());

        // A second call within the cache window must not hit the network again.
        $this->assertEquals('tok-123', $svc->getAccessToken());
        Http::assertSentCount(1);
    }

    /** @test */
    public function different_credentials_get_independent_cached_tokens(): void
    {
        Http::fake([
            '*/oauth/v1/generate*' => Http::sequence()
                ->push(['access_token' => 'tok-A'], 200)
                ->push(['access_token' => 'tok-B'], 200),
        ]);

        $a = (new MpesaService($this->credentials(['consumer_key' => 'key-A'])))->getAccessToken();
        $b = (new MpesaService($this->credentials(['consumer_key' => 'key-B'])))->getAccessToken();

        $this->assertEquals('tok-A', $a);
        $this->assertEquals('tok-B', $b);
    }

    /** @test */
    public function a_token_error_throws_with_a_credentials_message(): void
    {
        Http::fake(['*/oauth/v1/generate*' => Http::response(['error' => 'invalid_client'], 400)]);

        $svc = new MpesaService($this->credentials());
        $this->expectExceptionMessage('Check your Daraja credentials.');
        $svc->getAccessToken();
    }

    /** @test */
    public function an_incapsula_bot_block_is_retried_once_and_reported_distinctly(): void
    {
        // First hit is blocked by Safaricom's sandbox bot-mitigation; the retry succeeds.
        Http::fake(['*/oauth/v1/generate*' => Http::sequence()
            ->push('<html>Request unsuccessful. Incapsula incident ID: 123</html>', 403)
            ->push(['access_token' => 'tok-after-retry'], 200)]);

        $svc = new MpesaService($this->credentials());
        $this->assertEquals('tok-after-retry', $svc->getAccessToken());
        Http::assertSentCount(2);
    }

    /** @test */
    public function a_persistent_incapsula_block_is_reported_as_an_ip_reputation_issue_not_bad_credentials(): void
    {
        Http::fake(['*/oauth/v1/generate*' => Http::response('Request unsuccessful. Incapsula incident ID: 999', 403)]);

        $svc = new MpesaService($this->credentials());
        $this->expectExceptionMessage("whitelisted");
        $svc->getAccessToken();
    }

    // ── STK Push ─────────────────────────────────────────────────────────────

    /** @test */
    public function stk_push_uses_paybill_transaction_type_with_no_till_number(): void
    {
        Http::fake([
            '*/oauth/v1/generate*'          => Http::response(['access_token' => 'tok'], 200),
            '*/mpesa/stkpush/v1/processrequest' => Http::response(['ResponseCode' => '0', 'CheckoutRequestID' => 'ws_1'], 200),
        ]);

        (new MpesaService($this->credentials()))->stkPush('0712345678', 100, 'REF1', 'Test sale');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'processrequest')
                && $request['TransactionType'] === 'CustomerPayBillOnline'
                && $request['PartyB'] === '174379'
                && $request['PhoneNumber'] === '254712345678'
                && $request['Amount'] === 100;
        });
    }

    /** @test */
    public function stk_push_uses_buy_goods_transaction_type_when_a_till_number_is_set(): void
    {
        Http::fake([
            '*/oauth/v1/generate*'          => Http::response(['access_token' => 'tok'], 200),
            '*/mpesa/stkpush/v1/processrequest' => Http::response(['ResponseCode' => '0', 'CheckoutRequestID' => 'ws_2'], 200),
        ]);

        (new MpesaService($this->credentials(['till_number' => '999888'])))->stkPush('0712345678', 50.5, 'REF2', 'Till sale');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'processrequest')
                && $request['TransactionType'] === 'CustomerBuyGoodsOnline'
                && $request['PartyB'] === '999888'
                // amount is rounded UP to the nearest shilling
                && $request['Amount'] === 51;
        });
    }

    /** @test */
    public function stk_push_truncates_account_reference_and_description_to_darajas_limits(): void
    {
        Http::fake([
            '*/oauth/v1/generate*'          => Http::response(['access_token' => 'tok'], 200),
            '*/mpesa/stkpush/v1/processrequest' => Http::response(['ResponseCode' => '0'], 200),
        ]);

        (new MpesaService($this->credentials()))->stkPush(
            '0712345678', 10, 'A-VERY-LONG-REFERENCE-STRING', 'A description longer than thirteen characters'
        );

        Http::assertSent(fn ($r) => str_contains($r->url(), 'processrequest')
            && strlen($r['AccountReference']) <= 12 && strlen($r['TransactionDesc']) <= 13);
    }

    /** @test */
    public function stk_push_is_rejected_when_daraja_returns_a_non_zero_response_code(): void
    {
        Http::fake([
            '*/oauth/v1/generate*'          => Http::response(['access_token' => 'tok'], 200),
            '*/mpesa/stkpush/v1/processrequest' => Http::response(['ResponseCode' => '1', 'ResponseDescription' => 'Insufficient funds'], 200),
        ]);

        $this->expectExceptionMessage('Insufficient funds');
        (new MpesaService($this->credentials()))->stkPush('0712345678', 10, 'REF', 'x');
    }

    /** @test */
    public function stk_push_http_failure_surfaces_darajas_error_message(): void
    {
        Http::fake([
            '*/oauth/v1/generate*'          => Http::response(['access_token' => 'tok'], 200),
            '*/mpesa/stkpush/v1/processrequest' => Http::response(['errorMessage' => 'Bad Request - Invalid Shortcode'], 400),
        ]);

        $this->expectExceptionMessage('Bad Request - Invalid Shortcode');
        (new MpesaService($this->credentials()))->stkPush('0712345678', 10, 'REF', 'x');
    }

    // ── STK status query ─────────────────────────────────────────────────────

    /** @test */
    public function stk_status_query_treats_an_error_code_only_response_as_still_processing(): void
    {
        Http::fake([
            '*/oauth/v1/generate*'      => Http::response(['access_token' => 'tok'], 200),
            '*/mpesa/stkpushquery/v1/query' => Http::response(['errorCode' => '500.001.1001', 'errorMessage' => 'The transaction is being processed'], 500),
        ]);

        $result = (new MpesaService($this->credentials()))->queryStkStatus('ws_123');

        $this->assertTrue($result['stillProcessing']);
        $this->assertNull($result['resultCode']);
    }

    /** @test */
    public function stk_status_query_treats_result_code_4999_as_still_processing_not_a_failure(): void
    {
        Http::fake([
            '*/oauth/v1/generate*'      => Http::response(['access_token' => 'tok'], 200),
            '*/mpesa/stkpushquery/v1/query' => Http::response(['ResultCode' => 4999, 'ResultDesc' => 'The transaction is still under processing'], 200),
        ]);

        $result = (new MpesaService($this->credentials()))->queryStkStatus('ws_124');

        $this->assertTrue($result['stillProcessing']);
        $this->assertEquals(4999, $result['resultCode']);
    }

    /** @test */
    public function stk_status_query_returns_a_final_result_code(): void
    {
        Http::fake([
            '*/oauth/v1/generate*'      => Http::response(['access_token' => 'tok'], 200),
            '*/mpesa/stkpushquery/v1/query' => Http::response(['ResultCode' => 0, 'ResultDesc' => 'The service request is processed successfully.'], 200),
        ]);

        $result = (new MpesaService($this->credentials()))->queryStkStatus('ws_125');

        $this->assertFalse($result['stillProcessing']);
        $this->assertEquals(0, $result['resultCode']);
    }

    // ── B2C ──────────────────────────────────────────────────────────────────

    /** @test */
    public function b2c_payment_sends_a_salary_payment_command_with_a_unique_conversation_id(): void
    {
        Http::fake([
            '*/oauth/v1/generate*'      => Http::response(['access_token' => 'tok'], 200),
            '*/mpesa/b2c/v3/paymentrequest' => Http::response(['ResponseCode' => '0', 'ConversationID' => 'conv-1', 'ResponseDescription' => 'Accept the service request successfully.'], 200),
        ]);

        $result = (new MpesaService($this->credentials()))->b2cPayment(
            '0712345678', 5000, 'testapi', 'encrypted-cred', 'Salary', 'PAYROLL-1',
            'https://example.test/result', 'https://example.test/timeout'
        );

        $this->assertTrue($result['accepted']);
        $this->assertEquals('conv-1', $result['conversationId']);
        Http::assertSent(function ($r) {
            return str_contains($r->url(), 'paymentrequest')
                && $r['CommandID'] === 'SalaryPayment'
                && $r['Amount'] === 5000
                && $r['PartyB'] === '254712345678'
                && ! empty($r['OriginatorConversationID']);
        });
    }

    /** @test */
    public function b2c_payment_reports_a_rejection_without_throwing(): void
    {
        Http::fake([
            '*/oauth/v1/generate*'      => Http::response(['access_token' => 'tok'], 200),
            '*/mpesa/b2c/v3/paymentrequest' => Http::response(['ResponseCode' => '1', 'ResponseDescription' => 'The initiator information is invalid.'], 200),
        ]);

        $result = (new MpesaService($this->credentials()))->b2cPayment(
            '0712345678', 5000, 'testapi', 'bad-cred', 'Salary', 'PAYROLL-2',
            'https://example.test/result', 'https://example.test/timeout'
        );

        $this->assertFalse($result['accepted']);
        $this->assertStringContainsString('initiator', $result['message']);
    }

    /** @test */
    public function two_b2c_payments_never_reuse_the_same_originator_conversation_id(): void
    {
        Http::fake([
            '*/oauth/v1/generate*'      => Http::response(['access_token' => 'tok'], 200),
            '*/mpesa/b2c/v3/paymentrequest' => Http::response(['ResponseCode' => '0', 'ConversationID' => 'c'], 200),
        ]);

        $svc = new MpesaService($this->credentials());
        $svc->b2cPayment('0712345678', 100, 'i', 'c', 'r', 'occ-1', 'https://x/r', 'https://x/t');
        $svc->b2cPayment('0712345678', 100, 'i', 'c', 'r', 'occ-2', 'https://x/r', 'https://x/t');

        $ids = [];
        Http::assertSent(function ($r) use (&$ids) {
            if (str_contains($r->url(), 'paymentrequest')) $ids[] = $r['OriginatorConversationID'];
            return true;
        });
        $this->assertCount(2, array_unique($ids));
    }

    // ── C2B URL registration ─────────────────────────────────────────────────

    /** @test */
    public function c2b_url_registration_succeeds(): void
    {
        Http::fake([
            '*/oauth/v1/generate*'   => Http::response(['access_token' => 'tok'], 200),
            '*/mpesa/c2b/v1/registerurl' => Http::response(['ResponseCode' => '0', 'ResponseDescription' => 'success'], 200),
        ]);

        $result = (new MpesaService($this->credentials()))->registerC2BUrls('https://x/confirm', 'https://x/validate');
        $this->assertEquals('0', $result['ResponseCode']);
    }

    /** @test */
    public function c2b_url_registration_failure_throws(): void
    {
        Http::fake([
            '*/oauth/v1/generate*'   => Http::response(['access_token' => 'tok'], 200),
            '*/mpesa/c2b/v1/registerurl' => Http::response(['ResponseDescription' => 'Invalid ShortCode'], 400),
        ]);

        $this->expectExceptionMessage('Invalid ShortCode');
        (new MpesaService($this->credentials()))->registerC2BUrls('https://x/confirm', 'https://x/validate');
    }

    // ── QR code ──────────────────────────────────────────────────────────────

    /** @test */
    public function qr_code_generation_returns_the_base64_image(): void
    {
        Http::fake([
            '*/oauth/v1/generate*'    => Http::response(['access_token' => 'tok'], 200),
            '*/mpesa/qrcode/v1/generate' => Http::response(['ResponseCode' => '00', 'QRCode' => 'base64-image-data'], 200),
        ]);

        $qr = (new MpesaService($this->credentials()))->generateQrCode(500, 'REF', 'My Shop');
        $this->assertEquals('base64-image-data', $qr);
    }

    /** @test */
    public function qr_code_generation_failure_throws(): void
    {
        Http::fake([
            '*/oauth/v1/generate*'    => Http::response(['access_token' => 'tok'], 200),
            '*/mpesa/qrcode/v1/generate' => Http::response(['ResponseDescription' => 'Amount is required'], 200),
        ]);

        $this->expectExceptionMessage('Amount is required');
        (new MpesaService($this->credentials()))->generateQrCode(0, 'REF', 'My Shop');
    }

    // ── Phone formatting ─────────────────────────────────────────────────────

    /** @test */
    public function phone_numbers_are_normalised_to_254_format(): void
    {
        $svc = new MpesaService($this->credentials());
        $this->assertEquals('254712345678', $svc->formatPhone('0712345678'));
        $this->assertEquals('254712345678', $svc->formatPhone('+254712345678'));
        $this->assertEquals('254712345678', $svc->formatPhone('254712345678'));
        $this->assertEquals('254712345678', $svc->formatPhone('712345678'));
    }
}
