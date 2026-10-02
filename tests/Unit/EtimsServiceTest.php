<?php

namespace Tests\Unit;

use App\Jobs\SubmitEtimsDocument;
use App\Models\Business;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\EtimsRefund;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Services\EtimsService;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Helpers\CreatesOrganization;
use Tests\TestCase;

/**
 * KRA eTIMS (OSCU) integration, exercised entirely against Http::fake() — the
 * sandbox itself needs a KRA-issued device registration we don't have yet (see
 * EtimsService's own class docblock: "NOT yet verified against the KRA sandbox").
 * This is the next best thing: pins down the request-building, response-handling,
 * and the reversal (refund/credit-note) logic against the documented API shape,
 * so it's ready to point at the real sandbox the moment credentials exist.
 */
class EtimsServiceTest extends TestCase
{
    use CreatesOrganization;

    private function configuredBusiness(array $overrides = []): Business
    {
        [, , $business] = $this->scaffoldOrg();
        $business->forceFill(array_merge([
            'etims_enabled'              => true,
            'etims_device_serial'        => 'DVC12345',
            'etims_cmc_key'              => 'test-cmc-key',
            'kra_pin'                    => 'P051234567X',
            'etims_bhf_id'                => '00',
            'etims_default_item_cls_cd'  => '5059690800',
            'vat_registered'              => true,
            'vat_rate'                    => 16,
        ], $overrides))->save();

        return $business->fresh();
    }

    private function fakeSuccess(array $extraData = []): array
    {
        return ['resultCd' => '000', 'resultMsg' => 'Success', 'data' => array_merge(['curRcptNo' => 555], $extraData)];
    }

    // ── isConfigured() ───────────────────────────────────────────────────────

    /** @test */
    public function is_configured_requires_the_device_pin_and_communication_key(): void
    {
        $business = $this->configuredBusiness();
        $this->assertTrue(EtimsService::forBusiness($business)->isConfigured());

        $business->etims_enabled = false;
        $this->assertFalse(EtimsService::forBusiness($business)->isConfigured());
    }

    /** @test */
    public function a_business_missing_the_communication_key_is_not_configured(): void
    {
        [, , $business] = $this->scaffoldOrg();
        $business->forceFill(['etims_enabled' => true, 'etims_device_serial' => 'X', 'kra_pin' => 'P1'])->save();

        $this->assertFalse(EtimsService::forBusiness($business->fresh())->isConfigured());
    }

    // ── Device activation ────────────────────────────────────────────────────

    /** @test */
    public function initialize_refuses_without_a_pin_and_device_serial(): void
    {
        [, , $business] = $this->scaffoldOrg();

        $result = EtimsService::forBusiness($business)->initialize();

        $this->assertEquals('failed', $result['status']);
        $this->assertStringContainsString('KRA PIN', $result['message']);
    }

    /** @test */
    public function initialize_stores_the_communication_key_kra_returns(): void
    {
        [, , $business] = $this->scaffoldOrg();
        $business->forceFill(['kra_pin' => 'P051234567X', 'etims_device_serial' => 'DVC1'])->save();

        Http::fake(['*/selectInitOsdcInfo' => Http::response($this->fakeSuccess([
            'info' => ['cmcKey' => 'real-cmc-key-from-kra', 'sdcId' => 'SDC1', 'mrcNo' => 'MRC1', 'taxprNm' => 'Nainterr Wholesale Hub'],
        ]), 200)]);

        $result = EtimsService::forBusiness($business)->initialize();

        $this->assertEquals('ok', $result['status']);
        $this->assertStringContainsString('Nainterr Wholesale Hub', $result['message']);
        $business->refresh();
        $this->assertEquals('real-cmc-key-from-kra', $business->etims_cmc_key); // decrypted transparently by the accessor
        $this->assertNotEquals('real-cmc-key-from-kra', $business->getRawOriginal('etims_cmc_key')); // stored encrypted
        $this->assertEquals('SDC1', $business->etims_sdc_id);
    }

