<?php

namespace App\Jobs;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Services\GoogleSheetsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs off the queue so a connected Google account never adds latency to
 * the actual sale/expense/stock action that triggered it. The payload only
 * ever carries an ID — the row is built from a fresh read of the record
 * here, not from whatever shape the webhook payload happened to have, so
 * this sheet's columns can evolve independently of the webhook contract.
 */
class SyncGoogleSheetEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public int $businessId,
        public string $event,
        public array $payload,
    ) {}

    public function handle(GoogleSheetsService $sheets): void
    {
        $business = Business::find($this->businessId);
        if (! $business || ! $business->hasGoogleSheetsConnected()) {
            return;
        }

        match ($this->event) {
            'sale.created'    => $this->syncSale($sheets, $business),
            'expense.created' => $this->syncExpense($sheets, $business),
            'stock.adjusted'  => $this->syncInventory($sheets, $business),
            'customer.created' => $this->syncCustomers($sheets, $business),
            'sheets.initial_sync' => $this->initialSync($sheets, $business),
            default => null,
        };

        $business->update(['google_sheets_last_synced_at' => now()]);
    }

    private function syncSale(GoogleSheetsService $sheets, Business $business): void
    {
        $sale = Sale::with('customer')->find($this->payload['sale_id'] ?? null);
        if (! $sale) {
            return;
        }

        $sheets->appendRow($business, 'Sales', [
            $sale->created_at->format('Y-m-d H:i'),
            $sale->invoice_number,
            $sale->customer?->name ?? 'Walk-in',
            (float) $sale->total_amount,
            $sale->payment_method,
            $sale->status,
        ]);
    }

    private function syncExpense(GoogleSheetsService $sheets, Business $business): void
    {
        $expense = Expense::with('category')->find($this->payload['expense_id'] ?? null);
        if (! $expense) {
            return;
        }

        $sheets->appendRow($business, 'Expenses', [
            $expense->created_at->format('Y-m-d H:i'),
            $expense->category?->name ?? '—',
            $expense->title,
            (float) $expense->amount,
            $expense->payment_method ?? '—',
        ]);
    }

    /**
     * Inventory is a snapshot, not a log — one stock change re-exports the
     * whole current table, which also self-heals anything a missed/failed
     * sync earlier left stale.
     */
    private function syncInventory(GoogleSheetsService $sheets, Business $business): void
    {
        $rows = Product::forBusiness($business->id)
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                $p->name,
                $p->sku,
                $p->stock_qty,
                $p->reorder_level,
                (float) $p->buying_price,
                (float) $p->selling_price,
            ])
            ->all();

        $sheets->overwriteTab($business, 'Inventory', ['Product', 'SKU', 'Stock', 'Reorder Level', 'Buying Price', 'Selling Price'], $rows);
    }

    /**
     * Runs once, right after connecting — backfills everything that
     * already exists, rather than leaving every tab empty until the next
     * new sale/expense happens and the owner wonders why "connecting"
     * didn't actually bring any of their existing data in. Capped at the
     * 1,000 most recent sales/expenses so a business with years of
     * history doesn't turn this into an unbounded API call.
     */
    private function initialSync(GoogleSheetsService $sheets, Business $business): void
    {
        $this->syncInventory($sheets, $business);
        $this->syncCustomers($sheets, $business);

        $salesRows = Sale::with('customer')
            ->where('business_id', $business->id)
            ->orderByDesc('created_at')
            ->limit(1000)
            ->get()
            ->sortBy('created_at')
            ->map(fn ($sale) => [
                $sale->created_at->format('Y-m-d H:i'),
                $sale->invoice_number,
                $sale->customer?->name ?? 'Walk-in',
                (float) $sale->total_amount,
                $sale->payment_method,
                $sale->status,
            ])
            ->values()
            ->all();

        $sheets->overwriteTab($business, 'Sales', ['Date', 'Invoice #', 'Customer', 'Total', 'Payment Method', 'Status'], $salesRows);

        $expenseRows = Expense::with('category')
            ->where('business_id', $business->id)
            ->orderByDesc('created_at')
            ->limit(1000)
            ->get()
            ->sortBy('created_at')
            ->map(fn ($expense) => [
                $expense->created_at->format('Y-m-d H:i'),
                $expense->category?->name ?? '—',
                $expense->title,
                (float) $expense->amount,
                $expense->payment_method ?? '—',
            ])
            ->values()
            ->all();

        $sheets->overwriteTab($business, 'Expenses', ['Date', 'Category', 'Title', 'Amount', 'Payment Method'], $expenseRows);
    }

    private function syncCustomers(GoogleSheetsService $sheets, Business $business): void
    {
        $rows = Customer::where('business_id', $business->id)
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                $c->name,
                $c->phone ?? '—',
                $c->email ?? '—',
                (float) ($c->credit_balance ?? 0),
                (float) ($c->credit_limit ?? 0),
            ])
            ->all();

        $sheets->overwriteTab($business, 'Customers', ['Name', 'Phone', 'Email', 'Balance', 'Credit Limit'], $rows);
    }
}
