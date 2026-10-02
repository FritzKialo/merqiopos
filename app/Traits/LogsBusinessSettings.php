<?php

namespace App\Traits;

use App\Models\AuditLog;

/**
 * Business gets update()'d constantly for reasons that have nothing to do
 * with an owner changing a setting — trial counters, subscription status,
 * feature flags, M-Pesa token refreshes from a background job, etc.
 * Attaching the generic LogsActivity trait here would flood the audit log
 * with noise on every one of those. This only fires when a field an owner
 * actually edits via a Settings page changes, and never stores the real
 * value of a credential/secret field — just that it changed.
 */
trait LogsBusinessSettings
{
    public static function bootLogsBusinessSettings(): void
    {
        static::updated(function ($model) {
            $dirty = array_intersect_key($model->getDirty(), array_flip(static::settingsWatchedFields()));
            if (empty($dirty)) {
                return;
            }

            $sensitive = static::settingsSensitiveFields();
            $before = [];
            $after  = [];
            foreach ($dirty as $field => $newValue) {
                $oldValue = $model->getOriginal($field);
                if (in_array($field, $sensitive, true)) {
                    // Note that it changed without leaking the actual
                    // secret/credential value into the audit trail.
                    $before[$field] = $oldValue ? '[REDACTED]' : null;
                    $after[$field]  = $newValue ? '[REDACTED]' : null;
                } else {
                    $before[$field] = $oldValue;
                    $after[$field]  = $newValue;
                }
            }

            $fieldList = implode(', ', array_keys($dirty));
            AuditLog::recordAction(
                'settings_updated',
                'Business settings updated (' . $fieldList . ')',
                $model,
                $before,
                $after
            );
        });
    }

    /**
     * Fields that count as a "settings change" worth logging — everything
     * an owner can actually edit from a Settings page. Deliberately
     * excludes system-managed columns (organization_id, subscription_plan,
     * status, trial_ends_at, is_default) that change via billing/admin
     * actions, not a settings form, and would otherwise dominate the log.
     */
    protected static function settingsWatchedFields(): array
    {
        return [
            'name', 'email', 'phone', 'address', 'city', 'industry', 'business_type',
            'logo', 'kra_pin', 'payment_terms',
            'payroll_settings',
            'mpesa_shortcode', 'mpesa_consumer_key', 'mpesa_consumer_secret',
            'mpesa_passkey', 'mpesa_till_number', 'mpesa_environment',
            'sms_provider', 'sms_api_key', 'sms_username', 'sms_sender_id',
            'api_token',
            'dashboard_token',
            'vat_registered', 'vat_number', 'vat_rate',
            'etims_enabled', 'etims_device_serial', 'etims_api_key', 'etims_environment',
            'whatsapp_enabled',
            'portal_enabled', 'portal_welcome_message',
            'store_slug', 'store_public', 'store_description',
            'delivery_fee', 'delivery_zones',
            'receipt_header', 'receipt_footer', 'receipt_color',
            'invoice_terms', 'invoice_bank_details',
            'show_logo_on_receipt', 'show_logo_on_invoice', 'receipt_tagline',
            'sending_domain', 'domain_verified_at',
            // Deliberately NOT watched: dkim_selector/dkim_private_key/
            // dkim_public_key — internal to the verification flow, no
            // action a person takes maps to "this changed" the way it does
            // for the fields above, and the private key must never even
            // risk appearing here (redaction is a second line of defense,
            // not the only one).
        ];
    }

    /**
     * Watched fields whose actual value is never written to the audit
     * log — only whether they changed.
     */
    protected static function settingsSensitiveFields(): array
    {
        return [
            'mpesa_consumer_key', 'mpesa_consumer_secret', 'mpesa_passkey',
            'sms_api_key', 'etims_api_key', 'api_token', 'dashboard_token',
        ];
    }
}
