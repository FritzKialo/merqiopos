<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\StockReceiveItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillBuyingPrices extends Command
{
    /**
     * One-off reconciliation for the weighted-average-cost fix in
     * StockReceiveController::store(). Before that fix, buying_price was
     * frozen at whatever it was set to when the product was added/last
     * manually edited, regardless of what later stock receives actually
     * cost. This recomputes buying_price as the quantity-weighted average
     * unit_cost across ALL of a product's historical stock_receive_items.
     *
     * Note: this is an approximation, not a true reconstruction — it
     * doesn't know which specific units have since sold out (no FIFO/batch
     * tracking exists), so it treats every unit ever received as still
     * contributing to the average. That's the best available signal given
     * the data actually stored, and matches the going-forward formula's
     * own assumption (blend by quantity, not by what's left in stock).
     *
     * Products with no stock_receive_items at all (added manually, never
     * received against) are left untouched — there's no receive history
     * to reconcile against, so their existing buying_price stands.
     */
    protected $signature = 'products:backfill-buying-price
        {--dry-run : Show what would change without writing anything}';

    protected $description = 'Recompute each product\'s buying_price as the weighted average unit_cost across its stock receive history';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $averages = StockReceiveItem::select('product_id')
            ->selectRaw('SUM(quantity_received) as total_qty')
            ->selectRaw('SUM(quantity_received * unit_cost) as total_cost')
            ->groupBy('product_id')
            ->havingRaw('SUM(quantity_received) > 0')
            ->get()
            ->keyBy('product_id');

        if ($averages->isEmpty()) {
            $this->info('No stock_receive_items found — nothing to backfill.');
            return self::SUCCESS;
        }

        $productIds = $averages->keys()->all();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $rows = [];
        $changed = 0;

        foreach ($averages as $productId => $agg) {
            $product = $products->get($productId);
            if (! $product) {
                // Orphaned receive history (product since deleted) — skip.
                continue;
            }

            $newPrice = round(((float) $agg->total_cost) / ((float) $agg->total_qty), 2);
            $oldPrice = (float) $product->buying_price;

            if (abs($newPrice - $oldPrice) < 0.01) {
                continue; // already correct, don't touch it
            }

            $rows[] = [
                $product->id,
                $product->business_id,
                $product->name,
                number_format($oldPrice, 2),
                number_format($newPrice, 2),
            ];

            if (! $dryRun) {
                $product->update(['buying_price' => $newPrice]);
            }

            $changed++;
        }

        if (empty($rows)) {
            $this->info('Every product\'s buying_price already matches its weighted-average receive cost — nothing to change.');
            return self::SUCCESS;
        }

        $this->table(
            ['Product ID', 'Business ID', 'Product', 'Old buying_price', 'New buying_price'],
            $rows
        );

        $this->info(($dryRun ? '[DRY RUN] Would update ' : 'Updated ') . "{$changed} product(s).");

        return self::SUCCESS;
    }
}
