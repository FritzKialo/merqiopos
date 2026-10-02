<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    private string $secretKey;
    private string $baseUrl = 'https://api.paystack.co';

    public function __construct()
    {
        $this->secretKey = config('paystack.secret_key');
    }

    /**
     * Initialize a Paystack transaction.
     * Returns the authorization_url to redirect the user to.
     *
     * @param  string $email       Payer email
     * @param  int    $amountKes   Amount in KES (will be multiplied by 100 for Paystack)
     * @param  string $reference   Unique reference for this transaction
     * @param  array  $metadata    Extra data passed through to callback/webhook
     * @return array               ['authorization_url', 'access_code', 'reference']
     */
    public function initialize(
        string $email,
        int    $amountKes,
        string $reference,
        array  $metadata = []
    ): array {
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/transaction/initialize", [
                'email'        => $email,
                'amount'       => $amountKes * 100,  // Paystack expects kobo/cents
                'currency'     => 'KES',
                'reference'    => $reference,
                'callback_url' => route('paystack.callback'),
                'metadata'     => $metadata,
            ]);

        Log::info('Paystack initialize', [
            'reference' => $reference,
            'response'  => $response->json(),
        ]);

        if ($response->failed() || !($response->json('status'))) {
            throw new \Exception(
                $response->json('message') ?? 'Failed to initialize Paystack transaction.'
            );
        }

        return $response->json('data');
    }

    /**
     * Verify a transaction by reference.
     * Call this in the callback URL after Paystack redirects back.
     *
     * @return array  Full transaction data from Paystack
     */
    public function verify(string $reference): array
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/transaction/verify/{$reference}");

        Log::info('Paystack verify', [
            'reference' => $reference,
            'status'    => $response->json('data.status'),
        ]);

        if ($response->failed()) {
            throw new \Exception(
                $response->json('message') ?? 'Failed to verify Paystack transaction.'
            );
        }

        $data = $response->json('data');

        if (($data['status'] ?? '') !== 'success') {
            throw new \Exception('Payment was not successful: ' . ($data['gateway_response'] ?? 'Unknown'));
        }

        return $data;
    }

    /**
     * Verify that a webhook request came from Paystack.
     * Compare the X-Paystack-Signature header against HMAC-SHA512 of the raw payload.
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $expected = hash_hmac('sha512', $payload, $this->secretKey);
        return hash_equals($expected, $signature);
    }
}
