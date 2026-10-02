<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'business_id', 'organization_id', 'user_id', 'role', 'kind', 'method', 'path', 'route_name',
        'status', 'outcome', 'message', 'duration_ms', 'ip', 'agent', 'request_id', 'created_at',
    ];

    protected $casts = ['created_at' => 'datetime'];

    public function business() { return $this->belongsTo(Business::class); }
    public function user()     { return $this->belongsTo(User::class); }

    public function scopeFailures($q)
    {
        return $q->whereIn('outcome', ['failed', 'validation', 'denied', 'error']);
    }
}
