<?php

namespace App\Models;

use App\Traits\LogsBusinessSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Business extends Model {
    use HasFactory, LogsBusinessSettings;

    protected $fillable = [
        'organization_id',
        'name', 'email', 'phone',
        'address', 'city', 'industry',
        'business_type',
        'logo', 'kra_pin', 'payment_terms',
        'subscription_plan',
        'status', 'trial_ends_at', 'is_default',
        'payroll_settings',
        // Per-business M-Pesa credentials (for receiving customer sale payments)
        'mpesa_shortcode', 'mpesa_consumer_key', 'mpesa_consumer_secret',
        'mpesa_passkey', 'mpesa_till_number', 'mpesa_environment', 'mpesa_c2b_registered',
        // SMS notification settings
        'sms_provider', 'sms_api_key', 'sms_username', 'sms_sender_id',
        // REST API
        'api_token',
        // Read-only manager dashboard (no login required)
        'dashboard_token',
        // VAT
        'vat_registered', 'vat_number', 'vat_rate', 'service_charge_percent',
        // Digital Float (cash accountability per cashier)
        'enable_digital_float', 'default_credit_limit',
        // eTIMS
        'etims_enabled', 'etims_device_serial', 'etims_api_key', 'etims_environment',
        'etims_bhf_id', 'etims_default_item_cls_cd',
        // WhatsApp
        'whatsapp_enabled',
        // Customer portal
        'portal_enabled', 'portal_welcome_message',
        // Online store
        'store_slug', 'store_public', 'store_description',
        // Delivery — the settings page (SettingsController::updateDeliverySettings)
        // has been writing these via $business->update([...]) since the feature
        // was built, but neither column was ever listed here — Eloquent mass
        // assignment silently drops any attribute not in $fillable (no
        // exception by default), so the settings page has always shown a
        // false "Delivery settings updated." success message while saving
        // nothing at all.
        'delivery_fee', 'delivery_zones',
        // Receipt / invoice branding
        'receipt_header', 'receipt_footer', 'receipt_color',
        'invoice_terms', 'invoice_bank_details',
        'show_logo_on_receipt', 'show_logo_on_invoice', 'receipt_tagline',
        'shop_qr_scans', 'show_shop_qr_on_receipt',
        // Pesapal (card payments)
        'pesapal_consumer_key', 'pesapal_consumer_secret', 'pesapal_environment', 'pesapal_ipn_id',
        // Google Sheets live export
        'google_sheets_access_token', 'google_sheets_refresh_token',
        'google_sheets_token_expires_at', 'google_sheets_spreadsheet_id',
        'google_sheets_connected_by', 'google_sheets_connected_at', 'google_sheets_last_synced_at',
    ];

    protected $casts = [
        'trial_ends_at'    => 'datetime',
        'payroll_settings' => 'array',
        'vat_registered'   => 'boolean',
        'vat_rate'         => 'decimal:2',
        'enable_digital_float'  => 'boolean',
        'default_credit_limit'  => 'decimal:2',
        'etims_enabled'    => 'boolean',
        'whatsapp_enabled' => 'boolean',
        'portal_enabled'   => 'boolean',
        'store_public'     => 'boolean',
        'show_logo_on_receipt' => 'boolean',
        'show_shop_qr_on_receipt' => 'boolean',
        'show_logo_on_invoice' => 'boolean',
        'is_default'       => 'boolean',
        // NOTE: mpesa encrypted fields use custom accessors/mutators below
        // to handle graceful fallback if plaintext values exist in DB.
        // dkim_private_key has no such legacy-plaintext concern (brand new
        // column), so the plain 'encrypted' cast is safe here.
        'dkim_private_key'   => 'encrypted',
        'domain_verified_at' => 'datetime',
        // Brand new columns, no legacy-plaintext concern (see note above) —
        // the plain 'encrypted' cast is safe here, same reasoning as dkim_private_key.
        'google_sheets_access_token'      => 'encrypted',
        'google_sheets_refresh_token'     => 'encrypted',
        'google_sheets_token_expires_at'  => 'datetime',
        'google_sheets_connected_at'      => 'datetime',
        'google_sheets_last_synced_at'    => 'datetime',
    ];

    public function hasGoogleSheetsConnected(): bool
    {
        return !empty($this->google_sheets_refresh_token) && !empty($this->google_sheets_spreadsheet_id);
    }

    // ── M-Pesa encrypted field accessors (safe decrypt) ──────────────────────

    public function getMpesaConsumerKeyAttribute(?string $value): ?string
    {
        return $this->safeDecrypt($value);
    }

    public function getMpesaConsumerSecretAttribute(?string $value): ?string
    {
        return $this->safeDecrypt($value);
    }

    public function getMpesaPasskeyAttribute(?string $value): ?string
    {
        return $this->safeDecrypt($value);
    }

    public function setMpesaConsumerKeyAttribute(?string $value): void
    {
        $this->attributes['mpesa_consumer_key'] = $value ? encrypt($value) : null;
    }

    public function setMpesaConsumerSecretAttribute(?string $value): void
    {
        $this->attributes['mpesa_consumer_secret'] = $value ? encrypt($value) : null;
    }

    public function setMpesaPasskeyAttribute(?string $value): void
    {
        $this->attributes['mpesa_passkey'] = $value ? encrypt($value) : null;
    }

    /** Decrypt safely — returns raw value instead of crashing if not yet encrypted */
    private function safeDecrypt(?string $value): ?string
    {
        if (is_null($value)) return null;
        try {
            return decrypt($value);
        } catch (\Exception $e) {
            return $value; // plaintext fallback — will be re-encrypted on next save
        }
    }

    // The organization this store belongs to
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    // Staff members assigned to this store (via pivot)
    public function users()
    {
        return $this->belongsToMany(User::class, 'business_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    // HR profiles for all staff at this store
    public function staffProfiles()
    {
        return $this->hasMany(StaffProfile::class);
    }

    // Payroll periods for this store
    public function payrollPeriods()
    {
        return $this->hasMany(PayrollPeriod::class);
    }

    // Payroll items for this store (across all periods)
    public function payrollItems()
    {
        return $this->hasMany(PayrollItem::class);
    }

    // One business has many subscriptions (legacy — subscriptions move to org level)
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    // This relationship didn't exist at all — portal/layout.blade.php's nav
    // already checked ->business->loyaltyProgram to decide whether to show
    // the "Loyalty" link, so with no method here that always resolved to
    // null and the link never showed for any business, enabled or not.
    public function loyaltyProgram()
    {
        return $this->hasOne(LoyaltyProgram::class);
    }

    // Get the owner of the business via the organization
    public function owner()
    {
        return $this->organization?->owner();
    }

    // Check if trial is still active. Same rule as isActive()/planName()
    // below: once a store belongs to an organization, the ORG's
    // subscription is authoritative and the store's own trial_ends_at is
    // a stale leftover from before it joined an org (or from before the
    // org upgraded off its own trial) — never re-checked here, so a store
    // kept showing "Trial ends…" on its dashboard for the rest of that
    // original trial window even after the organization was upgraded to
    // a paid plan.
    public function isOnTrial(): bool {
        if ($this->organization) {
            return $this->organization->isOnTrial();
        }
        return $this->status === 'trial'
            && $this->trial_ends_at
            && $this->trial_ends_at->isFuture();
    }

    // Check if business is active.
    // A store's live status derives from its ORGANIZATION's subscription — the
    // org subscribes once and all its stores are active/expire together. An
    // explicit 'suspended' status still turns an individual store off. The
    // per-store status/trial columns are legacy and only used as an orphan
    // fallback when a store somehow has no organization.
    public function isActive(): bool {
        if ($this->status === 'suspended') {
            return false;
        }
        if ($this->organization) {
            return $this->organization->isActive();
        }
        return $this->status === 'active' || $this->isOnTrial();
    }

    // True when this store is read-only because the org downgraded below its
    // current store count and this store wasn't one of the kept ones. Only
    // ever true while the org is genuinely over its plan's store limit — an
    // upgrade back above the store count makes every store fully active
    // again immediately, regardless of is_default.
    public function isLockedByPlan(): bool {
        if (!$this->organization) return false;

        $limit = $this->organization->storeLimit();
        if ($limit === -1) return false;

        if ($this->organization->businesses()->count() <= $limit) return false;

        return !$this->is_default;
    }

    // Check if the business has its own M-Pesa credentials configured
    public function hasMpesaConfigured(): bool {
        return !empty($this->mpesa_shortcode)
            && !empty($this->mpesa_consumer_key)
            && !empty($this->mpesa_consumer_secret)
            && !empty($this->mpesa_passkey);
    }

    // Return credentials array for MpesaService constructor
    public function mpesaCredentials(): array {
        return [
            'shortcode'       => $this->mpesa_shortcode,
            'consumer_key'    => $this->mpesa_consumer_key,
            'consumer_secret' => $this->mpesa_consumer_secret,
            'passkey'         => $this->mpesa_passkey,
            'till_number'     => $this->mpesa_till_number,
            'environment'     => $this->mpesa_environment ?? 'sandbox',
        ];
    }

    // ── Pesapal encrypted field accessors ────────────────────────────────────

    public function getPesapalConsumerKeyAttribute(?string $value): ?string
    {
        return $this->safeDecrypt($value);
    }

    public function getPesapalConsumerSecretAttribute(?string $value): ?string
    {
        return $this->safeDecrypt($value);
    }

    public function setPesapalConsumerKeyAttribute(?string $value): void
    {
        $this->attributes['pesapal_consumer_key'] = $value ? encrypt($value) : null;
    }

    public function setPesapalConsumerSecretAttribute(?string $value): void
    {
        $this->attributes['pesapal_consumer_secret'] = $value ? encrypt($value) : null;
    }

    public function hasPesapalCredentials(): bool {
        return !empty($this->pesapal_consumer_key)
            && !empty($this->pesapal_consumer_secret);
    }

    public function hasPesapalConfigured(): bool {
        return $this->hasPesapalCredentials() && !empty($this->pesapal_ipn_id);
    }

    public function pesapalCredentials(): array {
        return [
            'consumer_key'    => $this->pesapal_consumer_key,
            'consumer_secret' => $this->pesapal_consumer_secret,
            'environment'     => $this->pesapal_environment ?? 'sandbox',
        ];
    }

    // ── Plan helpers ──────────────────────────────────────────────────────────

    // Get the plan config array for this business.
    // Org-level plans define a store_plan; fall back to the business's own subscription_plan.
    public function planConfig(): array {
        $orgPlan = $this->organization?->subscription_plan;
        $storePlan = $orgPlan
            ? config("plans.org.{$orgPlan}.store_plan", $this->subscription_plan)
            : $this->subscription_plan;
        return config('plans.' . $storePlan, config('plans.starter'));
    }

    // Check if this business has access to a specific feature. Trial
    // access matches whatever plan they actually signed up for (Starter —
    // the entry tier — since that's the only one a trial is ever offered
    // on; see RegisterController) rather than an artificially elevated
    // Business-tier grant, which no longer makes sense now that trials
    // aren't offered on Business/Enterprise at all. If the org has an
    // active paid subscription, org plan always wins regardless.
    public function hasFeature(string $feature): bool {
        return $this->planConfig()['features'][$feature] ?? false;
    }

    // Get the limit for a specific resource (products, users)
    public function planLimit(string $resource): int {
        return $this->planConfig()['limits'][$resource] ?? 0;
    }

    // ── Custom sending domain (campaign email branding) ─────────────────────
    // A business can verify their own domain (publishing a DKIM key + SPF
    // include we generate) so campaign email arrives genuinely "from" them
    // instead of the shared platform address. See DomainSettingsController.
    public function hasVerifiedSendingDomain(): bool {
        return (bool) $this->sending_domain && (bool) $this->domain_verified_at;
    }

    // The address campaign email should be sent from for this business —
    // their own verified domain if they have one, otherwise the shared
    // platform address (still branded with their name as the display name;
    // see CampaignController).
    public function campaignFromAddress(): string {
        return $this->hasVerifiedSendingDomain()
            ? 'no-reply@' . $this->sending_domain
            : config('mail.from.address');
    }

    // Human-readable plan name — reflects the organization's plan (the store
    // inherits it). Falls back to the store's own plan only when orphaned.
    public function planName(): string {
        if ($this->organization) {
            return $this->organization->planName();
        }

        if ($this->isOnTrial()) {
            return 'Trial';
        }

        return $this->planConfig()['name'] ?? ucfirst($this->subscription_plan);
    }

    // ── Payroll helpers ───────────────────────────────────────────────────────

    public function isPayrollEnabled(): bool
    {
        return (bool) ($this->payroll_settings['enabled'] ?? false);
    }

    public function payrollDeductions(): array
    {
        return $this->payroll_settings['deductions'] ?? [
            'paye' => false,
            'nssf' => false,
            'shif' => false,
            'housing_levy' => false,
        ];
    }

    public function isDeductionEnabled(string $type): bool
    {
        return (bool) ($this->payroll_settings['deductions'][$type] ?? false);
    }

    public function payCycle(): string
    {
        return $this->payroll_settings['pay_cycle'] ?? 'monthly';
    }

    public function employerPin(): ?string
    {
        return $this->payroll_settings['employer_pin'] ?? null;
    }

    public function autoPayrollEnabled(): bool
    {
        return (bool) ($this->payroll_settings['auto_payroll'] ?? false);
    }

    /**
     * 'calculate_only' — system creates + calculates; owner reviews, approves, pays
     * 'auto_approve'   — system creates + calculates + approves; owner only pays
     * 'fully_auto'     — system creates + calculates + approves + pays; no human touch
     */
    public function autoPayrollMode(): string
    {
        return $this->payroll_settings['auto_payroll_mode'] ?? 'calculate_only';
    }

    /** Day of month (1–28) on which auto-payroll fires. */
    public function payDay(): int
    {
        return (int) ($this->payroll_settings['pay_day'] ?? 28);
    }

    // ── VAT helpers ───────────────────────────────────────────────────────────

    public function isVatRegistered(): bool
    {
        return (bool) $this->vat_registered;
    }

    public function vatRate(): float
    {
        return $this->isVatRegistered() ? (float) ($this->vat_rate ?? 16) : 0;
    }

    // The eTIMS communication key (returned by device activation) is a credential too.
    public function setEtimsCmcKeyAttribute($value): void
    {
        $this->attributes['etims_cmc_key'] = ($value === null || $value === '')
            ? null
            : \Illuminate\Support\Facades\Crypt::encryptString($value);
    }

    public function getEtimsCmcKeyAttribute($value)
    {
        if ($value === null || $value === '') return $value;
        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    // The eTIMS API key is a credential — store it encrypted. Reads tolerate
    // values saved before this change (plain text) so nothing breaks on deploy.
    public function setEtimsApiKeyAttribute($value): void
    {
        $this->attributes['etims_api_key'] = ($value === null || $value === '')
            ? null
            : \Illuminate\Support\Facades\Crypt::encryptString($value);
    }

    public function getEtimsApiKeyAttribute($value)
    {
        if ($value === null || $value === '') return $value;
        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    // ── eTIMS helpers ─────────────────────────────────────────────────────────

    public function isEtimsConfigured(): bool
    {
        // Ready = switched on AND the device has been activated with KRA
        // (the communication key it returns is what authorises every call).
        return $this->etims_enabled
            && !empty($this->etims_device_serial)
            && !empty($this->etims_cmc_key);
    }

    // Human-readable list of business types for forms
    public static function businessTypes(): array
    {
        return [
            'retail'      => 'Retail / General Shop',
            'salon'       => 'Salon / Barbershop',
            'pharmacy'    => 'Pharmacy / Chemist',
            'restaurant'  => 'Restaurant / Café',
            'hardware'    => 'Hardware / Construction',
            'electronics' => 'Electronics / Appliances',
            'grocery'     => 'Grocery / Supermarket',
            'clothing'    => 'Clothing / Fashion',
            'services'    => 'Services / Consulting',
            'other'       => 'Other',
        ];
    }
}
