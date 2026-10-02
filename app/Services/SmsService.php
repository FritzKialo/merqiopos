<?php

namespace App\Services;

use App\Models\Business;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected Business $business;
    protected ?string $provider;
    protected ?string $apiKey;
    protected ?string $username;
    protected ?string $senderId;

    public function __construct(Business $business)
    {
        $this->business  = $business;
        $this->provider  = $business->sms_provider  ?? null;
        $this->apiKey    = $business->sms_api_key   ?? null;
        $this->username  = $business->sms_username  ?? null;
        $this->senderId  = $business->sms_sender_id ?? null;
    }

    /**
     * Check whether SMS is properly configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->provider) && !empty($this->apiKey);
    }

    /**
     * Send an SMS message to the given phone number.
     */
    public function send(string $phone, string $message): bool
    {
        if (!$this->isConfigured()) {
            // Bumped from warning() — invisible in production, whose
            // LOG_LEVEL only records error and above (see ReceiptService.php).
            Log::error('SmsService: SMS not configured for business ' . $this->business->id);
            return false;
        }

        $normalizedPhone = $this->normalizePhone($phone);

        try {
            return match ($this->provider) {
                'africas_talking' => $this->sendViaAfricasTalking($normalizedPhone, $message),
                'twilio'          => $this->sendViaTwilio($normalizedPhone, $message),
                default           => false,
            };
        } catch (\Exception $e) {
            Log::error('SmsService: Failed to send SMS', [
                'business_id' => $this->business->id,
                'provider'    => $this->provider,
                'phone'       => $normalizedPhone,
                'error'       => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send SMS via Africa's Talking API.
     */
    protected function sendViaAfricasTalking(string $phone, string $message): bool
    {
        $params = [
            'username' => $this->username ?: 'sandbox',
            'to'       => $phone,
            'message'  => $message,
        ];

        if ($this->senderId) {
            $params['from'] = $this->senderId;
        }

        $response = Http::withHeaders([
            'apiKey'       => $this->apiKey,
            'Accept'       => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->asForm()->post('https://api.africastalking.com/version1/messaging', $params);

        if ($response->successful()) {
            $data = $response->json();
            $status = $data['SMSMessageData']['Recipients'][0]['status'] ?? null;
            if ($status === 'Success') {
                return true;
            }
            // Bumped from warning() — see the note on send() above.
            Log::error('SmsService: Africa\'s Talking non-success status', [
                'response' => $data,
            ]);
            return false;
        }

        Log::error('SmsService: Africa\'s Talking HTTP error', [
            'status'   => $response->status(),
            'body'     => $response->body(),
        ]);
        return false;
    }

    /**
     * Send SMS via Twilio REST API.
     */
    protected function sendViaTwilio(string $phone, string $message): bool
    {
        // For Twilio: username = Account SID, apiKey = Auth Token
        $accountSid = $this->username;
        $authToken  = $this->apiKey;
        $from       = $this->senderId;

        if (!$accountSid || !$from) {
            // Bumped from warning() — see the note on send() above.
            Log::error('SmsService: Twilio requires username (Account SID) and sender_id (From number).');
            return false;
        }

        $response = Http::withBasicAuth($accountSid, $authToken)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                'To'   => $phone,
                'From' => $from,
                'Body' => $message,
            ]);

        if ($response->successful()) {
            $data   = $response->json();
            $status = $data['status'] ?? null;
            if (in_array($status, ['queued', 'sent', 'delivered'])) {
                return true;
            }
            // Bumped from warning() — see the note on send() above.
            Log::error('SmsService: Twilio non-success status', ['response' => $data]);
            return false;
        }

        Log::error('SmsService: Twilio HTTP error', [
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);
        return false;
    }

    /**
     * Normalize a Kenyan phone number to international format (+254...).
     */
    private function normalizePhone(string $phone): string
    {
        // Strip all non-digit and non-plus characters
        $cleaned = preg_replace('/[^\d+]/', '', $phone);

        // Already in +254 format
        if (str_starts_with($cleaned, '+254')) {
            return $cleaned;
        }

        // 254XXXXXXXXX → +254XXXXXXXXX
        if (str_starts_with($cleaned, '254') && strlen($cleaned) === 12) {
            return '+' . $cleaned;
        }

        // 07XXXXXXXX or 01XXXXXXXX (10 digits starting with 0)
        if (str_starts_with($cleaned, '0') && strlen($cleaned) === 10) {
            return '+254' . substr($cleaned, 1);
        }

        // 7XXXXXXXXX or 1XXXXXXXXX (9 digits, no country code)
        if (strlen($cleaned) === 9 && in_array($cleaned[0], ['7', '1'])) {
            return '+254' . $cleaned;
        }

        // Return as-is if format is unrecognised
        return $cleaned;
    }

    /**
     * Factory method: create an SmsService for the given business.
     */
    public static function forBusiness(Business $business): static
    {
        return new static($business);
    }
}
