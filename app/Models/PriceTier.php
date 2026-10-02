<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PriceTier extends Model {
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'description',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function scopeForBusiness($query, int $id) {
        return $query->where('business_id', $id);
    }

    public function customers() {
        return $this->hasMany(Customer::class);
    }

    public function productPrices() {
        return $this->hasMany(ProductPriceTier::class);
    }
}
