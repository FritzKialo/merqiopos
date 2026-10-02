<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Newsletter extends Model
{
    protected $fillable = [
        'sender_id', 'title', 'message', 'audience_status', 'audience_plan',
        'recipient_count', 'email_failed_count',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function audienceLabel(): string
    {
        $status = $this->audience_status ? ucfirst($this->audience_status) : null;
        $plan   = $this->audience_plan ? ucfirst($this->audience_plan) . ' plan' : null;

        $parts = array_filter([$status, $plan]);

        return $parts ? implode(' · ', $parts) : 'All organizations';
    }
}
