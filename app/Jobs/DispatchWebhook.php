<?php

namespace App\Jobs;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class DispatchWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Webhook $webhook,
        public string $event,
        public array $data,
    ) {}

    public function handle(): void
    {
        $body = json_encode([
            'event'       => $this->event,
            'business_id' => $this->webhook->business_id,
            'timestamp'   => now()->toIso8601String(),
            'data'        => $this->data,
        ]);

        $headers = ['Content-Type' => 'application/json'];

        if ($this->webhook->secret) {
            $sig = WebhookService::sign($body, $this->webhook->secret);
            $headers['X-Webhook-Signature'] = "sha256={$sig}";
        }

        $delivery = new WebhookDelivery([
            'webhook_id' => $this->webhook->id,
            'event'      => $this->event,
            'payload'    => json_decode($body, true),
            'failed'     => false,
        ]);

        try {
            $response = Http::timeout(10)
                ->withHeaders($headers)
                ->post($this->webhook->url, json_decode($body, true));

            $delivery->response_status = $response->status();
            $delivery->response_body   = substr($response->body(), 0, 2000);
            $delivery->delivered_at    = now();
            $delivery->failed          = !$response->successful();

        } catch (\Throwable $e) {
            $delivery->failed        = true;
            $delivery->response_body = $e->getMessage();
        }

        $delivery->save();

        if ($delivery->failed) {
            $this->webhook->increment('failure_count');
            if ($this->webhook->failure_count >= 5) {
                $this->webhook->update(['is_active' => false]);
            }
        } else {
            $this->webhook->update([
                'last_triggered_at' => now(),
                'failure_count'     => 0,
            ]);
        }
    }
}
