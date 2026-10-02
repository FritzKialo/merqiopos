<?php
namespace App\Models;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Invoice extends Model {
    use SoftDeletes, LogsActivity;
    protected $fillable = ['business_id','customer_id','user_id','sale_id','quote_id','invoice_number','issue_date','due_date','subtotal','vat_amount','discount_amount','total','amount_paid','balance_due','status','notes','payment_terms','etims_cuin','etims_status','etims_response','etims_submitted_at'];
    protected $casts = ['issue_date'=>'date','due_date'=>'date','etims_response'=>'array','etims_submitted_at'=>'datetime'];
    public function business()  { return $this->belongsTo(Business::class); }
    public function customer()  { return $this->belongsTo(Customer::class); }
    public function user()      { return $this->belongsTo(User::class); }
    public function items()     { return $this->hasMany(InvoiceItem::class); }
    public function payments()  { return $this->hasMany(InvoicePayment::class); }
    public function creditNotes() { return $this->hasMany(CreditNote::class); }
    public function isOverdue() { return !in_array($this->status,['paid','cancelled']) && $this->due_date->isPast(); }
    public function scopeForBusiness($q, int $id) { return $q->where('business_id', $id); }
    // $userId lets a caller with no authenticated session (an M-Pesa webhook,
    // which runs unauthenticated) attribute the payment explicitly —
    // invoice_payments.user_id is NOT NULL, so falling through to auth()->id()
    // there would throw. Every existing authenticated caller is unaffected.
    public function recordPayment(float $amount, string $method, string $date, ?string $ref = null, ?int $userId = null): void {
        $this->payments()->create(['business_id'=>$this->business_id,'user_id'=>$userId ?? auth()->id(),'amount'=>$amount,'method'=>$method,'paid_date'=>$date,'reference'=>$ref]);
        $paid    = $this->payments()->sum('amount');
        // Must net out issued credit notes here too — this recompute used to
        // overwrite balance_due from total-paid alone, silently reversing
        // any credit CreditNote::issue() had applied the moment the next
        // payment was recorded (a customer who'd paid exactly what a
        // credited invoice said they owed would still show a balance due).
        $credited = $this->creditNotes()->where('status', 'issued')->sum('total');
        $balance  = max(0, $this->total - $paid - $credited);
        $this->update(['amount_paid'=>$paid,'balance_due'=>$balance,'status'=>($balance<=0)?'paid':(($paid>0||$credited>0)?'partial':'sent')]);
    }
    public static function nextNumber(int $businessId): string {
        $last = static::where('business_id',$businessId)->max('id') ?? 0;
        return 'INV-'.str_pad($last+1,5,'0',STR_PAD_LEFT);
    }
}
