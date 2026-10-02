<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CustomerCredit extends Model {
    protected $fillable = ['business_id','customer_id','user_id','sale_id','sale_return_id','credit_note_id','type','amount','balance_after','reference','notes'];
    public function customer()   { return $this->belongsTo(Customer::class); }
    public function user()       { return $this->belongsTo(User::class); }
    public function sale()       { return $this->belongsTo(Sale::class); }
    public function saleReturn() { return $this->belongsTo(SaleReturn::class); }
    public function creditNote() { return $this->belongsTo(CreditNote::class); }
    public function scopeForBusiness($q, int $id) { return $q->where('business_id', $id); }
}
