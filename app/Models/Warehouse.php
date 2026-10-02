<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Warehouse extends Model
{
    protected $fillable = ['business_id', 'name', 'code', 'location', 'is_default', 'is_active'];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
    ];

    public function stock()
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public function scopeForBusiness($q, $id = null)
    {
        return $q->where('business_id', $id ?? Auth::user()->currentBusiness()->id);
    }
}
