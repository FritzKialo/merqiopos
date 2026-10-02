<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
class WithholdingTax extends Model {
    protected $table = 'withholding_taxes';
    protected $fillable = ['business_id','supplier_id','payee_name','payee_kra_pin','wht_type','gross_amount','wht_rate','wht_amount','net_amount','payment_date','certificate_number','period_month','notes'];
    protected $casts = ['payment_date' => 'date'];
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function scopeForBusiness($q, $id = null) { return $q->where('business_id', $id ?? Auth::user()->currentBusiness()->id); }
    public static function whtRates(): array {
        return [
            'consultancy'    => ['label' => 'Consultancy / Professional Fees',  'rate' => 5],
            'management_fee' => ['label' => 'Management / Training Fees',        'rate' => 5],
            'rent'           => ['label' => 'Rent (Commercial Property)',         'rate' => 7.5],
            'royalty'        => ['label' => 'Royalties',                          'rate' => 5],
            'interest'       => ['label' => 'Interest',                           'rate' => 15],
            'dividend'       => ['label' => 'Dividends (Resident)',               'rate' => 5],
            'other'          => ['label' => 'Other',                              'rate' => 10],
        ];
    }
}
