<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomFieldValue extends Model
{
    protected $fillable = ['custom_field_definition_id', 'entity_type', 'entity_id', 'value'];

    public function definition()
    {
        return $this->belongsTo(CustomFieldDefinition::class);
    }
}
