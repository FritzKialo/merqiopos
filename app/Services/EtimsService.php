<?php

namespace App\Services;

use App\Models\Business;
use App\Models\CreditNote;
use App\Models\EtimsRefund;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * KRA eTIMS — OSCU (Online Sales Control Unit) integration.
 *
 * Written to "eTIMS OSCU Specification v2.0" and "TIS for OSCU/VSCU Technical
 * Specifications v2.0". Flow:
 *   1. initialize()   POST /selectInitOsdcInfo  (PIN + branch + device serial)
 *                     -> returns the communication key (cmcKey) used afterwards.
 *   2. registerItem() POST /saveItem            each product once, under a KRA item code.
 *   3. submitSale()   POST /saveTrnsSalesOsdc   then stock: /insertStockIO + /saveStockMaster.
 *   4. Reversals      the same sales endpoint with receipt type R (credit note).
 *
 * NOT yet verified against the KRA sandbox — the request-header format for the
 * communication key and a few field details could not be confirmed from the
 * specification alone. All KRA-specific wiring is in this file.
 */
class EtimsService
{
    const SANDBOX_URL = 'https://etims-api-sbx.kra.go.ke/etims-api';
    const LIVE_URL    = 'https://etims-api.kra.go.ke/etims-api';

    // Tax type codes (spec 4.1): A exempt, B 16%, C 0%, D non-VAT, E 8%.
    const TAX_RATES = ['A' => 0, 'B' => 16, 'C' => 0, 'D' => 0, 'E' => 8];

    // Payment method codes (spec 4.11).
    const PAYMENT_CODES = [
        'cash' => '01', 'credit' => '02', 'bank_transfer' => '04',
        'card' => '05', 'mpesa' => '06', 'mobile_money' => '06',
    ];

    public function __construct(private Business $business) {}

    public static function forBusiness(Business $business): self
    {
        return new self($business);
    }

    /** Device activated (communication key held) and switched on. */
    public function isConfigured(): bool
    {
        return $this->business->etims_enabled
            && !empty($this->business->etims_device_serial)
            && !empty($this->business->etims_cmc_key)
            && !empty($this->business->kra_pin);
    }

    // ── Transport ─────────────────────────────────────────────────────────────

    private function baseUrl(): string
    {
        return $this->business->etims_environment === 'production' ? self::LIVE_URL : self::SANDBOX_URL;
    }

    private function bhfId(): string
    {
        return $this->business->etims_bhf_id ?: '00';
    }

    private function headers(): array
    {
        return [
            'Content-Type' => 'application/json',
            'tin'          => $this->business->kra_pin,
            'bhfId'        => $this->bhfId(),
            'cmcKey'       => (string) $this->business->etims_cmc_key,
        ];
    }

    /**
     * POST a request to the eTIMS API. Returns [ok, body, message]; ok only
     * when the server answers resultCd "000" (success).
     */
    private function call(string $path, array $body, bool $withKey = true): array
    {
        try {
            $req = Http::timeout(15)->acceptJson()->asJson();
            $req = $withKey
                ? $req->withHeaders($this->headers())
                : $req->withHeaders(['tin' => $this->business->kra_pin, 'bhfId' => $this->bhfId()]);

            $response = $req->post($this->baseUrl() . $path, $body);
            $json = $response->json();

            if ($response->successful() && is_array($json) && ($json['resultCd'] ?? null) === '000') {
                return [true, $json, $json['resultMsg'] ?? 'OK'];
            }

            $msg = is_array($json) ? ($json['resultMsg'] ?? 'Request failed') : ('HTTP ' . $response->status());
            return [false, $json ?? ['error' => $msg], $msg];
        } catch (\Throwable $e) {
            return [false, ['error' => $e->getMessage()], $e->getMessage()];
        }
    }

    // ── Device activation ─────────────────────────────────────────────────────

