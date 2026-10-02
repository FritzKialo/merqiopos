<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PesapalService
{
    private string $consumerKey;
    private string $consumerSecret;
    private string $baseUrl;
    private string $environment;

    /**
     * Per-business credentials.
     * Pass $credentials from Business::pesapalCredentials() for tenant payments.
     */
    public function __construct(array $credentials = [])
    {
        $this->consumerKey    = $credentials['consumer_key']    ?? config('pesapal.consumer_key', '');
        $this->consumerSecret = $credentials['consumer_secret'] ?? config('pesapal.consumer_secret', '');
        $this->environment    = $credentials['environment']     ?? config('pesapal.environment', 'sandbox');

        $this->baseUrl = $this->environment === 'production'
            ? 'https://pay.pesapal.com/v3'
            : 'https://cybqa.pesapal.com/pesapalv3';
    }

    /**
     * Get OAuth token. Cached per credential pair for 4 minutes (Pesapal tokens last 5min).
     */
    public function getToken(): string
    {
        $cacheKey = 'pesapal_token_' . md5($this->consumerKey);

        return Cache::remember($cacheKey, 4 * 60, function () {
            $response = Http::post("{$this->baseUrl}/api/Auth/RequestToken", [
                'consumer_key'    => $this->consumerKey,
                'consumer_secret' => $this->consumerSecret,
            ]);

            if ($response->failed() || !$response->json('token')) {
                Log::error('Pesapal token error', ['body' => $response->body()]);
                throw new \Exception('Failed to get Pesapal token. Check credentials.');
            }

            return $response->json('token');
        });
    }

    /**
     * Register an IPN (Instant Payment Notification) URL.
     * Must be done once per business. Returns the IPN ID to store on the business.
     */
    public function registerIpn(string $ipnUrl): string
    {
        $token    = $this->getToken();
        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/api/URLSetup/RegisterIPN", [
                'url'          => $ipnUrl,
                'ipn_notification_type' => 'POST',
            ]);

        Log::info('Pesapal IPN registration', ['response' => $response->json()]);

        if ($response->failed() || !$response->json('ipn_id')) {
            throw new \Exception('IPN registration failed: ' . $response->body());
        }

        return $response->json('ipn_id');
    }

    /**
     * Submit an order to Pesapal and get the hosted checkout redirect URL.
     *
     * @param  string $merchantReference  Our unique reference (e.g. PSP-ABCD1234)
     * @param  float  $amount
     * @param  string $ipnId             IPN ID from registerIpn()
     * @param  string $description
     * @param  array  $billing           ['email', 'phone', 'first_name', 'last_name']
     * @return array  ['redirect_url', 'order_tracking_id']
     */
    public function submitOrder(
        string $merchantReference,
        float  $amount,
        string $ipnId,
        string $description,
        array  $billing
    ): array {
        $token    = $this->getToken();
        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/api/Transactions/SubmitOrderRequest", [
                'id'                => $merchantReference,
                'currency'          => 'KES',
                'amount'            => round($amount, 2),
                'description'       => substr($description, 0, 100),
                'callback_url'      => route('pesapal.callback'),
                'notification_id'   => $ipnId,
                'billing_address'   => [
                    'email_address' => $billing['email']      ?? '',
                    'phone_number'  => $billing['phone']      ?? '',
                    'first_name'    => $billing['first_name'] ?? '',
                    'last_name'     => $billing['last_name']  ?? '',
                ],
            ]);

        Log::info('Pesapal SubmitOrder', [
            'ref'      => $merchantReference,
            'response' => $response->json(),
        ]);

        if ($response->failed() || !$response->json('redirect_url')) {
            throw new \Exception('Pesapal order submission failed: ' . $response->body());
        }

        return [
            'redirect_url'       => $response->json('redirect_url'),
            'order_tracking_id'  => $response->json('order_tracking_id'),
        ];
    }

    /**
     * Get transaction status by order tracking ID.
     */
    public function getTransactionStatus(string $orderTrackingId): array
    {
        $token    = $this->getToken();
        $response = Http::withToken($token)
            ->get("{$this->baseUrl}/api/Transactions/GetTransactionStatus", [
                'orderTrackingId' => $orderTrackingId,
            ]);

        if ($response->failed()) {
            throw new \Exception('Failed to get Pesapal transaction status.');
        }

        return $response->json();
    }
}
