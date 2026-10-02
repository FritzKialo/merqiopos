<?php

namespace App\Services;

use App\Jobs\DispatchWebhook;
use App\Models\Webhook;

class WebhookService
{
    public const EVENTS = [
        'sale.created',
        'sale.refunded',
        'invoice.created',
        'invoice.paid',
        'invoice.sent',
        'customer.created',
        'product.low_stock',
        'payroll.paid',
        'stock.adjusted',
        'expense.created',
    ];

    public static function dispatch(string $event, int $businessId, array $payload): void
    {
        $webhooks = Webhook::forBusiness($businessId)
            ->active()
            ->get()
            ->filter(fn ($w) => $w->subscribesTo($event));

        foreach ($webhooks as $webhook) {
            DispatchWebhook::dispatch($webhook, $event, $payload);
        }

        // Same event stream feeds the Google Sheets live export — one
        // call site for both, so a new event only needs wiring here once.
        \App\Services\GoogleSheetsSyncService::dispatch($event, $businessId, $payload);
    }

    public static function sign(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }
}
