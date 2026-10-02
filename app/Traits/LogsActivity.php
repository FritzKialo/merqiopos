<?php

namespace App\Traits;

use App\Models\AuditLog;

trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(fn($model) => AuditLog::recordAction('created', class_basename($model) . ' created', $model, [], $model->toArray()));

        static::updated(function ($model) {
            $dirty = $model->getDirty();
            // Eloquent fires 'updated' on any save() call, including a bare
            // touch() with nothing actually changed — skip those instead of
            // filling the log with no-op entries.
            if (empty($dirty)) {
                return;
            }
            // Only the changed fields' old values, not the whole row — the
            // original version of this stored a full getOriginal() snapshot
            // on every single update regardless of what changed, which
            // bloats old_values for wide models and makes the diff harder
            // to read at a glance.
            $before = array_intersect_key($model->getOriginal(), $dirty);
            AuditLog::recordAction('updated', class_basename($model) . ' updated', $model, $before, $dirty);
        });

        static::deleted(fn($model) => AuditLog::recordAction('deleted', class_basename($model) . ' deleted', $model, $model->toArray()));
    }
}
