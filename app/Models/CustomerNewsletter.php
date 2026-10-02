<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerNewsletter extends Model
{
    protected $fillable = [
        'business_id', 'sender_id', 'scope', 'title', 'message',
        'recipient_count', 'email_failed_count',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function scopeLabel(): string
    {
        return $this->scope === 'org' ? 'All stores (organization-wide)' : 'This store only';
    }
}
