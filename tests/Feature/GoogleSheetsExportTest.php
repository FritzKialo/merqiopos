<?php

namespace Tests\Feature;

use App\Jobs\SyncGoogleSheetEvent;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Sale;
use App\Services\GoogleSheetsSyncService;
use App\Services\WebhookService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Helpers\CreatesOrganization;
use Tests\TestCase;

class GoogleSheetsExportTest extends TestCase
{
    use CreatesOrganization;

    private function connectGoogleSheets($business): void
    {
        $business->update([
            'google_sheets_access_token'     => 'fake-access-token',
            'google_sheets_refresh_token'    => 'fake-refresh-token',
            'google_sheets_token_expires_at' => now()->addHour(),
            'google_sheets_spreadsheet_id'   => 'fake-spreadsheet-id',
            'google_sheets_connected_at'     => now(),
        ]);
    }

    /** @test */
    public function dispatch_does_nothing_when_the_business_has_not_connected_sheets(): void
    {
        [, , $business] = $this->scaffoldOrg();
        Bus::fake();

        GoogleSheetsSyncService::dispatch('sale.created', $business->id, ['sale_id' => 1]);

        Bus::assertNotDispatched(SyncGoogleSheetEvent::class);
    }

    /** @test */
    public function webhook_service_dispatch_also_triggers_a_sheets_sync_for_a_connected_business(): void
    {
        [, , $business] = $this->scaffoldOrg();
        $this->connectGoogleSheets($business);
        Bus::fake();

        WebhookService::dispatch('sale.created', $business->id, ['sale_id' => 1]);

        Bus::assertDispatched(SyncGoogleSheetEvent::class, fn ($job) => $job->businessId === $business->id && $job->event === 'sale.created');
    }

    /** @test */
    public function a_new_sale_appends_a_row_to_the_sales_tab(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();
        $this->connectGoogleSheets($business);

        $customer = Customer::create(['business_id' => $business->id, 'name' => 'Jane Doe']);
        $sale = Sale::create([
            'business_id'     => $business->id,
            'user_id'         => $owner->id,
            'customer_id'     => $customer->id,
            'invoice_number'  => 'INV-0001',
            'total_amount'    => 500,
            'payment_method'  => 'cash',
            'status'          => 'completed',
        ]);

        Http::fake(['sheets.googleapis.com/*' => Http::response(['spreadsheetId' => 'x'], 200)]);

        (new SyncGoogleSheetEvent($business->id, 'sale.created', ['sale_id' => $sale->id]))->handle(app(\App\Services\GoogleSheetsService::class));

        Http::assertSent(function ($request) use ($sale) {
            return str_contains($request->url(), 'Sales')
                && str_contains($request->url(), ':append')
                && $request['values'][0][1] === $sale->invoice_number
                && $request['values'][0][2] === 'Jane Doe';
        });
    }

    /** @test */
    public function a_new_expense_appends_a_row_to_the_expenses_tab(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();
        $this->connectGoogleSheets($business);

        $category = ExpenseCategory::create(['business_id' => $business->id, 'name' => 'Rent']);
        $expense = Expense::create([
            'business_id'          => $business->id,
            'user_id'              => $owner->id,
            'expense_category_id'  => $category->id,
            'title'                => 'October rent',
            'amount'               => 15000,
            'payment_method'       => 'bank_transfer',
            'expense_date'         => now(),
        ]);

        Http::fake(['sheets.googleapis.com/*' => Http::response(['spreadsheetId' => 'x'], 200)]);

        (new SyncGoogleSheetEvent($business->id, 'expense.created', ['expense_id' => $expense->id]))->handle(app(\App\Services\GoogleSheetsService::class));

        Http::assertSent(function ($request) use ($expense) {
            return str_contains($request->url(), 'Expenses')
                && str_contains($request->url(), ':append')
                && $request['values'][0][1] === 'Rent'
                && $request['values'][0][2] === $expense->title;
        });
    }

    /** @test */
    public function a_stock_adjustment_overwrites_the_whole_inventory_tab(): void
    {
        [, , $business] = $this->scaffoldOrg();
        $this->connectGoogleSheets($business);

        Product::create([
            'business_id' => $business->id, 'name' => 'Widget', 'sku' => 'W-1',
            'buying_price' => 100, 'selling_price' => 200, 'stock_qty' => 5, 'reorder_level' => 2,
        ]);

        Http::fake(['sheets.googleapis.com/*' => Http::response(['spreadsheetId' => 'x'], 200)]);

        (new SyncGoogleSheetEvent($business->id, 'stock.adjusted', []))->handle(app(\App\Services\GoogleSheetsService::class));

        Http::assertSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), ':clear'));
        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && str_contains($request->url(), 'Inventory')
                && ($request['values'][1][0] ?? null) === 'Widget';
        });
    }

    /** @test */
    public function connecting_backfills_existing_sales_and_expenses_not_just_future_ones(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();
        $this->connectGoogleSheets($business);

        $customer = Customer::create(['business_id' => $business->id, 'name' => 'Jane Doe']);
        Sale::create([
            'business_id' => $business->id, 'user_id' => $owner->id, 'customer_id' => $customer->id,
            'invoice_number' => 'INV-0001', 'total_amount' => 500, 'payment_method' => 'cash', 'status' => 'completed',
        ]);
        $category = ExpenseCategory::create(['business_id' => $business->id, 'name' => 'Rent']);
        Expense::create([
            'business_id' => $business->id, 'user_id' => $owner->id, 'expense_category_id' => $category->id,
            'title' => 'October rent', 'amount' => 15000, 'payment_method' => 'bank_transfer', 'expense_date' => now(),
        ]);

        Http::fake(['sheets.googleapis.com/*' => Http::response(['spreadsheetId' => 'x'], 200)]);

        (new SyncGoogleSheetEvent($business->id, 'sheets.initial_sync', []))->handle(app(\App\Services\GoogleSheetsService::class));

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT' && str_contains($request->url(), 'Sales')
                && ($request['values'][1][1] ?? null) === 'INV-0001';
        });
        Http::assertSent(function ($request) {
            return $request->method() === 'PUT' && str_contains($request->url(), 'Expenses')
                && ($request['values'][1][2] ?? null) === 'October rent';
        });
    }

    /** @test */
    public function disconnecting_clears_all_stored_tokens(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();
        $this->connectGoogleSheets($business);

        $response = $this->actingAs($owner)->post(route('settings.google-sheets.disconnect'));

        $response->assertRedirect(route('settings.google-sheets.index'));
        $business->refresh();
        $this->assertFalse($business->hasGoogleSheetsConnected());
        $this->assertNull($business->google_sheets_refresh_token);
    }
}
