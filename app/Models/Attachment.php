<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Attachment extends Model
{
    protected $fillable = [
        'business_id', 'user_id', 'attachable_type', 'attachable_id',
        'original_name', 'file_path', 'mime_type', 'file_size',
    ];

    public function scopeForBusiness($q, $id = null)
    {
        return $q->where('business_id', $id ?? Auth::user()->currentBusiness()->id);
    }

    public function getSizeHumanAttribute(): string
    {
        $bytes = $this->file_size ?? 0;
        if ($bytes < 1024)    return $bytes . 'B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . 'KB';
        return round($bytes / 1048576, 1) . 'MB';
    }
}