    /** Activate the OSCU device and store the communication key it returns. */
    public function initialize(): array
    {
        if (empty($this->business->kra_pin) || empty($this->business->etims_device_serial)) {
            return ['status' => 'failed', 'message' => 'Set the KRA PIN and the device serial number first.'];
        }

        [$ok, $body, $msg] = $this->call('/selectInitOsdcInfo', [
            'tin'      => $this->business->kra_pin,
            'bhfId'    => $this->bhfId(),
            'dvcSrlNo' => $this->business->etims_device_serial,
        ], false);

        if (! $ok) {
            return ['status' => 'failed', 'message' => $msg];
        }

        $info = $body['data']['info'] ?? [];
        if (empty($info['cmcKey'])) {
            return ['status' => 'failed', 'message' => 'KRA accepted the request but returned no communication key.'];
        }

        $this->business->forceFill([
            'etims_cmc_key'        => $info['cmcKey'],   // encrypted by the model's mutator
            'etims_sdc_id'         => $info['sdcId'] ?? ($info['sdicId'] ?? null),
            'etims_mrc_no'         => $info['mrcNo'] ?? null,
            'etims_initialized_at' => now(),
        ])->save();

        return ['status' => 'ok', 'message' => 'Device activated' . (isset($info['taxprNm']) ? ' for ' . $info['taxprNm'] : '') . '.'];
    }

    // ── Items ─────────────────────────────────────────────────────────────────

    private function classificationFor(?Product $product): ?string
    {
        return $product?->etims_item_cls_cd ?: $this->business->etims_default_item_cls_cd ?: null;
    }

    /**
     * Next unique item code, e.g. KE2NTXU0000012 (spec 4.19). Item codes must be
     * unique per taxpayer PIN, and several stores (branches) can share one PIN,
     * so the counter is taken across every store with this PIN.
     */
    private function nextItemCode(int $typeCd): string
    {
        $n = DB::transaction(function () {
            $stores = Business::where('kra_pin', $this->business->kra_pin)->lockForUpdate()->get();
            $next = (int) $stores->max('etims_last_item_no') + 1;
            Business::whereKey($this->business->id)->update(['etims_last_item_no' => $next]);
            $this->business->etims_last_item_no = $next;
            return $next;
        });
        return 'KE' . $typeCd . 'NT' . 'XU' . str_pad((string) $n, 7, '0', STR_PAD_LEFT);
    }

    private function taxTypeFor(?Product $product): string
    {
        // A taxpayer who isn't VAT-registered reports every sale as tax type D
        // (non-VAT, no tax) — eTIMS still applies to them.
        if (! $this->business->isVatRegistered()) {
            return 'D';
        }

        return match ($product?->effectiveTaxCategory() ?? 'standard') {
            'exempt'     => 'A',
            'zero_rated' => 'C',
            'reduced'    => 'E',
            default      => 'B',
        };
    }

    private function registerItemCall(string $itemCd, string $clsCd, string $name, string $taxTy, float $price, int $typeCd, ?string $barcode = null): array
    {
        return $this->call('/saveItem', [
            'tin'         => $this->business->kra_pin,
            'bhfId'       => $this->bhfId(),
            'itemCd'      => $itemCd,
            'itemClsCd'   => $clsCd,
            'itemTyCd'    => (string) $typeCd,
            'itemNm'      => mb_substr($name, 0, 200),
            'itemStdNm'   => null,
            'orgnNatCd'   => 'KE',
            'pkgUnitCd'   => 'NT',
            'qtyUnitCd'   => 'U',
            'taxTyCd'     => $taxTy,
            'btchNo'      => null,
            'bcd'         => $barcode,
            'dftPrc'      => round($price, 2),
            'grpPrcL1'    => null, 'grpPrcL2' => null, 'grpPrcL3' => null, 'grpPrcL4' => null, 'grpPrcL5' => null,
            'addInfo'     => null,
            'sftyQty'     => null,
            'isrcAplcbYn' => 'N',
            'useYn'       => 'Y',
            'regrId'      => 'system', 'regrNm' => 'system', 'modrId' => 'system', 'modrNm' => 'system',
        ]);
    }

