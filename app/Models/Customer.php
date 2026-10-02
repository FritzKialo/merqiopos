<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model implements AuthenticatableContract {
    use HasFactory, SoftDeletes, BelongsToBusiness, Authenticatable;

    protected $fillable = [
        'business_id', 'name', 'phone',
        'email', 'address',
        'balance_owed', 'notes', 'price_tier_id',
        'credit_limit', 'credit_balance',
        'credit_limit_enabled',
        'portal_password', 'portal_last_login', 'portal_token', 'portal_token_expires_at',
        'newsletter_unsubscribed_at',
        // Missing here meant every ->update(['loyalty_points' => ...]) /
        // ->update(['loyalty_tier' => ...]) call across SaleService,
        // SalesController and LoyaltyService silently dropped the value —
        // mass-assignment guards apply to update() the same as create().
        // Points earned via ->increment() still worked (that bypasses
        // fillable), but every redemption deduction and tier recalculation
        // was a no-op.
        'loyalty_points', 'loyalty_tier',
    ];

    protected $casts = [
        'balance_owed'                => 'decimal:2',
        'loyalty_points'              => 'decimal:2',
        'credit_limit_enabled'        => 'boolean',
        'payment_reminder_sent_at'    => 'datetime',
        'portal_last_login'           => 'datetime',
        'portal_token_expires_at'     => 'datetime',
        'newsletter_unsubscribed_at'  => 'datetime',
    ];

    // ── Auth interface helpers ────────────────────────────────────────────────
    public function getAuthIdentifierName(): string { return 'id'; }
    public function getAuthPassword(): string       { return (string) $this->portal_password; }

    // ── Business relationship ─────────────────────────────────────────────────
    public function business() { return $this->belongsTo(Business::class); }

    // Relationships
    public function customerCredits() {
        return $this->hasMany(CustomerCredit::class);
    }

    public function invoices() {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Segment tags (Settings → Customer Tags). The tag pages, the campaign
     * "by tag" segment and the pivot table all existed, but this relation did
     * not — choosing a tag when creating a campaign crashed, and nothing could
     * put a tag on a customer.
     */
    public function tags() {
        return $this->belongsToMany(CustomerTag::class, 'customer_tag_pivot', 'customer_id', 'customer_tag_id')->withTimestamps();
    }

    public function sales() {
        return $this->hasMany(Sale::class);
    }

    public function priceTier() {
        return $this->belongsTo(PriceTier::class);
    }

    // Scope: customers with outstanding debt
    public function scopeWithDebt($query) {
        return $query->where('balance_owed', '>', 0);
    }

    // Total amount spent by customer
    public function totalSpent(): float {
        return $this->sales()
            ->where('sale_status', 'completed')
            ->sum('total_amount');
    }

    // Number of completed purchases
    public function totalPurchases(): int {
        return $this->sales()
            ->where('sale_status', 'completed')
            ->count();
    }

    // Last purchase date
    public function lastPurchaseDate() {
        return $this->sales()
            ->where('sale_status', 'completed')
            ->latest()
            ->value('created_at');
    }

    // Check if customer has debt
    public function hasDebt(): bool {
        return $this->balance_owed > 0;
    }
}