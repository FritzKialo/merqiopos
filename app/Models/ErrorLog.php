<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErrorLog extends Model
{
    protected $fillable = [
        'fingerprint', 'business_id', 'organization_id', 'user_id', 'role', 'method', 'path', 'route_name',
        'status_code', 'exception', 'message', 'file', 'line', 'trace', 'request_id', 'count',
        'alerted_count', 'alerted_at', 'first_seen_at', 'last_seen_at', 'resolved_at',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at'  => 'datetime',
        'resolved_at'   => 'datetime',
        'alerted_at'    => 'datetime',
    ];

    public function business() { return $this->belongsTo(Business::class); }
    public function user()     { return $this->belongsTo(User::class); }
}