    /** Make sure the product exists at KRA; returns its item code or throws. */
    private function ensureProductItem(Product $product): string
    {
        if ($product->etims_item_cd && $product->etims_registered_at) {
            return $product->etims_item_cd;
        }

        $cls = $this->classificationFor($product);
        if (! $cls) {
            throw new \RuntimeException('Set a default item classification code in eTIMS settings — KRA requires one for every item.');
        }

        $code = $product->etims_item_cd ?: $this->nextItemCode(($product->is_service ?? false) ? 3 : 2);
        [$ok, , $msg] = $this->registerItemCall(
            $code, $cls, $product->name, $this->taxTypeFor($product),
            (float) $product->selling_price, ($product->is_service ?? false) ? 3 : 2, $product->barcode ?? null
        );

        if (! $ok) {
            $product->forceFill(['etims_item_cd' => $code])->save();
            throw new \RuntimeException("KRA rejected item \"{$product->name}\": {$msg}");
        }

        $product->forceFill(['etims_item_cd' => $code, 'etims_item_cls_cd' => $cls, 'etims_registered_at' => now()])->save();
        return $code;
    }

    /** Catch-all item for document lines that aren't a stocked product (invoice/credit-note free text). */
    private function ensureGenericItem(): array
    {
        $cls = $this->business->etims_default_item_cls_cd;
        if (! $cls) {
            throw new \RuntimeException('Set a default item classification code in eTIMS settings — KRA requires one for every item.');
        }
        if ($this->business->etims_generic_item_cd) {
            return [$this->business->etims_generic_item_cd, $cls];
        }

        $code = $this->nextItemCode(3);
        [$ok, , $msg] = $this->registerItemCall($code, $cls, 'General sales', $this->taxTypeFor(null), 0, 3);
        if (! $ok) {
            throw new \RuntimeException("KRA rejected the general sales item: {$msg}");
        }
        $this->business->forceFill(['etims_generic_item_cd' => $code])->save();
        return [$code, $cls];
    }

    // ── Sequential numbers ────────────────────────────────────────────────────

    private function nextInvcNo(): int
    {
        return DB::transaction(function () {
            $b = Business::whereKey($this->business->id)->lockForUpdate()->first();
            $b->increment('etims_last_invc_no');
            return (int) $b->fresh()->etims_last_invc_no;
        });
    }

    // ── Payload building ──────────────────────────────────────────────────────

