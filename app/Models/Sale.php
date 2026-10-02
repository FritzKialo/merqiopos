<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model {
    use HasFactory, SoftDeletes, BelongsToBusiness;

    protected $fillable = [
        'offline_id', 'business_id', 'shift_id', 'customer_id', 'user_id',
        'invoice_number', 'subtotal', 'delivery_fee',
        'discount_amount', 'coupon_discount_amount', 'discount_id', 'tax_amount',
        'total_amount', 'paid_amount',
        'balance_due', 'payment_method',
        'mpesa_reference', 'payment_status',
        'sale_status', 'notes',
        'vat_amount', 'recurring_invoice_id',
        'etims_cuin', 'etims_status', 'etims_response', 'etims_submitted_at',
        'table_order_id', 'service_charge_amount', 'tip_amount', 'amount_tendered', 'table_guest_name',
    ];

    protected $casts = [
        'subtotal'        => 'decimal:2',
        'delivery_fee'    => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount'      => 'decimal:2',
        'total_amount'    => 'decimal:2',
        'paid_amount'     => 'decimal:2',
        'balance_due'     => 'decimal:2',
        'etims_response'      => 'array',
        'etims_submitted_at'  => 'datetime',
    ];

    // ── Relationships ──────────────────────────

    public function customer() {
        return $this->belongsTo(Customer::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function shift() {
        return $this->belongsTo(Shift::class);
    }

    public function onlineOrder() {
        return $this->hasOne(OnlineOrder::class);
    }

    /**
     * Discounts that reduce the total but have no column of their own on the
     * sale (coupon and loyalty redemption), so receipts can explain the gap
     * between subtotal and total. Loyalty is the remainder after the coupon.
     */
    /** The restaurant table order this sale was paid from, if any. One order can have several sales (split bills). */
    public function tableOrder()
    {
        return $this->belongsTo(TableOrder::class, 'table_order_id');
    }

    public function promoDiscounts(): array {
        $gap = max(0, round((float) $this->subtotal + (float) ($this->delivery_fee ?? 0)
            - (float) $this->discount_amount - (float) $this->total_amount, 2));
        $points = abs((float) \App\Models\LoyaltyTransaction::where('sale_id', $this->id)
            ->where('type', 'redeem')->sum('points'));
        $coupon = (float) \App\Models\CouponUsage::where('sale_id', $this->id)->sum('discount_applied');
        if ($points <= 0) { $coupon = $gap; }
        $coupon  = min($coupon, $gap);
        $loyalty = $points > 0 ? max(0, round($gap - $coupon, 2)) : 0;
        return ['coupon' => $coupon, 'loyalty' => $loyalty, 'points' => $points];
    }

    /**
     * Tax label printed beside a line (KRA tax types): A exempt, B standard
     * VAT, C zero-rated, D non-VAT, E reduced (8%).
     */
    public static function taxLabelForItem($item): string
    {
        return match ($item->product?->effectiveTaxCategory() ?? 'standard') {
            'exempt'     => 'A',
            'zero_rated' => 'C',
            'reduced'    => 'E',
            default      => 'B',
        };
    }

    /**
     * Net / VAT / total per tax label for the receipt's tax summary. Prices
     * are VAT-inclusive and the sale total is after every discount, so
     * discounts are spread across lines and the VAT already contained in the
     * total is spread over the taxable (B, E) lines only — the same rule the
     * eTIMS payload uses, so the printed table always matches what KRA got.
     * Returns [label => ['rate','net','tax','total']] for labels with sales.
     */
    public function taxBreakdown(): array
    {
        $this->loadMissing('items.product', 'business');
        $total = round((float) $this->total_amount, 2);
        $vat   = round((float) ($this->vat_amount ?? $this->tax_amount ?? 0), 2);

        $lines = [];
        $grossSum = 0.0;
        foreach ($this->items as $item) {
            if ((float) $item->unit_price == 0.0) continue;   // bundle component rows
            $gross = (float) ($item->subtotal ?? ($item->quantity * $item->unit_price));
            $lines[] = ['label' => self::taxLabelForItem($item), 'gross' => $gross];
            $grossSum += $gross;
        }
        $factor = $grossSum > 0 ? $total / $grossSum : 1.0;

        $taxableBase = 0.0;
        foreach ($lines as $l) {
            if (in_array($l['label'], ['B', 'E'], true)) $taxableBase += $l['gross'] * $factor;
        }

        $rates = ['A' => 0, 'B' => (float) ($this->business->vat_rate ?? 16), 'C' => 0, 'D' => 0, 'E' => 8];
        $out = [];
        foreach ($lines as $l) {
            $lineTotal = round($l['gross'] * $factor, 2);
            $lineTax   = (in_array($l['label'], ['B', 'E'], true) && $taxableBase > 0) ? round($vat * ($lineTotal / $taxableBase), 2) : 0.0;
            $row = $out[$l['label']] ?? ['rate' => $rates[$l['label']], 'net' => 0.0, 'tax' => 0.0, 'total' => 0.0];
            $row['net']   += round($lineTotal - $lineTax, 2);
            $row['tax']   += $lineTax;
            $row['total'] += $lineTotal;
            $out[$l['label']] = $row;
        }
        ksort($out);
        return $out;
    }

    public function items() {
        return $this->hasMany(SaleItem::class);
    }

    public function mpesaTransactions() {
        return $this->hasMany(MpesaTransaction::class);
    }

    public function voidRequests() {
        return $this->hasMany(VoidRequest::class);
    }

    // ── Invoice Number Generator ───────────────

    public static function generateInvoiceNumber(
        int $businessId
    ): string {
        $prefix = 'INV';
        $date   = now()->format('Ymd');
        $count  = 1;
        do {
            $number = $prefix . '-' . $date . '-'
                . str_pad($count, 4, '0', STR_PAD_LEFT);
            $exists = self::where('business_id', $businessId)
                ->where('invoice_number', $number)
                ->exists();
            $count++;
        } while ($exists);
        return $number;
    }

    // ── Scopes ─────────────────────────────────

    public function scopeToday($query) {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisMonth($query) {
        return $query->whereMonth('created_at', now()->month)
                     ->whereYear('created_at', now()->year);
    }

    public function scopeForMonth(
        $query, $month, $year
    ) {
        return $query
            ->whereMonth('created_at', $month)
            ->whereYear('created_at',  $year);
    }

    // ── Payment Status Helpers ─────────────────

    public function isPaid(): bool {
        return $this->payment_status === 'paid';
    }

    public function isPartial(): bool {
        return $this->payment_status === 'partial';
    }

    public function isUnpaid(): bool {
        return $this->payment_status === 'unpaid';
    }

    // ── Profit Calculation ─────────────────────

    public function totalProfit(): float {
        return $this->items->sum(function ($item) {
            return ($item->unit_price - $item->buying_price)
                * $item->quantity;
        });
    }
}