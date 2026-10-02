<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyRate extends Model
{
    protected $fillable = ['business_id', 'currency_code', 'rate'];

    public function scopeForBusiness($query, $businessId = null)
    {
        return $query->where('business_id', $businessId ?? auth()->user()->currentBusiness()->id);
    }

    public static $commonCurrencies = [
        'USD' => ['name' => 'US Dollar',          'symbol' => '$',   'default_rate' => 130.00],
        'EUR' => ['name' => 'Euro',                'symbol' => '€',   'default_rate' => 140.00],
        'GBP' => ['name' => 'British Pound',       'symbol' => '£',   'default_rate' => 165.00],
        'UGX' => ['name' => 'Uganda Shilling',     'symbol' => 'UGX', 'default_rate' => 0.034],
        'TZS' => ['name' => 'Tanzania Shilling',   'symbol' => 'TZS', 'default_rate' => 0.052],
    ];
}