    /**
     * Turn generic document lines into the receipt payload. Every amount is
     * VAT-inclusive: $total is what the customer was charged/refunded after
     * every discount and $vat the VAT contained in it. Discounts are spread
     * over the lines and VAT over the taxable lines so net + tax == total for
     * the header and each line.
     *
     * $lines: [product(?Product), name, qty, unit_price, gross]
     */
    private function buildReceipt(array $lines, float $total, float $vat, array $meta): array
    {
        $grossSum = array_sum(array_column($lines, 'gross'));
        $factor   = $grossSum > 0 ? $total / $grossSum : 1.0;

        // Resolve KRA item codes (registers products on first use).
        foreach ($lines as $i => $l) {
            if ($l['product']) {
                $lines[$i]['itemCd']  = $this->ensureProductItem($l['product']);
                $lines[$i]['clsCd']   = $l['product']->etims_item_cls_cd ?: $this->classificationFor($l['product']);
                $lines[$i]['taxTy']   = $this->taxTypeFor($l['product']);
            } else {
                [$cd, $cls] = $this->ensureGenericItem();
                $lines[$i]['itemCd'] = $cd;
                $lines[$i]['clsCd']  = $cls;
                $lines[$i]['taxTy']  = $this->taxTypeFor(null);
            }
        }

        $taxableBase = 0.0;
        foreach ($lines as $l) {
            if (in_array($l['taxTy'], ['B', 'E'], true)) {
                $taxableBase += $l['gross'] * $factor;
            }
        }

        $sumNet = ['A' => 0.0, 'B' => 0.0, 'C' => 0.0, 'D' => 0.0, 'E' => 0.0];
        $sumTax = ['A' => 0.0, 'B' => 0.0, 'C' => 0.0, 'D' => 0.0, 'E' => 0.0];
        $itemList = [];

        foreach ($lines as $i => $l) {
            $lineTotal = round($l['gross'] * $factor, 2);
            $discount  = round($l['gross'] - $lineTotal, 2);
            $lineTax   = (in_array($l['taxTy'], ['B', 'E'], true) && $taxableBase > 0)
                ? round($vat * ($lineTotal / $taxableBase), 2) : 0.0;
            $lineNet   = round($lineTotal - $lineTax, 2);

            $sumNet[$l['taxTy']] += $lineNet;
            $sumTax[$l['taxTy']] += $lineTax;

            $itemList[] = [
                'itemSeq'   => $i + 1,
                'itemCd'    => $l['itemCd'],
                'itemClsCd' => $l['clsCd'],
                'itemNm'    => mb_substr($l['name'], 0, 200),
                'bcd'       => null,
                'pkgUnitCd' => 'NT',
                'pkg'       => 1,
                'qtyUnitCd' => 'U',
                'qty'       => $l['qty'],
                'prc'       => $l['unit_price'],
                'splyAmt'   => round($l['gross'], 2),
                'dcRt'      => $l['gross'] > 0 ? round($discount / $l['gross'] * 100, 2) : 0,
                'dcAmt'     => $discount,
                'isrccCd'   => null,
                'isrccNm'   => null,
                'isrcRt'    => null,
                'isrcAmt'   => null,
                'taxTyCd'   => $l['taxTy'],
                'taxblAmt'  => $lineNet,
                'taxAmt'    => $lineTax,
                'totAmt'    => $lineTotal,
            ];
        }

        $rates = self::TAX_RATES;
        $rates['B'] = (float) ($this->business->vat_rate ?? 16);
        $now = now()->format('YmdHis');

        $payload = [
            'tin'          => $this->business->kra_pin,
            'bhfId'        => $this->bhfId(),
            'trdInvcNo'    => $meta['trdInvcNo'],
            'invcNo'       => $meta['invcNo'],
            'orgInvcNo'    => $meta['orgInvcNo'] ?? 0,
            'custTin'      => null,
            'custNm'       => $meta['custNm'] ?? null,
            'salesTyCd'    => 'N',
            'rcptTyCd'     => $meta['rcptTyCd'] ?? 'S',
            'pmtTyCd'      => $meta['pmtTyCd'] ?? '01',
            'salesSttsCd'  => $meta['salesSttsCd'] ?? '02',
            'cfmDt'        => $now,
            'salesDt'      => now()->format('Ymd'),
            'stockRlsDt'   => $now,
            'cnclReqDt'    => null,
            'cnclDt'       => null,
            'rfdDt'        => $meta['rfdDt'] ?? null,
            'rfdRsnCd'     => $meta['rfdRsnCd'] ?? null,
            'totItemCnt'   => count($itemList),
            'taxblAmtA'    => round($sumNet['A'], 2), 'taxblAmtB' => round($sumNet['B'], 2), 'taxblAmtC' => round($sumNet['C'], 2),
            'taxblAmtD'    => round($sumNet['D'], 2), 'taxblAmtE' => round($sumNet['E'], 2),
            'taxRtA'       => $rates['A'], 'taxRtB' => $rates['B'], 'taxRtC' => $rates['C'], 'taxRtD' => $rates['D'], 'taxRtE' => $rates['E'],
            'taxAmtA'      => round($sumTax['A'], 2), 'taxAmtB' => round($sumTax['B'], 2), 'taxAmtC' => round($sumTax['C'], 2),
            'taxAmtD'      => round($sumTax['D'], 2), 'taxAmtE' => round($sumTax['E'], 2),
            'totTaxblAmt'  => round(array_sum($sumNet), 2),
            'totTaxAmt'    => round(array_sum($sumTax), 2),
            'totAmt'       => round($total, 2),
            'prchrAcptcYn' => 'N',
            'remark'       => null,
            'regrId'       => (string) ($meta['userId'] ?? 'system'),
            'regrNm'       => (string) ($meta['userName'] ?? 'system'),
            'modrId'       => (string) ($meta['userId'] ?? 'system'),
            'modrNm'       => (string) ($meta['userName'] ?? 'system'),
            'receipt'      => [
                'custTin'      => null,
                'custMblNo'    => $meta['custMblNo'] ?? null,
                'rcptPbctDt'   => $now,
                'trdeNm'       => $this->business->name,
                'adrs'         => $this->business->address ?? null,
                'topMsg'       => null,
                'btmMsg'       => null,
                'prchrAcptcYn' => 'N',
            ],
            'itemList'     => $itemList,
        ];

        return $payload;
    }

