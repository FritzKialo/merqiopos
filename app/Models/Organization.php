<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'owner_user_id',
        'subscription_plan',
        'status',
        'trial_ends_at',
        'admin_notes',
        'is_demo',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'is_demo'       => 'boolean',
    ];

    // ── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Excludes throwaway "Try it now" demo orgs (is_demo=true) — self-purged
     * within 24h by PurgeDemoStores, never a real customer. Platform-health
     * stats (org counts, plan breakdown, trial-ending alerts, signups this
     * week, etc.) should never count them; without this, the admin
     * dashboard's numbers churn every time someone clicks "Try the demo"
     * and a "Demo Mini-Mart — trial ends in N hours" shows up under Needs
     * Attention even though there is nothing for an admin to act on.
     */
    public function scopeReal($query)
    {
        return $query->where('is_demo', false);
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function businesses()
    {
        return $this->hasMany(Business::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    // The subscription actually paying right now, if any — eager-loadable
    // (->with('activeSubscription')) so admin/organizations.index can show
    // per-row MRR without an N+1 query per organization.
    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->where('end_date', '>=', now()->toDateString())
            ->latestOfMany('end_date');
    }

    public function users()
    {
        return $this->hasManyThrough(User::class, Business::class);
    }

    // ── Status helpers ────────────────────────────────────────────────────────

    public function isOnTrial(): bool
    {
        return $this->status === 'trial'
            && $this->trial_ends_at
            && $this->trial_ends_at->isFuture();
    }

    public function isActive(): bool
    {
        return $this->status === 'active' || $this->isOnTrial();
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Whether the org currently has paying-or-trial access — a trial still
     * running, or a Subscription row that's actually active and unexpired.
     *
     * Deliberately separate from isActive(): that only reflects the org's
     * manually-set status flag (active/trial/suspended), which stays
     * 'active' even after a paid subscription's end_date has passed —
     * nothing flips it automatically on expiry. CheckSubscription (the
     * middleware that actually gates access) has always checked the real
     * subscriptions table for this reason; Settings -> Subscription used
     * to call isActive() instead and could show "Active" / the wrong plan
     * card highlighted for an org the middleware had already locked out.
     */
    public function hasActiveAccess(): bool
    {
        return $this->isOnTrial()
            || $this->subscriptions()->where('status', 'active')->where('end_date', '>=', now()->toDateString())->exists();
    }

    // ── Plan helpers ──────────────────────────────────────────────────────────

    public function planConfig(): array
    {
        return config('plans.org.' . $this->subscription_plan, config('plans.org.solo'));
    }

    // Trial access matches whatever plan they actually signed up for (only
    // Solo, the entry tier, ever carries a trial — see RegisterController)
    // rather than an artificially elevated tier. Previously hardcoded to
    // grant Growth-level features/limits during any trial, which no longer
    // makes sense now that trials aren't offered on Growth/Enterprise at all.
    public function hasFeature(string $feature): bool
    {
        return $this->planConfig()['features'][$feature] ?? false;
    }

    // Max number of stores allowed on this plan
    public function storeLimit(): int
    {
        return $this->planConfig()['limits']['stores'] ?? 1;
    }

    // Whether the org can add another store
    public function canAddStore(): bool
    {
        $limit = $this->storeLimit();

        if ($limit === -1) return true; // unlimited

        return $this->businesses()->count() < $limit;
    }

    // True when the org has more stores than its current plan allows AND
    // hasn't (yet, or still validly) picked which ones to keep active.
    // Drives the mandatory store-selection redirect — mirrors the existing
    // "must complete X before continuing" pattern used for admin 2FA.
    // Becomes false again automatically the moment the org upgrades back
    // above its store count — no flag cleanup needed either direction.
    public function hasUnresolvedStoreOverage(): bool
    {
        $limit = $this->storeLimit();
        if ($limit === -1) return false; // unlimited plan

        $count = $this->businesses()->count();
        if ($count <= $limit) return false; // within limit, nothing to resolve

        // Over limit: resolved only if exactly $limit stores are marked kept.
        // Fewer than that (none picked yet, or a store since deleted) or more
        // (a previous pick no longer fits a further downgrade) both count as
        // unresolved.
        return $this->businesses()->where('is_default', true)->count() !== $limit;
    }

    // Max number of team members allowed across the whole organisation
    // (every store combined — not per-store). Matches hasFeature()/
    // storeLimit() — trial access is whatever plan they actually signed
    // up for, not an artificially elevated tier.
    public function userLimit(): int
    {
        return $this->planConfig()['limits']['users'] ?? 1;
    }

    // Organisation-wide team member count. Counts by organization_id directly
    // rather than the users() hasManyThrough relation — that relation expects
    // users.business_id to be set, but team members created via
    // SettingsController::storeMember() only get organization_id set (their
    // store link is the business_user pivot), so hasManyThrough would
    // silently miss them.
    public function userCount(): int
    {
        return User::where('organization_id', $this->id)->count();
    }

    // Whether the org can add another team member (organisation-wide total)
    public function canAddUser(): bool
    {
        $limit = $this->userLimit();

        if ($limit === -1 || $limit >= PHP_INT_MAX) return true; // unlimited

        return $this->userCount() < $limit;
    }

    // Human-readable plan name
    public function planName(): string
    {
        if ($this->isOnTrial()) return 'Trial';

        return $this->planConfig()['name'] ?? ucfirst($this->subscription_plan);
    }

    // Next upgrade plan key (null if already on enterprise)
    public function upgradePlan(): ?string
    {
        return match ($this->subscription_plan) {
            'solo'   => 'growth',
            'growth' => 'enterprise',
            default  => null,
        };
    }

    // Monthly price for the current plan
    public function planPrice(): int
    {
        return $this->planConfig()['price'] ?? 0;
    }

    // Estimated monthly-equivalent revenue from the currently active
    // subscription (amount ÷ its own duration in months) — subscriptions
    // are sold in arbitrary month blocks (1-12, admin/organizations.
    // grant-subscription), not fixed monthly billing, so a straight
    // "amount" isn't comparable across orgs on different-length grants.
    public function estimatedMrr(): float
    {
        $sub = $this->activeSubscription;
        if (! $sub || ! $sub->start_date || ! $sub->end_date) {
            return 0.0;
        }

        $months = max(1, $sub->start_date->diffInMonths($sub->end_date));

        return round(((float) $sub->amount) / $months, 2);
    }

    // Days remaining in an active trial (0 if not on trial or already
    // expired) — used for the urgency-colored countdown in the admin UI.
    public function trialDaysLeft(): int
    {
        if (! $this->isOnTrial()) {
            return 0;
        }

        return (int) now()->startOfDay()->diffInDays($this->trial_ends_at->startOfDay(), false);
    }
}
