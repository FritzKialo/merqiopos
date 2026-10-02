<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportMessage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'conversation_id', 'sender_type', 'sender_user_id', 'body', 'page',
        'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size', 'created_at',
    ];

    protected $casts = ['created_at' => 'datetime'];

    public function conversation() { return $this->belongsTo(SupportConversation::class, 'conversation_id'); }
    public function sender() { return $this->belongsTo(User::class, 'sender_user_id'); }
}