    private function saleLines(Sale $sale): array
    {
        $lines = [];
        foreach ($sale->items as $item) {
            $lines[] = [
                'product'    => $item->product,
                'name'       => $item->product?->name ?? $item->product_name ?? 'Item',
                'qty'        => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'gross'      => (float) ($item->subtotal ?? ($item->quantity * $item->unit_price)),
            ];
        }
        return $lines;
    }

    private function paymentCode(?string $method): string
    {
        return self::PAYMENT_CODES[$method ?? 'cash'] ?? '07';
    }

    // ── Sales ─────────────────────────────────────────────────────────────────

    public function submitSale(Sale $sale): array
    {
        $sale->load(['items.product', 'customer', 'user']);

        try {
            if (! $sale->etims_invc_no) {
                $sale->forceFill(['etims_invc_no' => $this->nextInvcNo()])->save();
            }

            $payload = $this->buildReceipt(
                $this->saleLines($sale),
                round((float) $sale->total_amount, 2),
                round((float) ($sale->vat_amount ?? $sale->tax_amount ?? 0), 2),
                [
                    'trdInvcNo' => (int) $sale->id,
                    'invcNo'    => (int) $sale->etims_invc_no,
                    'custNm'    => $sale->customer?->name,
                    'custMblNo' => $sale->customer?->phone,
                    'pmtTyCd'   => $this->paymentCode($sale->payment_method),
                    'userId'    => $sale->user_id,
                    'userName'  => $sale->user?->name,
                ]
            );
        } catch (\RuntimeException $e) {
            return $this->fail($sale, $e->getMessage());
        }

        return $this->send($payload, $sale, $this->saleStockLines($sale), 11);
    }

    /** Stock lines for the outgoing stock report ([itemCd,...]) — built after registration. */
    private function saleStockLines(Sale $sale): array
    {
        $out = [];
        foreach ($sale->items as $item) {
            if ($item->product) {
                $out[] = ['product' => $item->product, 'qty' => (float) $item->quantity, 'price' => (float) $item->unit_price];
            }
        }
        return $out;
    }

