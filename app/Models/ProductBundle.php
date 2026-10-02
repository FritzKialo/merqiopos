<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductBundle extends Model {
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'sku',
        'description',
        'price',
        'is_active',
        'image',
    ];

    protected $casts = [
        'price'     => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function scopeForBusiness($query, int $id) {
        return $query->where('business_id', $id);
    }

    public function scopeActive($query) {
        return $query->where('is_active', true);
    }

    public function items() {
        return $this->hasMany(ProductBundleItem::class);
    }
}
