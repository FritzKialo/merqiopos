<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InvoicePayment extends Model {
    protected $fillable = ['invoice_id','business_id','user_id','amount','method','paid_date','reference','notes'];
    protected $casts = ['paid_date'=>'date'];
    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function user()    { return $this->belongsTo(User::class); }
}
