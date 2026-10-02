<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected Business $business;
    protected ?string $provider;
    protected ?string $apiKey;
    protected ?string $username;
    protected ?string $senderId;

    // The real reason the last send() failed — isConfigured() can only ever
    // check that a provider + API key were typed into Settings; it can't
    // know whether the WhatsApp sender is actually approved on the
    // provider's own side (a separate onboarding step with Africa's
    // Talking/Twilio, outside this app entirely). Previously that failure
    // was only ever visible in the server log — completely inaccessible to
    // a shop owner on shared hosting with no log access — so "Send Test
    // Message" just said "Check logs" with nothing they could actually do
    // about it.
    protected ?string $lastError = null;

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function __construct(Business $business)
    {
        $this->business  = $business;
        $this->provider  = $business->sms_provider  ?? null;
        $this->apiKey    = $business->sms_api_key   ?? null;
        $this->username  = $business->sms_username  ?? null;
        $this->senderId  = $business->sms_sender_id ?? null;
    }

    public static function forBusiness(Business $business): static
    {
        return new static($business);
    }

    /**
     * Check if WhatsApp is enabled AND SMS credentials are configured.
     */
    public function isConfigured(): bool
    {
        return $this->business->whatsapp_enabled && !empty($this->provider) && !empty($this->apiKey);
    }

    /**
     * Send a WhatsApp message.
     */
    public function send(string $phone, string $message): bool
    {
        $this->lastError = null;

        if (!$this->isConfigured()) {
            $this->lastError = $this->business->whatsapp_enabled
                ? 'No SMS/WhatsApp provider or API key is set under Settings → SMS.'
                : 'WhatsApp notifications are turned off.';
            return false;
        }

        $normalizedPhone = $this->normalizePhone($phone);

        try {
            return match ($this->provider) {
                'africas_talking' => $this->sendViaAfricasTalking($normalizedPhone, $message),
                'twilio'          => $this->sendViaTwilio($normalizedPhone, $message),
                default           => (function () {
                    $this->lastError = "Unknown provider \"{$this->provider}\" — expected africas_talking or twilio.";
                    return false;
                })(),
            };
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            Log::error('WhatsAppService: send failed', [
                'business_id' => $this->business->id,
                'error'       => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function sendReceiptLink(Customer $customer, Sale $sale): bool
    {
        if (!$customer->phone) return false;

        $message = "Hi {$customer->name}, thank you for your purchase at {$this->business->name}!\n"
            . "Receipt #{$sale->invoice_number} — KSh " . number_format($sale->total_amount, 0) . ".\n"
            . "Thank you for shopping with us!";

        return $this->send($customer->phone, $message);
    }

    /**
     * Send a PAID receipt to a specific phone (e.g. the M-Pesa number that paid),
     * including a link to the printable receipt.
     */
    public function sendReceipt(string $phone, Sale $sale, string $link): bool
    {
        $name    = $sale->customer?->name ?? 'there';
        $message = "Hi {$name}, thank you for shopping at {$this->business->name}!\n"
            . "Receipt {$sale->invoice_number} — PAID KSh " . number_format($sale->paid_amount, 0) . ".\n"
            . "View / print your receipt: {$link}";

        return $this->send($phone, $message);
    }

    public function sendInvoiceReminder(Customer $customer, Invoice $invoice): bool
    {
        if (!$customer->phone) return false;

        $message = "Hi {$customer->name}, a friendly reminder from {$this->business->name}.\n"
            . "Invoice #{$invoice->invoice_number} for KSh " . number_format($invoice->total_amount, 0)
            . " is due on " . $invoice->due_date?->format('d M Y') . ".\n"
            . "Please arrange payment. Thank you!";

        return $this->send($customer->phone, $message);
    }

    public function sendLowStockAlert(User $owner, Product $product): bool
    {
        if (!$owner->phone ?? null) return false;

        $message = "Low stock alert from {$this->business->name}:\n"
            . "Product: {$product->name}\n"
            . "Current stock: {$product->stock_qty} {$product->unit}\n"
            . "Reorder level: {$product->reorder_level}";

        return $this->send($owner->phone, $message);
    }

    // ── Private helpers ───────────────────────────────────────────────────

    protected function sendViaAfricasTalking(string $phone, string $message): bool
    {
        $response = Http::withHeaders([
            'apiKey'       => $this->apiKey,
            'Accept'       => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->asForm()->post('https://api.africastalking.com/version1/messaging/whatsapp', [
            'username' => $this->username ?: 'sandbox',
            'to'       => $phone,
            'message'  => $message,
        ]);

        if ($response->successful()) {
            return true;
        }

        // Field name isn't 100% guaranteed across every Africa's Talking
        // response shape, so this tries the common ones and falls back to
        // the raw body (trimmed) rather than showing nothing — something
        // a shop owner can read is better than a guess that might be wrong
        // silently swallowing the real reason.
        $body = $response->json() ?? [];
        $this->lastError = $body['errorMessage']
            ?? $body['error']
            ?? (trim($response->body()) !== '' ? \Illuminate\Support\Str::limit($response->body(), 200) : null)
            ?? "Africa's Talking rejected the request (HTTP {$response->status()}).";

        // Bumped from warning() — invisible in production, whose LOG_LEVEL
        // only records error and above (see ReceiptService.php). $lastError
        // above is shown to the shop owner in the moment, but this log is
        // what would let anyone spot a pattern (e.g. expired credentials)
        // across many silent failures over time.
        Log::error('WhatsAppService: Africa\'s Talking error', [
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);
        return false;
    }

    protected function sendViaTwilio(string $phone, string $message): bool
    {
        $accountSid = $this->username;
        $authToken  = $this->apiKey;
        $from       = 'whatsapp:' . ($this->senderId ?? '');

        $response = Http::withBasicAuth($accountSid, $authToken)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                'To'   => 'whatsapp:' . $phone,
                'From' => $from,
                'Body' => $message,
            ]);

        $data = $response->json() ?? [];

        if ($response->successful() && in_array($data['status'] ?? '', ['queued', 'sent', 'delivered'])) {
            return true;
        }

        // Twilio's error format is consistent: {code, message, more_info,
        // status} — 'message' is reliably human-readable ("The 'To' number
        // ... is not currently reachable via WhatsApp", "Channel ... is not
        // approved", etc.), unlike Africa's Talking's less standardized
        // shape above. A request can also come back HTTP-successful but
        // with a status Twilio doesn't consider sent (e.g. 'failed',
        // 'undelivered') — "rejected" would be factually wrong there since
        // the HTTP call itself succeeded, so word it around whichever
        // actually happened.
        $this->lastError = $data['message']
            ?? ($response->successful()
                ? "Twilio accepted the request but reported status \"" . ($data['status'] ?? 'unknown') . "\"."
                : "Twilio rejected the request (HTTP {$response->status()}).");

        // Bumped from warning() — see the note on sendViaAfricasTalking() above.
        Log::error('WhatsAppService: Twilio error', [
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);
        return false;
    }

    private function normalizePhone(string $phone): string
    {
        $cleaned = preg_replace('/[^\d+]/', '', $phone);
        if (str_starts_with($cleaned, '+254')) return $cleaned;
        if (str_starts_with($cleaned, '254') && strlen($cleaned) === 12) return '+' . $cleaned;
        if (str_starts_with($cleaned, '0') && strlen($cleaned) === 10) return '+254' . substr($cleaned, 1);
        if (strlen($cleaned) === 9 && in_array($cleaned[0], ['7', '1'])) return '+254' . $cleaned;
        return $cleaned;
    }
}
