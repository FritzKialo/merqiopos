<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerTag extends Model
{
    protected $fillable = ['business_id', 'name', 'color'];

    public function scopeForBusiness($q, $id = null)
    {
        return $q->where('business_id', $id ?? auth()->user()?->currentBusiness()?->id);
    }

    public function customers()
    {
        return $this->belongsToMany(Customer::class, 'customer_tag_pivot');
    }
}
