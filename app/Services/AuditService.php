<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Write an audit log entry.
     *
     * @param  string      $event    Dot-namespaced event e.g. 'sale.created'
     * @param  Model|null  $subject  The eloquent model affected
     * @param  array       $metadata Additional key/value context
     */
    public static function log(string $event, ?Model $subject = null, array $metadata = []): void
    {
        try {
            $user     = Auth::user();
            $business = $user?->currentBusiness();

            AuditLog::create([
                'business_id'  => $business?->id,
                'user_id'      => $user?->id,
                'event'        => $event,
                'subject_type' => $subject ? get_class($subject) : null,
                'subject_id'   => $subject?->getKey(),
                'metadata'     => $metadata ?: null,
                'ip_address'   => Request::ip(),
            ]);
        } catch (\Throwable $e) {
            // Never let audit logging crash the main flow
            logger()->error('AuditService::log failed: ' . $e->getMessage());
        }
    }
}
