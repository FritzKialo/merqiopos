<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReorderCheck extends Command
{
    protected $signature   = 'inventory:reorder-check';
    protected $description = 'Auto-create draft Purchase Orders for products at or below reorder level';

    public function handle(): void
    {
        $businesses = Business::whereIn('status', ['active', 'trial'])->get();
        $totalPOs   = 0;

        foreach ($businesses as $business) {
            // purchase_orders.user_id is NOT NULL, but this command created
            // every draft PO with user_id = null — so the insert failed and the
            // command exited with an error every single day as soon as any
            // product was at or below its reorder level. Attribute the draft
            // to the business's owner.
            $ownerId = $business->users()->wherePivot('role', 'owner')->value('users.id')
                ?? $business->organization?->owner_user_id;

            // Find products needing reorder
            $products = Product::where('business_id', $business->id)
                ->where('reorder_level', '>', 0)
                ->whereColumn('stock_qty', '<=', 'reorder_level')
                ->get();

            if ($products->isEmpty()) continue;

            if (! $ownerId) {
                $this->warn("  Skipping {$business->name}: no owner account to attribute draft orders to.");
                continue;
            }

            foreach ($products as $product) {
                // Skip if there's already a pending/open PO containing this product
                $existingPO = PurchaseOrderItem::whereHas('purchaseOrder', function ($q) use ($business) {
                    $q->where('business_id', $business->id)
                      ->whereIn('status', ['draft', 'sent', 'partial']);
                })
                ->where('product_id', $product->id)
                ->exists();

                if ($existingPO) {
                    $this->line("  Skipping {$product->name} — open PO already exists.");
                    continue;
                }

                // Find supplier: most recent PO for this product, else any active supplier
                $supplier = $this->findSupplier($business->id, $product->id);

                try {
                DB::transaction(function () use ($business, $product, $supplier, $ownerId, &$totalPOs) {
                    $poNumber = PurchaseOrder::generatePoNumber($business->id);
                    $qty      = max(1, $product->reorder_level * 2);
                    $price    = (float) ($product->buying_price ?? 0);
                    $total    = round($qty * $price, 2);

                    $po = PurchaseOrder::create([
                        'business_id'  => $business->id,
                        'supplier_id'  => $supplier?->id,
                        'user_id'      => $ownerId,
                        'po_number'    => $poNumber,
                        'order_date'   => now()->toDateString(),
                        'expected_date'=> now()->addDays(7)->toDateString(),
                        'subtotal'     => $total,
                        'tax_amount'   => 0,
                        'total'        => $total,
                        'amount_paid'  => 0,
                        'status'       => 'draft',
                        'payment_status' => 'unpaid',
                        'notes'        => 'Auto-created by reorder check — ' . now()->format('d M Y')
                            . ($supplier ? '' : ' (No supplier found)'),
                    ]);

                    PurchaseOrderItem::create([
                        'purchase_order_id' => $po->id,
                        'product_id'        => $product->id,
                        'product_name'      => $product->name,
                        'quantity_ordered'  => $qty,
                        'quantity_received' => 0,
                        'unit_cost'         => $price,
                        'subtotal'          => $total,
                    ]);

                    AuditService::log('reorder_po_created', $po, [
                        'product_id'   => $product->id,
                        'product_name' => $product->name,
                        'qty'          => $qty,
                        'business_id'  => $business->id,
                    ]);

                    $totalPOs++;
                    $this->info("  Created PO {$po->po_number} for {$product->name} (qty: {$qty})");
                });
                } catch (\Throwable $e) {
                    // One product/business failing must not stop the rest, and
                    // production only records errors, so log it as one.
                    \Log::error('Reorder check failed for product ' . $product->id . ': ' . $e->getMessage());
                    $this->error("  Failed for {$product->name}: " . $e->getMessage());
                }
            }
        }

        $this->info("Reorder check complete. {$totalPOs} PO(s) created.");
    }

    private function findSupplier(int $businessId, int $productId): ?Supplier
    {
        // Most recent PO containing this product for this business
        $item = PurchaseOrderItem::whereHas('purchaseOrder', fn($q) => $q->where('business_id', $businessId))
            ->where('product_id', $productId)
            ->with('purchaseOrder.supplier')
            ->orderByDesc('created_at')
            ->first();

        if ($item?->purchaseOrder?->supplier) {
            return $item->purchaseOrder->supplier;
        }

        // Fall back to any active supplier for this business
        return Supplier::where('business_id', $businessId)->first();
    }
}
