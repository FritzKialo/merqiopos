<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaService
{
    private string $baseUrl;
    private string $consumerKey;
    private string $consumerSecret;
    private string $shortcode;
    private string $passkey;
    private string $callbackUrl;
    private string $tillNumber;
    private string $environment;

    /**
     * Build the service with credentials.
     *
     * Pass a $credentials array (from a Business model) to use per-business
     * M-Pesa for sale payments. Omit it (or pass []) to use the platform-level
     * .env credentials — used for subscription billing.
     *
     * The callback URL always points back to this platform so we can record
     * payment results regardless of whose shortcode is used.
     */
    public function __construct(array $credentials = [])
    {
        $this->consumerKey    = trim($credentials['consumer_key']    ?? config('mpesa.consumer_key'));
        $this->consumerSecret = trim($credentials['consumer_secret'] ?? config('mpesa.consumer_secret'));
        $this->shortcode      = trim($credentials['shortcode']       ?? config('mpesa.shortcode'));
        $this->passkey        = trim($credentials['passkey']         ?? config('mpesa.passkey'));
        $this->tillNumber     = trim($credentials['till_number']     ?? config('mpesa.till_number', ''));
        $this->environment    = $credentials['environment']          ?? config('mpesa.environment', 'sandbox');

        // Base URL is derived from environment — not overridable per business
        $this->baseUrl = $this->environment === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';

        // Callback always points to this platform (central webhook handler)
        $this->callbackUrl = trim(config('mpesa.callback_url'));
    }

    /**
     * Base Guzzle options for every Daraja call. Self-heals a common shared-host
     * problem: some DNS resolvers can't follow Safaricom's sandbox CNAME (behind
     * Imperva). If the local resolver fails, we pin the IP looked up via public
     * DNS-over-HTTPS. Does nothing when DNS resolves normally (e.g. production).
     */
    private function httpOptions(int $timeout): array
    {
        $opts = [
            'verify'  => $this->environment === 'production',
            'timeout' => $timeout,
            // A generic Guzzle/PHP user-agent is one of the simplest signals
            // a bot-mitigation WAF (Safaricom's sandbox sits behind Imperva/
            // Incapsula — see isBotProtectionBlock() below) uses to flag
            // server-to-server API calls as automated traffic. This is a
            // free, safe thing to send regardless — it can't make a working
            // request fail, and it might be the difference for a borderline
            // block — but it's not a guaranteed fix: Incapsula's real
            // fingerprinting goes well past one header, and a persistent
            // block is ultimately an IP-reputation issue only Safaricom
            // support can lift by whitelisting the server's outbound IP.
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
            ],
        ];

        $host = parse_url($this->baseUrl, PHP_URL_HOST);
        if ($host && @gethostbyname($host) === $host) {
            if ($ip = $this->resolveViaDoh($host)) {
                $opts['curl'] = [CURLOPT_RESOLVE => ["{$host}:443:{$ip}"]];
            }
        }

        return $opts;
    }

    /**
     * Detect Safaricom's sandbox bot-mitigation (Imperva/Incapsula) blocking
     * a request outright — an HTML challenge/incident page instead of any
     * JSON Daraja response at all. Distinguishing this from a genuine
     * credentials/request error matters: this is an IP-reputation block on
     * *this server*, not something fixable by re-checking Daraja keys, and
     * telling the two apart used to require reading the raw HTML dump in
     * the log by hand (confirmed happening in production — see the
     * "M-Pesa token error" log entries containing an Incapsula incident ID
     * that got surfaced to a user as "check your Daraja credentials").
     */
    private function isBotProtectionBlock(string $body): bool
    {
        return str_contains($body, 'Incapsula')
            || str_contains($body, '_Incapsula_Resource')
            || str_contains($body, 'Request unsuccessful');
    }

    private function botProtectionMessage(): string
    {
        $env = $this->environment === 'production' ? 'live' : 'sandbox';
        return "Safaricom's {$env} API blocked this request as automated traffic (Incapsula), "
             . "not a credentials problem — this server's outbound IP needs to be whitelisted "
             . "by Safaricom Daraja support before {$env} M-Pesa requests from it will go through.";
    }

    /**
     * Runs a Daraja request and, if the bot-protection block from above hits,
     * retries it once after a short pause. Confirmed empirically (production
     * SSH: two curl requests back-to-back — one bare, one with a browser
     * User-Agent — both went straight through, no Incapsula page, right
     * after this exact block had been logged happening on separate earlier
     * occasions) that this is an intermittent block, not a standing ban on
     * the server's IP — so a request that gets challenged once has a real
     * chance of going through moments later without any human retrying it.
     */
    private function sendWithRetry(\Closure $makeRequest): \Illuminate\Http\Client\Response
    {
        $response = $makeRequest();

        if ($response->failed() && $this->isBotProtectionBlock($response->body())) {
            Log::warning('M-Pesa request blocked by bot protection (Incapsula) — retrying once');
            sleep(2);
            $response = $makeRequest();
        }

        return $response;
    }

    /**
     * Resolve a hostname's A record via public DNS-over-HTTPS (Google, then
     * Cloudflare) when the local resolver can't. Successful IPs are cached 1h.
     */
    private function resolveViaDoh(string $host): ?string
    {
        $cacheKey = 'mpesa_doh_' . $host;
        if ($ip = Cache::get($cacheKey)) {
            return $ip;
        }

        foreach (['https://dns.google/resolve', 'https://cloudflare-dns.com/dns-query'] as $endpoint) {
            try {
                $resp = Http::timeout(6)
                    ->withHeaders(['accept' => 'application/dns-json'])
                    ->get($endpoint, ['name' => $host, 'type' => 'A']);

                foreach ((array) $resp->json('Answer', []) as $ans) {
                    if ((int) ($ans['type'] ?? 0) === 1
                        && filter_var($ans['data'] ?? '', FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        Cache::put($cacheKey, $ans['data'], 3600);
                        return $ans['data'];
                    }
                }
            } catch (\Throwable) {
                // try next resolver
            }
        }

        return null;
    }

    /**
     * Get OAuth access token.
     * Cached per consumer-key pair so different businesses get independent tokens.
     */
    public function getAccessToken(): string
    {
        $cacheKey = 'mpesa_token_' . md5($this->consumerKey);

        return Cache::remember($cacheKey, 55 * 60, function () {
            $isSandbox = $this->environment !== 'production';

            $response = $this->sendWithRetry(fn () => Http::withBasicAuth(
                $this->consumerKey,
                $this->consumerSecret
            )->withOptions($this->httpOptions(30))
              ->get("{$this->baseUrl}/oauth/v1/generate", [
                'grant_type' => 'client_credentials',
            ]));

            if ($response->failed()) {
                $body = $response->body();
                Log::error('M-Pesa token error', ['body' => $body]);
                if ($this->isBotProtectionBlock($body)) {
                    throw new \Exception($this->botProtectionMessage());
                }
                throw new \Exception('Failed to get M-Pesa access token. Check your Daraja credentials.');
            }

            return $response->json('access_token');
        });
    }

    /**
     * Initiate an STK Push request.
     *
     * @param  string $phone       Customer phone (07xx / +254xx / 254xx)
     * @param  float  $amount      Amount in KES (rounded up to integer)
     * @param  string $accountRef  Account reference shown on customer's phone
     * @param  string $description Transaction description (max 13 chars)
     * @return array               Daraja response array
     */
    public function stkPush(
        string  $phone,
        float   $amount,
        string  $accountRef,
        string  $description,
        ?string $callbackUrl = null
    ): array {
        $token     = $this->getAccessToken();
        $timestamp = now('Africa/Nairobi')->format('YmdHis');
        $password  = base64_encode($this->shortcode . $this->passkey . $timestamp);
        $phone     = $this->formatPhone($phone);
        $amount    = (int) ceil($amount);

        $isBuyGoods = !empty($this->tillNumber);

        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'TransactionType'   => $isBuyGoods ? 'CustomerBuyGoodsOnline' : 'CustomerPayBillOnline',
            'Amount'            => $amount,
            'PartyA'            => $phone,
            'PartyB'            => $isBuyGoods ? $this->tillNumber : $this->shortcode,
            'PhoneNumber'       => $phone,
            // Every caller previously shared one hardcoded platform-wide
            // callback URL (config('mpesa.callback_url'), pointed at the
            // generic POS endpoint which looks up an MpesaTransaction row).
            // The public shop checkout never creates an MpesaTransaction —
            // it uses OnlineOrder — so its payments silently went to a
            // callback handler that could never recognize them. Callers
            // that need a different destination (like the shop) now pass
            // their own URL; everything else keeps the old platform-wide
            // default unchanged.
            'CallBackURL'       => $callbackUrl ?? $this->callbackUrl,
            'AccountReference'  => substr($accountRef, 0, 12),
            'TransactionDesc'   => substr($description, 0, 13),
        ];

        $isSandbox = $this->environment !== 'production';

        $response = $this->sendWithRetry(fn () => Http::withToken($token)
            ->withOptions($this->httpOptions(60))
            ->post(
                "{$this->baseUrl}/mpesa/stkpush/v1/processrequest",
                $payload
            ));

        Log::info('M-Pesa STK Push', [
            'shortcode' => $this->shortcode,
            'phone'     => $phone,
            'amount'    => $amount,
            'ref'       => $accountRef,
            'response'  => $response->json(),
        ]);

        if ($response->failed()) {
            $body = $response->body();
            Log::error('M-Pesa STK Push error', ['body' => $body]);
            if ($this->isBotProtectionBlock($body)) {
                throw new \Exception($this->botProtectionMessage());
            }
            throw new \Exception(
                $response->json('errorMessage') ?? 'STK Push request failed.'
            );
        }

        $data = $response->json();

        if (($data['ResponseCode'] ?? '') !== '0') {
            throw new \Exception(
                $data['ResponseDescription'] ?? 'STK Push rejected by Daraja.'
            );
        }

        return $data;
    }

    /**
     * Actively ask Safaricom for a checkout's real status (STK Push Query),
     * rather than passively waiting for their callback to arrive.
     *
     * Confirmed on this exact production account: a real STK push was sent,
     * the customer completed it on their phone, and Safaricom's sandbox
     * simply never delivered the callback at all — nothing in the logs,
     * nothing rejected, it just never sent one. That's a known reliability
     * gap in Daraja's sandbox specifically (well documented in the wider
     * developer community), not something fixable on our end for the
     * callback path itself. This query is the standard, Safaricom-provided
     * way to ask directly instead of only ever waiting on a push that may
     * never come — used as a fallback in MpesaController::status() when a
     * transaction has been PENDING for a while.
     *
     * @return array{resultCode:?int, resultDesc:?string, stillProcessing:bool}
     */
    public function queryStkStatus(string $checkoutRequestId): array
    {
        $token     = $this->getAccessToken();
        $timestamp = now('Africa/Nairobi')->format('YmdHis');
        $password  = base64_encode($this->shortcode . $this->passkey . $timestamp);

        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ];

        $response = $this->sendWithRetry(fn () => Http::withToken($token)
            ->withOptions($this->httpOptions(30))
            ->post("{$this->baseUrl}/mpesa/stkpushquery/v1/query", $payload));

        $data = $response->json() ?? [];

        Log::info('M-Pesa STK Push Query', [
            'checkout_id' => $checkoutRequestId,
            'response'    => $data,
        ]);

        // Safaricom returns an errorCode (no ResultCode at all) while the
        // transaction is still being processed — that's not a failure, it's
        // "ask again shortly". Anything else with no ResultCode is treated
        // the same way rather than guessed at.
        if (!array_key_exists('ResultCode', $data)) {
            return ['resultCode' => null, 'resultDesc' => $data['errorMessage'] ?? null, 'stillProcessing' => true];
        }

        // Daraja also answers a not-yet-finished payment WITH a ResultCode:
        // 4999 "The transaction is still under processing". That is not a
        // failure — the customer simply hasn't finished (or Safaricom hasn't
        // settled it). Treating it as one marked live payments FAILED while
        // the money was already leaving the customer's phone.
        if ((int) $data['ResultCode'] === 4999 || stripos((string) ($data['ResultDesc'] ?? ''), 'still under processing') !== false) {
            return ['resultCode' => 4999, 'resultDesc' => $data['ResultDesc'] ?? null, 'stillProcessing' => true];
        }

        return [
            'resultCode'      => (int) $data['ResultCode'],
            'resultDesc'      => $data['ResultDesc'] ?? null,
            'stillProcessing' => false,
        ];
    }

    /**
     * Send money to a phone (Daraja B2C, salary payment). The outcome arrives
     * later on $resultUrl. Deliberately NOT retried: a retry after a lost
     * response could pay the same salary twice.
     */
    public function b2cPayment(
        string $phone,
        float  $amount,
        string $initiator,
        string $securityCredential,
        string $remarks,
        string $occasion,
        string $resultUrl,
        string $timeoutUrl
    ): array {
        $token = $this->getAccessToken();

        $payload = [
            // The v3 B2C endpoint requires a unique id per request (it answered
            // "Invalid OriginatorConversationID" without one) and refuses a
            // repeat of an id it has already seen.
            'OriginatorConversationID' => (string) \Illuminate\Support\Str::uuid(),
            'InitiatorName'      => $initiator,
            'SecurityCredential' => $securityCredential,
            'CommandID'          => 'SalaryPayment',
            'Amount'             => (int) round($amount),
            'PartyA'             => $this->shortcode,
            'PartyB'             => $this->formatPhone($phone),
            'Remarks'            => mb_substr($remarks, 0, 100),
            'QueueTimeOutURL'    => $timeoutUrl,
            'ResultURL'          => $resultUrl,
            'Occasion'           => mb_substr($occasion, 0, 100),
        ];

        $response = Http::withToken($token)
            ->withOptions($this->httpOptions(30))
            ->post("{$this->baseUrl}/mpesa/b2c/v3/paymentrequest", $payload);

        $data = $response->json() ?? [];

        Log::info('M-Pesa B2C request', ['occasion' => $occasion, 'status' => $response->status(), 'response' => $data]);

        return [
            'accepted'       => (string) ($data['ResponseCode'] ?? '') === '0',
            'conversationId' => $data['ConversationID'] ?? null,
            'message'        => $data['ResponseDescription'] ?? ($data['errorMessage'] ?? ('HTTP ' . $response->status())),
        ];
    }

    /**
     * Ask Safaricom whether a receipt code is a real, completed payment
     * (Daraja "Transaction Status"). The answer is NOT in this response — it
     * arrives later, POSTed to $resultUrl. Returns the acknowledgement,
     * including the ConversationID used to match the result.
     *
     * $initiator / $securityCredential come from the business's Daraja
     * initiator: the credential is the initiator password already encrypted
     * with Safaricom's certificate (the Daraja portal generates it).
     */
    public function queryTransactionStatus(
        string $receipt,
        string $initiator,
        string $securityCredential,
        string $resultUrl,
        string $timeoutUrl,
        string $remarks = 'Verify payment'
    ): array {
        $token = $this->getAccessToken();

        $payload = [
            'Initiator'          => $initiator,
            'SecurityCredential' => $securityCredential,
            'CommandID'          => 'TransactionStatusQuery',
            'TransactionID'      => $receipt,
            'PartyA'             => !empty($this->tillNumber) ? $this->tillNumber : $this->shortcode,
            'IdentifierType'     => !empty($this->tillNumber) ? '2' : '4',
            'ResultURL'          => $resultUrl,
            'QueueTimeOutURL'    => $timeoutUrl,
            'Remarks'            => mb_substr($remarks, 0, 100),
            'Occasion'           => mb_substr($receipt, 0, 100),
        ];

        $response = $this->sendWithRetry(fn () => Http::withToken($token)
            ->withOptions($this->httpOptions(30))
            ->post("{$this->baseUrl}/mpesa/transactionstatus/v1/query", $payload));

        $data = $response->json() ?? [];

        Log::info('M-Pesa Transaction Status request', ['receipt' => $receipt, 'response' => $data]);

        return [
            'accepted'       => (string) ($data['ResponseCode'] ?? '') === '0',
            'conversationId' => $data['ConversationID'] ?? null,
            'message'        => $data['ResponseDescription'] ?? ($data['errorMessage'] ?? 'No response'),
        ];
    }

    /**
     * Register C2B confirmation + validation URLs with Safaricom.
     * Must be called once per shortcode (or whenever the URLs change).
     *
     * @param  string $confirmationUrl  Full URL Safaricom POSTs confirmed payments to
     * @param  string $validationUrl    Full URL Safaricom POSTs pre-confirmation checks to
     * @return array                    Daraja response
     */
    public function registerC2BUrls(string $confirmationUrl, string $validationUrl): array
    {
        $token    = $this->getAccessToken();
        $isSandbox = $this->environment !== 'production';

        $payload = [
            'ShortCode'       => $this->shortcode,
            'ResponseType'    => 'Completed',
            'ConfirmationURL' => $confirmationUrl,
            'ValidationURL'   => $validationUrl,
        ];

        $response = $this->sendWithRetry(fn () => Http::withToken($token)
            ->withOptions($this->httpOptions(30))
            ->post("{$this->baseUrl}/mpesa/c2b/v1/registerurl", $payload));

        Log::info('M-Pesa C2B URL registration', [
            'shortcode' => $this->shortcode,
            'response'  => $response->json(),
        ]);

        if ($response->failed()) {
            $body = $response->body();
            if ($this->isBotProtectionBlock($body)) {
                throw new \Exception($this->botProtectionMessage());
            }
            throw new \Exception('C2B URL registration failed: ' . $body);
        }

        $data = $response->json();

        if (($data['ResponseCode'] ?? '') !== '0' && !isset($data['OriginatorCoversationID'])) {
            // Sandbox returns slightly different success structure
            if (!str_contains(strtolower($data['ResponseDescription'] ?? ''), 'success')) {
                throw new \Exception($data['ResponseDescription'] ?? 'C2B registration rejected.');
            }
        }

        return $data;
    }

    /**
     * Generate a Dynamic M-Pesa QR code for a specific amount + reference.
     * The customer scans it in their M-Pesa app (Lipa na M-Pesa → Scan QR) —
     * no phone number needed from the merchant's side, unlike STK Push.
     * Settles as an ordinary Till/Paybill payment, so it arrives through the
     * same C2B confirmation webhook as any other customer-initiated payment
     * (MpesaController::c2bConfirm) — this method only produces the image.
     *
     * @return string  Base64-encoded QR code image (no data: prefix)
     */
    public function generateQrCode(float $amount, string $refNo, string $merchantName, int $size = 300): string
    {
        $token      = $this->getAccessToken();
        $isBuyGoods = !empty($this->tillNumber);

        $payload = [
            'MerchantName' => substr($merchantName, 0, 22),
            'RefNo'        => substr($refNo, 0, 12),
            'Amount'       => (int) ceil($amount),
            // BG = Buy Goods (Till), PB = Paybill — same distinction stkPush() makes.
            'TrxCode'      => $isBuyGoods ? 'BG' : 'PB',
            'CPI'          => $isBuyGoods ? $this->tillNumber : $this->shortcode,
            'Size'         => (string) $size,
        ];

        $response = $this->sendWithRetry(fn () => Http::withToken($token)
            ->withOptions($this->httpOptions(30))
            ->post("{$this->baseUrl}/mpesa/qrcode/v1/generate", $payload));

        Log::info('M-Pesa QR generate', [
            'shortcode' => $this->shortcode,
            'ref'       => $refNo,
            'amount'    => $payload['Amount'],
            'response'  => $response->json(),
        ]);

        if ($response->failed()) {
            $body = $response->body();
            Log::error('M-Pesa QR generation error', ['body' => $body]);
            if ($this->isBotProtectionBlock($body)) {
                throw new \Exception($this->botProtectionMessage());
            }
            throw new \Exception('Failed to generate M-Pesa QR code.');
        }

        $data = $response->json();

        if (empty($data['QRCode'])) {
            throw new \Exception($data['ResponseDescription'] ?? 'QR generation rejected by Daraja.');
        }

        return $data['QRCode'];
    }

    public function getShortcode(): string
    {
        return $this->shortcode;
    }

    /**
     * Normalize a Kenyan phone number to 254XXXXXXXXX format.
     */
    public function formatPhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '0')) {
            $phone = '254' . substr($phone, 1);
        } elseif (str_starts_with($phone, '+254')) {
            $phone = substr($phone, 1);
        } elseif (!str_starts_with($phone, '254')) {
            $phone = '254' . $phone;
        }

        return $phone;
    }
}
