<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CustomerNotification extends Model
{
    protected $fillable = [
        'customer_id', 'business_id', 'type', 'title', 'message', 'action_url', 'icon', 'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function scopeForCustomer($q)
    {
        $customer = Auth::guard('customer')->user();

        return $q->where('customer_id', $customer?->id)
                 ->where('business_id', $customer?->business_id);
    }

    public function scopeUnread($q)
    {
        return $q->whereNull('read_at');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function accentColor(): string
    {
        return match ($this->icon) {
            'check-circle' => 'success',
            'x-circle'     => 'danger',
            'warning'      => 'warning',
            default        => 'info',
        };
    }

    public static function send(int $businessId, int $customerId, string $type, string $title, string $message, ?string $url = null, string $icon = 'bell'): void
    {
        static::create([
            'business_id' => $businessId,
            'customer_id' => $customerId,
            'type'        => $type,
            'title'       => $title,
            'message'     => $message,
            'action_url'  => $url,
            'icon'        => $icon,
        ]);
    }
}