    public function submitInvoice(Invoice $invoice): array
    {
        $invoice->load(['items.product', 'customer', 'user']);

        try {
            if (! $invoice->etims_invc_no) {
                $invoice->forceFill(['etims_invc_no' => $this->nextInvcNo()])->save();
            }

            $lines = [];
            $grossSum = 0.0;
            foreach ($invoice->items as $it) {
                $gross = (float) $it->subtotal + (float) $it->vat_amount;   // invoice lines are VAT-exclusive
                $grossSum += $gross;
                $lines[] = [
                    'product'    => $it->product,
                    'name'       => $it->description ?: ($it->product?->name ?? 'Item'),
                    'qty'        => (float) $it->quantity,
                    'unit_price' => (float) $it->unit_price,
                    'gross'      => $gross,
                ];
            }
            $total = round((float) $invoice->total, 2);
            $vat   = $grossSum > 0 ? round((float) $invoice->vat_amount * $total / $grossSum, 2) : 0.0;

            $payload = $this->buildReceipt($lines, $total, $vat, [
                'trdInvcNo' => (int) $invoice->id,
                'invcNo'    => (int) $invoice->etims_invc_no,
                'custNm'    => $invoice->customer?->name,
                'custMblNo' => $invoice->customer?->phone,
                'pmtTyCd'   => '02',
                'userId'    => $invoice->user_id,
                'userName'  => $invoice->user?->name,
            ]);
        } catch (\RuntimeException $e) {
            return $this->fail($invoice, $e->getMessage());
        }

        $stock = [];
        foreach ($invoice->items as $it) {
            if ($it->product) {
                $stock[] = ['product' => $it->product, 'qty' => (float) $it->quantity, 'price' => (float) $it->unit_price];
            }
        }
        return $this->send($payload, $invoice, $stock, 11);
    }

    /** Submit one receipt, store KRA's response, then report the stock movement. */
    private function send(array $payload, Sale|Invoice $model, array $stockLines, int $sarTyCd): array
    {
        [$ok, $body, $msg] = $this->call('/saveTrnsSalesOsdc', $payload);

        if (! $ok) {
            return $this->fail($model, $msg, $body);
        }

        $data = $body['data'] ?? [];
        $model->update([
            'etims_cuin'         => isset($data['curRcptNo']) ? (string) $data['curRcptNo'] : null,
            'etims_status'       => 'submitted',
            'etims_response'     => $body,
            'etims_submitted_at' => now(),
        ]);

        // Stock is reported separately; a failure here must not undo a
        // receipt KRA has already accepted, so it is only logged.
        try {
            $this->reportStock($stockLines, $sarTyCd, (int) $payload['invcNo'], $payload['totAmt']);
        } catch (\Throwable $e) {
            \Log::warning('eTIMS stock report failed for receipt ' . $payload['invcNo'] . ': ' . $e->getMessage());
        }

        return ['cuin' => $model->etims_cuin, 'status' => 'submitted', 'message' => 'Successfully submitted to eTIMS.'];
    }

    private function fail(Sale|Invoice $model, string $msg, ?array $body = null): array
    {
        $model->update([
            'etims_status'   => 'failed',
            'etims_response' => $body ?? ['error' => $msg],
        ]);
        return ['cuin' => null, 'status' => 'failed', 'message' => $msg];
    }

    // ── Stock ─────────────────────────────────────────────────────────────────

