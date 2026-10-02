<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\RecurringInvoice;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shift;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessRecurringInvoices extends Command
{
    protected $signature   = 'sme:process-recurring-invoices';
    protected $description = 'Generate sales from recurring invoices that are due today';

    public function handle(): int
    {
        $due = RecurringInvoice::with(['items', 'customer'])
            ->due()
            ->get();

        if ($due->isEmpty()) {
            $this->info('No recurring invoices due.');
            return self::SUCCESS;
        }

        $generated = 0;
        $failed    = 0;

        foreach ($due as $recurring) {
            try {
                DB::transaction(function () use ($recurring) {
                    $total    = (float) $recurring->total;
                    $subtotal = (float) $recurring->subtotal;
                    $discount = (float) ($recurring->discount_amount ?? 0);
                    $tax      = (float) ($recurring->tax_amount ?? 0);

                    // sales.user_id is required (non-nullable). users.business_id
                    // is never actually populated by registration or the team-
                    // invite flow (those only set organization_id + the
                    // business_user pivot), so looking the owner up that way
                    // silently returned null for every real business and this
                    // whole insert would fail. Use the recurring invoice's own
                    // creator instead — set at creation time and always
                    // populated — matching the fallback the manual "Run Now"
                    // button already uses (RecurringInvoiceController::generateSale).
                    $ownerId = $recurring->user_id;

                    $sale = Sale::create([
                        'business_id'          => $recurring->business_id,
                        'shift_id'             => Shift::currentOpenId($recurring->business_id),
                        'customer_id'          => $recurring->customer_id,
                        'user_id'              => $ownerId,
                        'invoice_number'       => Sale::generateInvoiceNumber($recurring->business_id),
                        'subtotal'             => $subtotal,
                        'discount_amount'      => $discount,
                        'tax_amount'           => $tax,
                        // invoice.blade.php reads vat_amount, not tax_amount
                        // — see SaleService::createSale for the full context
                        // on why both need to be set (bug affecting every
                        // direct Sale::create() call site in the app).
                        'vat_amount'           => $tax,
                        'total_amount'         => $total,
                        'paid_amount'          => 0,
                        'balance_due'          => $total,
                        'payment_method'       => 'cash',
                        'payment_status'       => 'unpaid',
                        'sale_status'          => 'completed',
                        'notes'                => $recurring->notes,
                        // Missing here previously — the manual "Run Now" path
                        // (RecurringInvoiceController::generateSale) sets this,
                        // but the scheduled command didn't, so every
                        // auto-generated sale showed up unlinked from the
                        // recurring invoice that created it.
                        'recurring_invoice_id' => $recurring->id,
                    ]);

                    foreach ($recurring->items as $item) {
                        // Same gap as the manual "Run Now" path and
                        // QuoteController::convertToSale() — never touched
                        // Product stock at all for a completed Sale. Also
                        // buying_price was hardcoded 0 here (unlike the
                        // manual path, which already read it correctly) —
                        // fixed both together since they're the same loop.
                        $product = $item->product_id ? Product::find($item->product_id) : null;

                        if ($product) {
                            if ($product->stock_qty < $item->quantity) {
                                throw new \RuntimeException(
                                    "Insufficient stock for \"{$item->product_name}\": available {$product->stock_qty}, recurring invoice #{$recurring->id} requires {$item->quantity}."
                                );
                            }
                            $product->decrement('stock_qty', $item->quantity);
                            \App\Models\ProductBatch::consume((int) $product->id, null, (float) $item->quantity);
                        }

                        SaleItem::create([
                            'sale_id'      => $sale->id,
                            'product_id'   => $item->product_id,
                            'product_name' => $item->product_name,
                            'unit_price'   => $item->unit_price,
                            'buying_price' => $product?->buying_price ?? 0,
                            'quantity'     => $item->quantity,
                            'discount'     => 0,
                            'subtotal'     => $item->subtotal,
                        ]);
                    }

                    // Update customer balance if applicable
                    if ($recurring->customer_id && $total > 0) {
                        \App\Models\Customer::where('id', $recurring->customer_id)
                            ->increment('balance_owed', $total);
                    }

                    // Advance schedule
                    $recurring->update([
                        'last_run_date' => now()->toDateString(),
                        'next_run_date' => $recurring->nextRunAfter(now())->toDateString(),
                        'run_count'     => $recurring->run_count + 1,
                        'is_active'     => $recurring->end_date
                            ? now()->lt($recurring->end_date)
                            : true,
                    ]);
                });

                $generated++;
                $this->line("  <info>✓</info> Generated invoice for recurring #{$recurring->id} ({$recurring->title})");

            } catch (\Throwable $e) {
                $failed++;
                Log::error("Failed to process recurring invoice #{$recurring->id}: " . $e->getMessage());
                $this->line("  <error>✗</error> Failed #{$recurring->id}: " . $e->getMessage());
            }
        }

        $this->info("Done. Generated: {$generated}, Failed: {$failed}.");
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
