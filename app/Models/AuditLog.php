<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'business_id', 'user_id', 'event',
        'subject_type', 'subject_id',
        'metadata', 'ip_address', 'created_at',
        // Extended fields
        'action', 'model_type', 'model_id', 'description', 'old_values', 'new_values',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()     { return $this->belongsTo(User::class); }
    public function business() { return $this->belongsTo(Business::class); }

    // subject_type/subject_id already follow morphTo's default column
    // naming ("subject" + _type/_id) — added so admin/dashboard.blade.php's
    // activity feed can resolve the actual target (Organization, Business,
    // Subscription, User) without a manual lookup per row.
    public function subject() { return $this->morphTo(); }

    public function scopeForBusiness($q, $id = null)
    {
        return $q->where('business_id', $id ?? Auth::user()->currentBusiness()->id);
    }

    /**
     * Record an auditable event (legacy signature).
     *
     * Usage:
     *   AuditLog::record('sale.created', $sale, ['total' => 1500]);
     */
    /**
     * Human-readable line for the audit list and CSV. Events recorded with
     * metadata only (sale.payment, payroll.approved, team.member_updated ...)
     * have no description, and the metadata was never shown anywhere — so the
     * log said "sale.payment Sale #113" without the amount or method.
     */
    public function summary(): string
    {
        if (!empty($this->description)) {
            return $this->description;
        }

        $parts = [];
        foreach ((array) ($this->metadata ?? []) as $key => $value) {
            if ($value === null || $value === '' || $value === []) continue;
            if (is_bool($value)) $value = $value ? 'yes' : 'no';
            if (is_array($value)) $value = json_encode($value);
            $parts[] = str_replace('_', ' ', (string) $key) . ': ' . $value;
        }

        return ($this->event ?? '') . ($parts ? ' — ' . implode(' · ', $parts) : '');
    }

    public static function record(
        string $event,
        ?Model $subject = null,
        array $metadata = [],
        ?Model $actingUser = null
    ): void {
        try {
            $user = $actingUser ?? Auth::user();

            static::create([
                'business_id'  => $user?->business_id ?? Auth::user()?->currentBusiness()?->id,
                'user_id'      => $user?->id,
                'event'        => $event,
                'subject_type' => $subject ? get_class($subject) : null,
                'subject_id'   => $subject?->id,
                'metadata'     => $metadata ?: null,
                'ip_address'   => Request::ip(),
                'created_at'   => now(),
            ]);
        } catch (\Exception $e) { /* silent fail */ }
    }

    /**
     * Record an action with old/new values (new signature used by LogsActivity trait).
     */
    public static function recordAction(string $action, string $description, $model = null, array $oldValues = [], array $newValues = []): void
    {
        try {
            $businessId = Auth::user()?->currentBusiness()?->id;
            if (!$businessId) return;

            static::create([
                'business_id' => $businessId,
                'user_id'     => Auth::id(),
                'event'       => $action,
                'action'      => $action,
                'model_type'  => $model ? class_basename($model) : null,
                'model_id'    => $model?->id,
                'subject_type' => $model ? get_class($model) : null,
                'subject_id'  => $model?->id,
                'description' => $description,
                'old_values'  => $oldValues ?: null,
                'new_values'  => $newValues ?: null,
                'ip_address'  => Request::ip(),
                'created_at'  => now(),
            ]);
        } catch (\Exception $e) { /* silent fail */ }
    }
}