    /**
     * Report a stock movement (/insertStockIO) then the new remaining quantity
     * of each item (/saveStockMaster). $sarTyCd per spec 4.15: 11 outgoing
     * sale, 03 incoming return.
     */
    private function reportStock(array $lines, int $sarTyCd, int $sarNo, float $totAmt): void
    {
        if (! $lines) return;

        $itemList = [];
        $net = $tax = 0.0;
        foreach ($lines as $i => $l) {
            /** @var Product $p */
            $p = $l['product'];
            if (! $p->etims_item_cd) continue;
            $gross  = round($l['qty'] * $l['price'], 2);
            $taxTy  = $this->taxTypeFor($p);
            $rate   = $taxTy === 'B' ? (float) ($this->business->vat_rate ?? 16) : ($taxTy === 'E' ? 8 : 0);
            $lineTax = $rate > 0 ? round($gross * $rate / (100 + $rate), 2) : 0.0;
            $net += $gross - $lineTax; $tax += $lineTax;
            $itemList[] = [
                'itemSeq'   => $i + 1, 'itemCd' => $p->etims_item_cd, 'itemClsCd' => $p->etims_item_cls_cd,
                'itemNm'    => mb_substr($p->name, 0, 200), 'bcd' => null, 'pkgUnitCd' => 'NT', 'pkg' => 1,
                'qtyUnitCd' => 'U', 'qty' => $l['qty'], 'itemExprDt' => null, 'prc' => $l['price'],
                'splyAmt'   => $gross, 'totDcAmt' => 0, 'taxblAmt' => round($gross - $lineTax, 2),
                'taxTyCd'   => $taxTy, 'taxAmt' => $lineTax, 'totAmt' => $gross,
            ];
        }
        if (! $itemList) return;

        $this->call('/insertStockIO', [
            'tin' => $this->business->kra_pin, 'bhfId' => $this->bhfId(),
            'sarNo' => $sarNo, 'orgSarNo' => $sarNo, 'regTyCd' => 'A',
            'custTin' => null, 'custNm' => null, 'custBhfId' => null,
            'sarTyCd' => (string) $sarTyCd, 'ocrnDt' => now()->format('Ymd'),
            'totItemCnt' => count($itemList), 'totTaxblAmt' => round($net, 2), 'totTaxAmt' => round($tax, 2), 'totAmt' => round($totAmt, 2),
            'remark' => null, 'regrId' => 'system', 'regrNm' => 'system', 'modrId' => 'system', 'modrNm' => 'system',
            'itemList' => $itemList,
        ]);

        foreach ($lines as $l) {
            $p = $l['product']->fresh();
            if (! $p || ! $p->etims_item_cd) continue;
            $this->call('/saveStockMaster', [
                'tin' => $this->business->kra_pin, 'bhfId' => $this->bhfId(),
                'itemCd' => $p->etims_item_cd, 'rsdQty' => (float) $p->stock_qty,
                'regrId' => 'system', 'regrNm' => 'system', 'modrId' => 'system', 'modrNm' => 'system',
            ]);
        }
    }

    // ── Reversals: cancelled sales, returns, credit notes ─────────────────────
    //
    // A sale KRA already has can only be undone with a credit note: the same
    // sales endpoint, receipt type R, pointing at the original invoice number
    // (orgInvcNo), with a refund date and reason (spec 4.10 / 4.17).

