<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;

class CreditNote extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'business_id',
        'invoice_id',
        'customer_id',
        'user_id',
        'number',
        'reason',
        'status',
        'subtotal',
        'vat_amount',
        'total',
        'issued_at',
    ];

    protected $casts = [
        'subtotal'   => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'total'      => 'decimal:2',
        'issued_at'  => 'datetime',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(CreditNoteItem::class);
    }

    public function scopeForBusiness($query, int $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    public static function nextNumber(int $businessId): string
    {
        $last = static::where('business_id', $businessId)->max('id') ?? 0;
        return 'CRN-' . str_pad($last + 1, 5, '0', STR_PAD_LEFT);
    }

    public function issue(): void
    {
        DB::transaction(function () {
            $this->update([
                'status'    => 'issued',
                'issued_at' => now(),
            ]);

            if ($this->invoice_id) {
                // Reduce linked invoice balance
                $invoice = Invoice::find($this->invoice_id);
                if ($invoice) {
                    // Recompute from total/paid/credited rather than just
                    // subtracting from the current balance_due — must agree
                    // with Invoice::recordPayment()'s own formula, or the
                    // two silently fight each other (a payment recorded
                    // after this reverses the credit; issuing a second
                    // credit note here after that would double it back).
                    $paid     = $invoice->payments()->sum('amount');
                    $credited = $invoice->creditNotes()->where('status', 'issued')->sum('total');
                    $newBalance = max(0, (float) $invoice->total - $paid - $credited);
                    $newStatus  = $newBalance <= 0 ? 'paid' : ($paid > 0 || $credited > 0 ? 'partial' : $invoice->status);
                    $invoice->update([
                        'balance_due' => $newBalance,
                        'status'      => $newStatus,
                    ]);
                }
            } elseif ($this->customer_id) {
                // No specific invoice to apply this against — the create form
                // explicitly allows a customer-only credit note (a general
                // credit against their account). Previously this branch did
                // nothing at all: the note was created and marked "issued"
                // but never actually credited anything anywhere. Apply it as
                // store credit, the same mechanism SaleReturnController uses
                // for refund-issued credit.
                $customer = Customer::find($this->customer_id);
                if ($customer) {
                    $newBalance = (float) $customer->credit_balance + (float) $this->total;
                    $customer->update(['credit_balance' => $newBalance]);

                    CustomerCredit::create([
                        'business_id'    => $this->business_id,
                        'customer_id'    => $customer->id,
                        'user_id'        => auth()->id() ?? $this->user_id,
                        'credit_note_id' => $this->id,
                        'type'           => 'store_credit',
                        'amount'         => $this->total,
                        'balance_after'  => $newBalance,
                        'reference'      => $this->number,
                        'notes'          => "Credit note {$this->number}: {$this->reason}",
                    ]);
                }
            }
        });
    }
}
