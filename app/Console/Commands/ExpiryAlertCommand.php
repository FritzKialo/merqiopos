<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Services\AuditService;
use Illuminate\Console\Command;

class ExpiryAlertCommand extends Command {
    protected $signature   = 'inventory:expiry-alerts';
    protected $description = 'Notify about batches expiring soon or already expired';

    public function handle(): int {
        $products = Product::where('track_batches', true)->with('variants')->get();

        foreach ($products as $product) {
            $alertDays = $product->expiry_alert_days ?? 30;

            $batches = ProductBatch::where('product_id', $product->id)
                ->whereNotNull('expiry_date')
                ->where('expiry_date', '<=', now()->addDays($alertDays)->toDateString())
                ->where('quantity', '>', 0)
                ->get();

            foreach ($batches as $batch) {
                $days     = $batch->daysUntilExpiry();
                $label    = $days !== null && $days < 0 ? 'EXPIRED' : "expires in {$days} days";
                $this->warn("[{$product->name}] Batch {$batch->batch_number} {$label} (qty: {$batch->quantity})");

                AuditService::log('batch.expiry_alert', $batch, [
                    'product'      => $product->name,
                    'batch_number' => $batch->batch_number,
                    'expiry_date'  => $batch->expiry_date?->toDateString(),
                    'days'         => $days,
                    'quantity'     => $batch->quantity,
                ]);
            }
        }

        $this->info('Expiry alerts processed.');
        return self::SUCCESS;
    }
}