    /** @test */
    public function initialize_fails_cleanly_when_kra_accepts_but_returns_no_key(): void
    {
        [, , $business] = $this->scaffoldOrg();
        $business->forceFill(['kra_pin' => 'P1', 'etims_device_serial' => 'DVC1'])->save();

        Http::fake(['*/selectInitOsdcInfo' => Http::response(['resultCd' => '000', 'resultMsg' => 'ok', 'data' => ['info' => []]], 200)]);

        $result = EtimsService::forBusiness($business)->initialize();

        $this->assertEquals('failed', $result['status']);
        $this->assertStringContainsString('no communication key', $result['message']);
    }

    /** @test */
    public function initialize_reports_kras_own_rejection_message(): void
    {
        [, , $business] = $this->scaffoldOrg();
        $business->forceFill(['kra_pin' => 'P1', 'etims_device_serial' => 'DVC1'])->save();

        Http::fake(['*/selectInitOsdcInfo' => Http::response(['resultCd' => '901', 'resultMsg' => 'Invalid device serial number'], 200)]);

        $result = EtimsService::forBusiness($business)->initialize();

        $this->assertEquals('failed', $result['status']);
        $this->assertEquals('Invalid device serial number', $result['message']);
    }

    // ── Item registration (via submitSale) ───────────────────────────────────

