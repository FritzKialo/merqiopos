<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportConversation extends Model
{
    protected $fillable = [
        'organization_id', 'business_id', 'user_id', 'status', 'role', 'first_page', 'last_page',
        'last_message_by', 'last_message_preview', 'last_message_at', 'unread_admin', 'unread_tenant', 'resolved_at',
    ];

    protected $casts = ['last_message_at' => 'datetime', 'resolved_at' => 'datetime'];

    public function business() { return $this->belongsTo(Business::class); }
    public function organization() { return $this->belongsTo(Organization::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function messages() { return $this->hasMany(SupportMessage::class, 'conversation_id'); }
}
