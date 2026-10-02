<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VoidReason extends Model {
    use HasFactory;

    protected $fillable = ['business_id', 'name', 'enabled'];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function business() {
        return $this->belongsTo(Business::class);
    }

    public function voidRequests() {
        return $this->hasMany(VoidRequest::class);
    }

    public function scopeForBusiness($query, int $businessId) {
        return $query->where('business_id', $businessId);
    }

    public function scopeEnabled($query) {
        return $query->where('enabled', true);
    }
}
