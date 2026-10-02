<?php

namespace App\Console\Commands;

use App\Mail\LowStockAlert;
use App\Models\Business;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendLowStockAlerts extends Command
{
    protected $signature   = 'sme:low-stock-alerts';
    protected $description = 'Email business owners about products at or below reorder level';

    public function handle(): void
    {
        // Get all active businesses that have at least one low-stock product.
        // Was: ->with('owner') — Business::owner() isn't a real Eloquent
        // relation (it's a passthrough to organization?->owner()), so eager
        // loading it via with() threw a fatal "addEagerConstraints() on
        // null" error on every single run — this command has never
        // completed successfully. Load the branch owner the same
        // pivot-based way AutoRunPayroll/CheckNotifications already do
        // correctly elsewhere in this codebase.
            // Demo sandboxes (Support\DemoStore) look like real trial businesses — status='trial'
            // with genuinely overdue/low-stock data for realism — so they'd otherwise get
            // emailed here too, to their fake @demo.invalid owner address, which just bounces.
        $businesses = Business::where(function ($q) {
                $q->where('status', 'active')->orWhere('status', 'trial');
            })
            ->whereDoesntHave('organization', fn ($q) => $q->where('is_demo', true))
            ->get();

        $totalSent = 0;

        foreach ($businesses as $business) {
            $owner = $business->users()->wherePivot('role', 'owner')->first();
            if (! $owner?->email) continue;

            // Low-stock products not yet alerted today
            $lowStock = Product::forBusiness($business->id)
                ->active()
                ->lowStock()
                ->where(function ($q) {
                    $q->whereNull('low_stock_alert_sent_at')
                      ->orWhereDate('low_stock_alert_sent_at', '<', today());
                })
                ->get();

            if ($lowStock->isEmpty()) continue;

            Mail::to($owner->email)->queue(
                new LowStockAlert($business, $lowStock)
            );

            // Stamp sent_at so we don't re-alert same day
            Product::whereIn('id', $lowStock->pluck('id'))
                ->update(['low_stock_alert_sent_at' => now()]);

            $this->info(
                "Alert sent: {$business->name} ({$owner->email}) — "
                . $lowStock->count() . " products"
            );

            $totalSent++;
        }

        $this->info("Done. Alerts sent to {$totalSent} business(es).");
    }
}