    /** Build the credit-note payload for a queued EtimsRefund, or null (with $whyNot) if it can't be built. */
    public function buildRefundPayload(EtimsRefund $refund, string &$whyNot = ''): ?array
    {
        $original = null;
        $total = 0.0;
        $vat = 0.0;
        $lines = [];
        $custNm = null;

        if ($refund->source_type === 'credit_note') {
            $cn = CreditNote::with(['items.product', 'invoice', 'customer'])->find($refund->source_id);
            if (! $cn) { $whyNot = 'Credit note no longer exists.'; return null; }
            $original = $cn->invoice;
            if (! $original || empty($original->etims_invc_no) || $original->etims_status !== 'submitted') {
                $whyNot = 'The credit note is not linked to an invoice that was reported to eTIMS.';
                return null;
            }
            foreach ($cn->items as $it) {
                $lines[] = [
                    'product' => $it->product, 'name' => $it->description ?: 'Item', 'qty' => (float) $it->quantity,
                    'unit_price' => (float) $it->unit_price, 'gross' => (float) $it->total,
                ];
                $total += (float) $it->total;
                $vat   += (float) $it->vat_amount;
            }
            $custNm = $cn->customer?->name;
        } else {
            $sale = Sale::with(['items.product', 'customer'])->find($refund->sale_id);
            if (! $sale) { $whyNot = 'Sale no longer exists.'; return null; }
            $original = $sale;
            if (empty($sale->etims_invc_no) || $sale->etims_status !== 'submitted') {
                $whyNot = 'The original sale was never accepted by eTIMS, so there is nothing to reverse.';
                return null;
            }
            $custNm = $sale->customer?->name;

            if ($refund->source_type === 'sale_return') {
                $ret = SaleReturn::with('items.product')->find($refund->source_id);
                if (! $ret) { $whyNot = 'Return no longer exists.'; return null; }
                foreach ($ret->items as $ri) {
                    $lines[] = [
                        'product' => $ri->product, 'name' => $ri->product?->name ?? $ri->product_name ?? 'Item',
                        'qty' => (float) $ri->quantity_returned, 'unit_price' => (float) $ri->unit_price, 'gross' => (float) $ri->subtotal,
                    ];
                }
                $total = round((float) $ret->total_refund, 2);
            } else { // sale_cancel: whatever earlier returns haven't already refunded
                $done = (float) EtimsRefund::where('sale_id', $sale->id)->where('source_type', 'sale_return')
                    ->whereIn('status', ['submitted', 'pending'])->sum('amount');
                $saleTotal = round((float) $sale->total_amount, 2);
                $total = round(max(0, $saleTotal - $done), 2);
                $frac  = $saleTotal > 0 ? $total / $saleTotal : 0;
                foreach ($this->saleLines($sale) as $l) {
                    $l['qty']   = round($l['qty'] * $frac, 3);
                    $l['gross'] = $l['gross'] * $frac;
                    $lines[] = $l;
                }
            }

            if ($total <= 0) { $whyNot = 'Nothing left to reverse for this sale.'; return null; }
            $saleTotal = (float) $sale->total_amount;
            $saleVat   = (float) ($sale->vat_amount ?? $sale->tax_amount ?? 0);
            $vat = $saleTotal > 0 ? round($saleVat * $total / $saleTotal, 2) : 0.0;
        }

        if (! $refund->invc_no) {
            $refund->forceFill(['invc_no' => $this->nextInvcNo()])->save();
        }

        $now = now()->format('YmdHis');
        return $this->buildReceipt($lines, round($total, 2), round($vat, 2), [
            'trdInvcNo'   => (int) $refund->id,
            'invcNo'      => (int) $refund->invc_no,
            'orgInvcNo'   => (int) $original->etims_invc_no,
            'custNm'      => $custNm,
            'rcptTyCd'    => 'R',
            'salesSttsCd' => '05',
            'rfdDt'       => $now,
            'rfdRsnCd'    => '06',
            'pmtTyCd'     => $original instanceof Sale ? $this->paymentCode($original->payment_method) : '02',
        ]);
    }

    public function submitRefund(EtimsRefund $refund): array
    {
        $why = '';
        try {
            $payload = $this->buildRefundPayload($refund, $why);
        } catch (\RuntimeException $e) {
            $refund->update(['status' => 'failed', 'message' => mb_substr($e->getMessage(), 0, 500)]);
            return ['status' => 'failed', 'message' => $e->getMessage()];
        }

        if ($payload === null) {
            $refund->update(['status' => 'skipped', 'message' => $why]);
            return ['status' => 'skipped', 'message' => $why];
        }

        [$ok, $body, $msg] = $this->call('/saveTrnsSalesOsdc', $payload);

        if (! $ok) {
            $refund->update(['status' => 'failed', 'response' => $body, 'message' => mb_substr($msg, 0, 500)]);
            return ['status' => 'failed', 'message' => $msg];
        }

        $refund->update([
            'status'       => 'submitted',
            'cuin'         => isset($body['data']['curRcptNo']) ? (string) $body['data']['curRcptNo'] : null,
            'response'     => $body,
            'message'      => 'Refund reported to eTIMS.',
            'submitted_at' => now(),
        ]);

        // Returned goods come back into stock — report it (incoming return = 03).
        try {
            $stock = [];
            foreach ($payload['itemList'] as $row) {
                $p = Product::where('business_id', $this->business->id)->where('etims_item_cd', $row['itemCd'])->first();
                if ($p) $stock[] = ['product' => $p, 'qty' => (float) $row['qty'], 'price' => (float) $row['prc']];
            }
            $this->reportStock($stock, 3, (int) $payload['invcNo'], (float) $payload['totAmt']);
        } catch (\Throwable $e) {
            \Log::warning('eTIMS stock report failed for refund ' . $refund->id . ': ' . $e->getMessage());
        }

        return ['status' => 'submitted', 'message' => 'Refund reported to eTIMS.'];
    }
}