    private function makeSaleWithProduct(Business $business, array $productOverrides = []): Sale
    {
        $unique = \Illuminate\Support\Str::random(6);
        $product = Product::create(array_merge([
            'business_id' => $business->id, 'name' => 'Sugar 1kg', 'sku' => 'SUGAR1-' . $unique,
            'buying_price' => 100, 'selling_price' => 150, 'stock_qty' => 50,
        ], $productOverrides));

        $sale = Sale::create([
            'business_id' => $business->id, 'user_id' => $business->users()->first()->id,
            'invoice_number' => 'INV-TEST-' . $unique, 'subtotal' => 300, 'tax_amount' => 41.38, 'vat_amount' => 41.38,
            'total_amount' => 300, 'paid_amount' => 300, 'balance_due' => 0,
            'payment_method' => 'cash', 'payment_status' => 'paid', 'sale_status' => 'completed',
        ]);
        SaleItem::create(['sale_id' => $sale->id, 'product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 2, 'unit_price' => 150, 'subtotal' => 300]);

        return $sale->fresh();
    }

    /** @test */
    public function submitting_a_sale_fails_cleanly_when_a_product_has_no_item_classification_and_no_default(): void
    {
        $business = $this->configuredBusiness(['etims_default_item_cls_cd' => null]);
        $sale = $this->makeSaleWithProduct($business);

        $result = EtimsService::forBusiness($business)->submitSale($sale);

        $this->assertEquals('failed', $result['status']);
        $this->assertStringContainsString('item classification code', $result['message']);
        $this->assertEquals('failed', $sale->fresh()->etims_status);
    }

    /** @test */
    public function a_new_product_is_registered_with_kra_before_its_first_sale_is_submitted(): void
    {
        $business = $this->configuredBusiness();
        $sale = $this->makeSaleWithProduct($business);

        Http::fake([
            '*/saveItem'          => Http::response($this->fakeSuccess(), 200),
            '*/saveTrnsSalesOsdc' => Http::response($this->fakeSuccess(['curRcptNo' => 777]), 200),
            '*/insertStockIO'     => Http::response($this->fakeSuccess(), 200),
            '*/saveStockMaster'   => Http::response($this->fakeSuccess(), 200),
        ]);

        $result = EtimsService::forBusiness($business)->submitSale($sale);

        $this->assertEquals('submitted', $result['status']);
        $this->assertEquals('777', $result['cuin']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'saveItem') && $r['itemNm'] === 'Sugar 1kg');

        $product = Product::where('business_id', $business->id)->first();
        $this->assertNotNull($product->etims_item_cd, 'the product should now carry its KRA item code');
    }

    /** @test */
    public function a_second_sale_of_an_already_registered_product_does_not_register_it_again(): void
    {
        $business = $this->configuredBusiness();
        $product = Product::create([
            'business_id' => $business->id, 'name' => 'Sugar 1kg', 'sku' => 'SUGAR1',
            'buying_price' => 100, 'selling_price' => 150, 'stock_qty' => 50,
            'etims_item_cls_cd' => '5059690800',
        ]);
        // etims_item_cd is deliberately not mass-assignable (see EtimsService::ensureProductItem —
        // it's only ever written internally via forceFill), so it has to be set the same way here.
        $product->forceFill(['etims_item_cd' => 'KE2NTXU0000001', 'etims_registered_at' => now()])->save();
        $sale = Sale::create([
            'business_id' => $business->id, 'user_id' => $business->users()->first()->id,
            'invoice_number' => 'INV-TEST-2', 'subtotal' => 150, 'total_amount' => 150, 'paid_amount' => 150,
            'payment_method' => 'cash', 'payment_status' => 'paid', 'sale_status' => 'completed',
        ]);
        SaleItem::create(['sale_id' => $sale->id, 'product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 1, 'unit_price' => 150, 'subtotal' => 150]);

        Http::fake([
            '*/saveItem'          => Http::response($this->fakeSuccess(), 200),
            '*/saveTrnsSalesOsdc' => Http::response($this->fakeSuccess(), 200),
            '*/insertStockIO'     => Http::response($this->fakeSuccess(), 200),
            '*/saveStockMaster'   => Http::response($this->fakeSuccess(), 200),
        ]);

        EtimsService::forBusiness($business)->submitSale($sale->fresh());

        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'saveItem'));
    }

    /** @test */
    public function a_vat_registered_business_reports_standard_rated_tax_type_b_with_correct_vat_split(): void
    {
        $business = $this->configuredBusiness(['vat_registered' => true, 'vat_rate' => 16]);
        $sale = $this->makeSaleWithProduct($business);

        Http::fake([
            '*/saveItem'          => Http::response($this->fakeSuccess(), 200),
            '*/saveTrnsSalesOsdc' => Http::response($this->fakeSuccess(), 200),
            '*/insertStockIO'     => Http::response($this->fakeSuccess(), 200),
            '*/saveStockMaster'   => Http::response($this->fakeSuccess(), 200),
        ]);

        EtimsService::forBusiness($business)->submitSale($sale);

        Http::assertSent(function ($r) {
            if (! str_contains($r->url(), 'saveTrnsSalesOsdc')) return true;
            return $r['itemList'][0]['taxTyCd'] === 'B' && $r['taxRtB'] == 16 && $r['totAmt'] == 300;
        });
    }

    /** @test */
    public function a_non_vat_registered_business_reports_everything_as_tax_type_d(): void
    {
        $business = $this->configuredBusiness(['vat_registered' => false]);
        $sale = $this->makeSaleWithProduct($business);

        Http::fake([
            '*/saveItem'          => Http::response($this->fakeSuccess(), 200),
            '*/saveTrnsSalesOsdc' => Http::response($this->fakeSuccess(), 200),
            '*/insertStockIO'     => Http::response($this->fakeSuccess(), 200),
            '*/saveStockMaster'   => Http::response($this->fakeSuccess(), 200),
        ]);

        EtimsService::forBusiness($business)->submitSale($sale);

        Http::assertSent(function ($r) {
            if (! str_contains($r->url(), 'saveTrnsSalesOsdc')) return true;
            return $r['itemList'][0]['taxTyCd'] === 'D';
        });
    }

    /** @test */
    public function kra_rejecting_a_sale_marks_it_failed_with_the_response_stored(): void
    {
        $business = $this->configuredBusiness();
        $sale = $this->makeSaleWithProduct($business);

        Http::fake([
            '*/saveItem'          => Http::response($this->fakeSuccess(), 200),
            '*/saveTrnsSalesOsdc' => Http::response(['resultCd' => '902', 'resultMsg' => 'Duplicate invoice number'], 200),
        ]);

        $result = EtimsService::forBusiness($business)->submitSale($sale);

        $this->assertEquals('failed', $result['status']);
        $this->assertEquals('Duplicate invoice number', $result['message']);
        $sale->refresh();
        $this->assertEquals('failed', $sale->etims_status);
        $this->assertEquals('902', $sale->etims_response['resultCd']);
    }

    /** @test */
    public function a_failed_stock_report_does_not_undo_an_already_accepted_receipt(): void
    {
        $business = $this->configuredBusiness();
        $sale = $this->makeSaleWithProduct($business);

        Http::fake([
            '*/saveItem'          => Http::response($this->fakeSuccess(), 200),
            '*/saveTrnsSalesOsdc' => Http::response($this->fakeSuccess(), 200),
            '*/insertStockIO'     => Http::response(['resultCd' => '999', 'resultMsg' => 'Stock service unavailable'], 500),
        ]);

        $result = EtimsService::forBusiness($business)->submitSale($sale);

        $this->assertEquals('submitted', $result['status'], 'the receipt was already accepted — a later stock-report failure must not roll it back');
        $this->assertEquals('submitted', $sale->fresh()->etims_status);
    }

    /** @test */
    public function each_sale_gets_a_sequential_etims_invoice_number(): void
    {
        $business = $this->configuredBusiness();
        $sale1 = $this->makeSaleWithProduct($business);
        $sale2 = $this->makeSaleWithProduct($business);

        Http::fake([
            '*/saveItem'          => Http::response($this->fakeSuccess(), 200),
            '*/saveTrnsSalesOsdc' => Http::response($this->fakeSuccess(), 200),
            '*/insertStockIO'     => Http::response($this->fakeSuccess(), 200),
            '*/saveStockMaster'   => Http::response($this->fakeSuccess(), 200),
        ]);

        $svc = EtimsService::forBusiness($business);
        $svc->submitSale($sale1);
        $svc->submitSale($sale2);

        $this->assertEquals(1, $sale1->fresh()->etims_invc_no);
        $this->assertEquals(2, $sale2->fresh()->etims_invc_no);
    }

    // ── Invoices ─────────────────────────────────────────────────────────────

    /** @test */
    public function submitting_an_invoice_succeeds_and_stores_the_kra_receipt_number(): void
    {
        $business = $this->configuredBusiness();
        $product = Product::create([
            'business_id' => $business->id, 'name' => 'Rice 2kg', 'sku' => 'RICE1',
            'buying_price' => 200, 'selling_price' => 260, 'stock_qty' => 30,
        ]);
        $invoice = Invoice::create([
            'business_id' => $business->id, 'user_id' => $business->users()->first()->id,
            'invoice_number' => 'INV-0001', 'issue_date' => now(), 'due_date' => now()->addDays(14),
            'subtotal' => 260, 'vat_amount' => 35.86, 'total' => 260, 'amount_paid' => 0, 'balance_due' => 260, 'status' => 'sent',
        ]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'product_id' => $product->id, 'description' => 'Rice 2kg', 'quantity' => 1, 'unit_price' => 260, 'vat_amount' => 35.86, 'subtotal' => 224.14]);

        Http::fake([
            '*/saveItem'          => Http::response($this->fakeSuccess(), 200),
            '*/saveTrnsSalesOsdc' => Http::response($this->fakeSuccess(['curRcptNo' => 888]), 200),
            '*/insertStockIO'     => Http::response($this->fakeSuccess(), 200),
            '*/saveStockMaster'   => Http::response($this->fakeSuccess(), 200),
        ]);

        $result = EtimsService::forBusiness($business)->submitInvoice($invoice->fresh());

        $this->assertEquals('submitted', $result['status']);
        $this->assertEquals('888', $invoice->fresh()->etims_cuin);
    }

