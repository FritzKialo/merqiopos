<?php

namespace App\Models;

use App\Jobs\SubmitEtimsDocument;
use Illuminate\Database\Eloquent\Model;

/**
 * A reversal (refund / credit note) reported to KRA eTIMS against a sale or
 * invoice that was already reported. One row per reversal document.
 */
class EtimsRefund extends Model
{
    public const TYPES = ['sale_cancel', 'sale_return', 'credit_note'];

    protected $fillable = [
        'business_id', 'source_type', 'source_id', 'sale_id', 'invoice_id',
        'amount', 'status', 'cuin', 'response', 'message', 'submitted_at',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'response'     => 'array',
        'submitted_at' => 'datetime',
    ];

    public function business() { return $this->belongsTo(Business::class); }
    public function sale()     { return $this->belongsTo(Sale::class); }
    public function invoice()  { return $this->belongsTo(Invoice::class); }

    /** The refund row (if any) for a given source document. */
    public static function forSource(string $type, int $id): ?self
    {
        return static::where('source_type', $type)->where('source_id', $id)->latest('id')->first();
    }

    /**
     * Queue the reversal of a document KRA already knows about. Does nothing
     * unless the business reports to eTIMS AND the original sale/invoice was
     * (or is about to be) reported — otherwise there is nothing to reverse.
     */
    public static function queue(string $type, int $sourceId, Business $business, ?Sale $sale, ?Invoice $invoice, float $amount): ?self
    {
        // eTIMS applies to VAT and non-VAT taxpayers alike: only require that it is set up.
        if (! $business->isEtimsConfigured()) {
            return null;
        }

        $original = $sale ?? $invoice;
        if (! $original || ! in_array($original->etims_status, ['submitted', 'pending'], true)) {
            return null;
        }

        $refund = static::create([
            'business_id' => $business->id,
            'source_type' => $type,
            'source_id'   => $sourceId,
            'sale_id'     => $sale?->id,
            'invoice_id'  => $invoice?->id,
            'amount'      => $amount,
            'status'      => 'pending',
        ]);

        SubmitEtimsDocument::dispatch('refund', $refund->id);

        return $refund;
    }
}
