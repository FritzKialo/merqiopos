<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomFieldDefinition extends Model
{
    protected $fillable = [
        'business_id', 'entity_type', 'label', 'field_key',
        'field_type', 'options', 'is_required', 'sort_order', 'is_active',
    ];

    protected $casts = ['options' => 'array', 'is_required' => 'boolean', 'is_active' => 'boolean'];

    public function scopeForBusiness($q, $id = null)
    {
        return $q->where('business_id', $id ?? auth()->user()?->currentBusiness()?->id);
    }

    public function values()
    {
        return $this->hasMany(CustomFieldValue::class);
    }
}
