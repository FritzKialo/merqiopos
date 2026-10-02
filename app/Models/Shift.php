<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Shift extends Model {
    protected $fillable = ['business_id','opened_by','closed_by','opening_float','closing_cash','expected_cash','cash_variance','total_sales','total_cash_sales','total_mpesa_sales','total_card_sales','total_transactions','status','opened_at','closed_at','notes'];
    protected $casts = ['opened_at'=>'datetime','closed_at'=>'datetime'];
    public function opener()  { return $this->belongsTo(User::class,'opened_by'); }
    public function closer()  { return $this->belongsTo(User::class,'closed_by'); }
    public function business(){ return $this->belongsTo(Business::class); }
    public function sales()   { return $this->hasMany(Sale::class); }
    public function isOpen()  { return $this->status === 'open'; }
    public function scopeForBusiness($q, int $id) { return $q->where('business_id', $id); }

    /**
     * The id of the business's currently open till shift, if any. Shared
     * by every Sale::create() call site in the app (SaleService,
     * TableController, QuoteController, RecurringInvoiceController,
     * ProcessRecurringInvoices) so a sale gets stamped with whichever
     * shift is open at the moment it's created — sales are never blocked
     * by the absence of an open shift, this is purely for later
     * reconciliation in ShiftController::close/closeStore.
     */
    public static function currentOpenId(int $businessId): ?int
    {
        return static::forBusiness($businessId)->where('status', 'open')->value('id');
    }
}
