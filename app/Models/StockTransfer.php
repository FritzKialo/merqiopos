<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StockTransfer extends Model {
    protected $fillable = ['organization_id','from_business_id','to_business_id','requested_by','approved_by','transfer_number','status','notes','requested_at','approved_at','dispatched_at','received_at'];
    protected $casts = ['requested_at'=>'datetime','approved_at'=>'datetime','dispatched_at'=>'datetime','received_at'=>'datetime'];
    public function fromBusiness()  { return $this->belongsTo(Business::class,'from_business_id'); }
    public function toBusiness()    { return $this->belongsTo(Business::class,'to_business_id'); }
    public function requester()     { return $this->belongsTo(User::class,'requested_by'); }
    public function approver()      { return $this->belongsTo(User::class,'approved_by'); }
    public function items()         { return $this->hasMany(StockTransferItem::class); }
    public static function nextNumber(int $orgId): string {
        $last = static::where('organization_id',$orgId)->max('id') ?? 0;
        return 'TRF-'.str_pad($last+1,5,'0',STR_PAD_LEFT);
    }
    public function scopeForOrganization($q, int $id) { return $q->where('organization_id', $id); }
    // Unused today (grepped — no call site), but the unwrapped orWhere() was
    // a landmine: chained after any other where() (e.g. ->forOrganization()
    // above), the OR breaks out of that constraint's AND grouping instead of
    // staying scoped within it — the same precedence bug class found live
    // elsewhere this session (Payroll bug #4, Admin\SubscriptionController
    // bug #46). Grouped in a closure so it composes safely if ever used.
    public function scopeInvolvingBusiness($q, int $id) {
        return $q->where(fn ($w) => $w->where('from_business_id', $id)->orWhere('to_business_id', $id));
    }
}
