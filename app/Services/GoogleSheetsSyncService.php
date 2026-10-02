<?php

namespace App\Services;

use App\Jobs\SyncGoogleSheetEvent;
use App\Models\Business;

/**
 * The Google Sheets equivalent of WebhookService::dispatch() — called from
 * the exact same event sites, so a business with Sheets connected gets a
 * live export without any extra wiring beyond this one call per event.
 */
class GoogleSheetsSyncService
{
    public const EVENTS = ['sale.created', 'expense.created', 'stock.adjusted', 'customer.created'];

    public static function dispatch(string $event, int $businessId, array $payload): void
    {
        if (! in_array($event, self::EVENTS, true)) {
            return;
        }

        $business = Business::find($businessId);
        if (! $business || ! $business->hasGoogleSheetsConnected()) {
            return;
        }

        SyncGoogleSheetEvent::dispatch($businessId, $event, $payload);
    }
}
