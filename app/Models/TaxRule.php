<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TaxRule extends Model {
    use HasFactory;

    protected $fillable = [
        'business_id', 'name', 'code', 'rate', 'priority',
        'tax_category', 'inclusive', 'enabled', 'sort_order',
    ];

    protected $casts = [
        'rate'      => 'decimal:2',
        'priority'  => 'integer',
        'inclusive' => 'boolean',
        'enabled'   => 'boolean',
    ];

    public function business() {
        return $this->belongsTo(Business::class);
    }

    public function scopeForBusiness($query, int $businessId) {
        return $query->where('business_id', $businessId);
    }

    public function scopeEnabled($query) {
        return $query->where('enabled', true);
    }

    public function scopeForCategory($query, string $category) {
        return $query->where('tax_category', $category);
    }
}
