<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Memo extends Model
{
    protected $fillable = [
        'organization_id', 'business_id', 'sender_id',
        'scope', 'target_role', 'title', 'message', 'recipient_count',
    ];

    public function sender(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function business(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function scopeLabel(): string
    {
        return match ($this->scope) {
            'org'   => 'Whole organization',
            'role'  => ucfirst(str_replace('_', ' ', $this->target_role ?? '')) . 's',
            default => $this->business?->name ?? 'This store',
        };
    }
}
