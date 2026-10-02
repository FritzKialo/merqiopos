<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AppNotification extends Model
{
    protected $table = 'app_notifications';

    protected $fillable = [
        'business_id', 'user_id', 'type', 'title', 'message', 'action_url', 'icon', 'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($q)
    {
        return $q->where('user_id', Auth::id())
                 ->where('business_id', Auth::user()->currentBusiness()->id);
    }

    public function scopeUnread($q)
    {
        return $q->whereNull('read_at');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Semantic accent (success|danger|warning|info) used by the notifications
     * page to color-code each row's icon/left-border. Keyed primarily by
     * `type` (specific + stable), falling back to a best-guess from `icon`
     * for any type not listed here, so a new notification type still gets a
     * sensible color instead of always defaulting to neutral.
     */
    public function accentColor(): string
    {
        $successTypes = ['advance_approved', 'transfer_approved'];
        $dangerTypes  = ['advance_rejected', 'overdue_invoice'];
        $warningTypes = ['low_stock'];

        if (in_array($this->type, $successTypes, true)) return 'success';
        if (in_array($this->type, $dangerTypes, true))  return 'danger';
        if (in_array($this->type, $warningTypes, true)) return 'warning';

        return match ($this->icon) {
            'check-circle' => 'success',
            'x-circle'     => 'danger',
            'warning'      => 'warning',
            default        => 'info',
        };
    }

    /**
     * Tell everyone who can act on a request (the business's managers and its
     * owner) — except the person who made it. Never lets a notification
     * problem break the action that triggered it.
     */
    public static function notifyApprovers(\App\Models\Business $business, int $exceptUserId, string $type, string $title, string $message, ?string $url = null, string $icon = 'bell'): void
    {
        try {
            $recipients = $business->users()->wherePivot('role', 'manager')->get();
            $owner = $business->organization?->owner;
            if ($owner) {
                $recipients->push($owner);
            }
            foreach ($recipients->reject(fn ($u) => $u->id === $exceptUserId)->unique('id') as $u) {
                static::send($business->id, $u->id, $type, $title, $message, $url, $icon);
            }
        } catch (\Throwable $e) {
            \Log::warning('Approver notification failed: ' . $e->getMessage());
        }
    }

    /** Notify one person, swallowing any failure. */
    public static function notifyUser(int $businessId, int $userId, string $type, string $title, string $message, ?string $url = null, string $icon = 'bell'): void
    {
        try {
            static::send($businessId, $userId, $type, $title, $message, $url, $icon);
        } catch (\Throwable $e) {
            \Log::warning('Notification failed: ' . $e->getMessage());
        }
    }

    public static function send(int $businessId, int $userId, string $type, string $title, string $message, ?string $url = null, string $icon = 'bell'): void
    {
        static::create([
            'business_id' => $businessId,
            'user_id'     => $userId,
            'type'        => $type,
            'title'       => $title,
            'message'     => $message,
            'action_url'  => $url,
            'icon'        => $icon,
        ]);
    }
}