    // ── Refunds / reversals ──────────────────────────────────────────────────

    private function submittedSale(Business $business): Sale
    {
        $sale = $this->makeSaleWithProduct($business);
        $sale->forceFill(['etims_status' => 'submitted', 'etims_invc_no' => 42])->save();

        return $sale->fresh();
    }

    /** @test */
    public function a_refund_is_skipped_when_the_original_sale_was_never_accepted_by_etims(): void
    {
        $business = $this->configuredBusiness();
        $sale = $this->makeSaleWithProduct($business); // etims_status defaults to null/pending
        $refund = EtimsRefund::create(['business_id' => $business->id, 'source_type' => 'sale_cancel', 'source_id' => $sale->id, 'sale_id' => $sale->id, 'amount' => 300]);

        $result = EtimsService::forBusiness($business)->submitRefund($refund);

        $this->assertEquals('skipped', $result['status']);
        $this->assertEquals('skipped', $refund->fresh()->status);
    }

    /** @test */
    public function a_sale_cancel_refund_is_submitted_as_a_credit_note_against_the_original_invoice_number(): void
    {
        $business = $this->configuredBusiness();
        $sale = $this->submittedSale($business);
        $refund = EtimsRefund::create(['business_id' => $business->id, 'source_type' => 'sale_cancel', 'source_id' => $sale->id, 'sale_id' => $sale->id, 'amount' => 300]);

        Http::fake(['*/saveItem' => Http::response($this->fakeSuccess(), 200), '*/saveTrnsSalesOsdc' => Http::response($this->fakeSuccess(['curRcptNo' => 900]), 200)]);

        $result = EtimsService::forBusiness($business)->submitRefund($refund);

        $this->assertEquals('submitted', $result['status']);
        $this->assertEquals('900', $refund->fresh()->cuin);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'saveTrnsSalesOsdc') && $r['rcptTyCd'] === 'R' && $r['orgInvcNo'] === 42);
    }

    /** @test */
    public function a_sale_cancel_refund_nets_off_amounts_already_covered_by_earlier_returns(): void
    {
        $business = $this->configuredBusiness();
        $sale = $this->submittedSale($business);
        // KSh 100 of the KSh 300 sale was already returned and submitted separately.
        EtimsRefund::create(['business_id' => $business->id, 'source_type' => 'sale_return', 'source_id' => 1, 'sale_id' => $sale->id, 'amount' => 100, 'status' => 'submitted']);
        $refund = EtimsRefund::create(['business_id' => $business->id, 'source_type' => 'sale_cancel', 'source_id' => $sale->id, 'sale_id' => $sale->id, 'amount' => 300]);

        Http::fake(['*/saveItem' => Http::response($this->fakeSuccess(), 200), '*/saveTrnsSalesOsdc' => Http::response($this->fakeSuccess(), 200)]);

        EtimsService::forBusiness($business)->submitRefund($refund);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'saveTrnsSalesOsdc') && abs($r['totAmt'] - 200) < 0.01);
    }

    /** @test */
    public function a_credit_note_refund_is_skipped_when_its_invoice_was_never_reported(): void
    {
        $business = $this->configuredBusiness();
        $customer = \App\Models\Customer::create(['business_id' => $business->id, 'name' => 'Jane', 'phone' => '0700000000']);
        $invoice = Invoice::create([
            'business_id' => $business->id, 'user_id' => $business->users()->first()->id, 'customer_id' => $customer->id,
            'invoice_number' => 'INV-9', 'issue_date' => now(), 'due_date' => now(), 'subtotal' => 100, 'total' => 100, 'status' => 'paid',
            // etims_status intentionally left null — never reported.
        ]);
        $cn = CreditNote::create(['business_id' => $business->id, 'invoice_id' => $invoice->id, 'customer_id' => $customer->id, 'user_id' => $business->users()->first()->id, 'number' => 'CN-1', 'reason' => 'x', 'status' => 'issued', 'subtotal' => 100, 'total' => 100]);
        CreditNoteItem::create(['credit_note_id' => $cn->id, 'description' => 'x', 'quantity' => 1, 'unit_price' => 100, 'total' => 100]);
        $refund = EtimsRefund::create(['business_id' => $business->id, 'source_type' => 'credit_note', 'source_id' => $cn->id, 'invoice_id' => $invoice->id, 'amount' => 100]);

        $result = EtimsService::forBusiness($business)->submitRefund($refund);

        $this->assertEquals('skipped', $result['status']);
        $this->assertStringContainsString('not linked to an invoice', $result['message']);
    }

    /** @test */
    public function a_sale_return_refund_is_submitted_with_the_returned_lines_only(): void
    {
        $business = $this->configuredBusiness();
        $sale = $this->submittedSale($business);
        $item = SaleItem::where('sale_id', $sale->id)->first();
        $return = SaleReturn::create(['business_id' => $business->id, 'sale_id' => $sale->id, 'user_id' => $business->users()->first()->id, 'return_number' => 'RET-1', 'total_refund' => 150, 'stock_action' => 'restock', 'refund_method' => 'cash', 'reason' => 'faulty']);
        SaleReturnItem::create(['sale_return_id' => $return->id, 'sale_item_id' => $item->id, 'product_id' => $item->product_id, 'product_name' => $item->product_name, 'quantity_returned' => 1, 'unit_price' => 150, 'subtotal' => 150]);
        $refund = EtimsRefund::create(['business_id' => $business->id, 'source_type' => 'sale_return', 'source_id' => $return->id, 'sale_id' => $sale->id, 'amount' => 150]);

        Http::fake(['*/saveItem' => Http::response($this->fakeSuccess(), 200), '*/saveTrnsSalesOsdc' => Http::response($this->fakeSuccess(), 200)]);

        $result = EtimsService::forBusiness($business)->submitRefund($refund);

        $this->assertEquals('submitted', $result['status']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'saveTrnsSalesOsdc') && $r['totAmt'] == 150 && count($r['itemList']) === 1);
    }

    // ── SubmitEtimsDocument job ──────────────────────────────────────────────

    /** @test */
    public function the_job_makes_no_api_call_at_all_when_the_business_is_not_configured(): void
    {
        [, , $business] = $this->scaffoldOrg(); // eTIMS not enabled
        $sale = $this->makeSaleWithProduct($business);

        Http::fake();
        (new SubmitEtimsDocument('sale', $sale->id))->handle();

        Http::assertNothingSent();
        $this->assertNull($sale->fresh()->etims_status);
    }

    /** @test */
    public function the_job_submits_a_sale_when_the_business_is_configured(): void
    {
        $business = $this->configuredBusiness();
        $sale = $this->makeSaleWithProduct($business);

        Http::fake([
            '*/saveItem'          => Http::response($this->fakeSuccess(), 200),
            '*/saveTrnsSalesOsdc' => Http::response($this->fakeSuccess(), 200),
            '*/insertStockIO'     => Http::response($this->fakeSuccess(), 200),
            '*/saveStockMaster'   => Http::response($this->fakeSuccess(), 200),
        ]);

        (new SubmitEtimsDocument('sale', $sale->id))->handle();

        $this->assertEquals('submitted', $sale->fresh()->etims_status);
    }

    /** @test */
    public function a_refund_job_waits_for_the_original_sale_still_pending_in_the_queue(): void
    {
        $business = $this->configuredBusiness();
        $sale = $this->makeSaleWithProduct($business);
        $sale->forceFill(['etims_status' => 'pending'])->save();
        $refund = EtimsRefund::create(['business_id' => $business->id, 'source_type' => 'sale_cancel', 'source_id' => $sale->id, 'sale_id' => $sale->id, 'amount' => 300]);

        Http::fake();
        $job = new SubmitEtimsDocument('refund', $refund->id);
        $job->handle();

        Http::assertNothingSent();
        $this->assertEquals('pending', $refund->fresh()->status, 'it should be waiting to retry, not marked failed or skipped');
    }
}
