<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'organization_id', 'name', 'email',
        'password', 'role', 'is_active',
        'is_super_admin', 'last_login_at',
        'google2fa_secret', 'two_factor_enabled',
        'two_factor_confirmed_at', 'google_id',
        'two_factor_recovery_codes',
    ];

    protected $hidden = [
        'password', 'remember_token', 'google2fa_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'last_login_at'             => 'datetime',
        'is_active'                 => 'boolean',
        'is_super_admin'            => 'boolean',
        'two_factor_enabled'        => 'boolean',
        'two_factor_confirmed_at'   => 'datetime',
        'google2fa_secret'          => 'encrypted',
        'two_factor_recovery_codes' => 'array',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    // Owner belongs to an organization
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    // Stores this user is assigned to (managers/cashiers via pivot)
    public function businesses()
    {
        return $this->belongsToMany(Business::class, 'business_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    // Convenience: get the single business for a manager/cashier
    // (owners use organization->businesses() instead)
    public function business()
    {
        return $this->businesses()->first();
    }

    // All staff profiles (one per business assignment)
    public function staffProfiles()
    {
        return $this->hasMany(StaffProfile::class);
    }

    // Staff profile for the current active business
    public function staffProfile()
    {
        return $this->hasOne(StaffProfile::class);
    }

    // Staff profile for a specific business
    public function staffProfileFor(int $businessId): ?StaffProfile
    {
        return $this->staffProfiles()->where('business_id', $businessId)->first();
    }

    // ── Role helpers ──────────────────────────────────────────────────────────

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    // Organization-level manager: oversees every branch like an owner, but
    // cannot cancel the subscription or delete the organization.
    public function isOverallManager(): bool
    {
        return $this->role === 'overall_manager';
    }

    // True for any organization-wide role (owner or overall manager).
    // Use this for cross-branch access: org dashboard, branch management,
    // inter-branch transfers, cross-branch reports.
    public function roleLabel(): string
    {
        return ucwords(str_replace('_', ' ', (string) $this->role));
    }

    public function canActAsOwner(): bool
    {
        return $this->isOwner() || $this->isOverallManager();
    }

    // Reserved for the two owner-exclusive actions (billing cancel, org delete).
    public function canManageBilling(): bool
    {
        return $this->isOwner();
    }

    // Where a user lands right after logging in — shared by LoginController
    // (immediate post-login redirect), TwoFactorController (challenge
    // fallback / the "already verified" skip), and AppServiceProvider's
    // RedirectIfAuthenticated override (re-visiting /login while already
    // signed in). Every store role lands on the colorful home-menu launcher
    // (HomeController::index()), which shows only the tiles that role can
    // actually reach — for staff that's just Dashboard, since every other
    // top-level sidebar section excludes them. Super admins are the one
    // exception, going straight to the admin panel instead.
    public function postLoginRoute(): string
    {
        if ($this->isSuperAdmin()) {
            return route('admin.dashboard');
        }

        return route('menu');
    }

    // Branch ids this user may operate inter-branch transfers for.
    // Org-wide roles get every branch in the organization; a branch manager
    // gets only the branches where they hold the 'manager' pivot role.
    public function managedBusinessIds(): array
    {
        if ($this->canActAsOwner()) {
            return $this->organization
                ? $this->organization->businesses()->pluck('id')->all()
                : [];
        }

        return $this->businesses()
            ->wherePivot('role', 'manager')
            ->pluck('businesses.id')
            ->all();
    }

    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    public function isCashier(): bool
    {
        return $this->role === 'cashier';
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function hasAnyRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isStoreUser(): bool
    {
        return in_array($this->role, ['owner', 'manager', 'cashier', 'staff'], true);
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    /**
     * Sign this user out everywhere except (optionally) the session making the
     * request. Called whenever a password changes: without it, a session that
     * was already stolen, or left open on a shared till, keeps working after
     * the owner "secures" the account by changing or resetting the password.
     * Sessions live in the database (SESSION_DRIVER=database); with any other
     * driver this is a no-op.
     */
    public function endOtherSessions(?string $keepSessionId = null): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $query = \Illuminate\Support\Facades\DB::table(config('session.table', 'sessions'))
            ->where('user_id', $this->id);
        if ($keepSessionId) {
            $query->where('id', '!=', $keepSessionId);
        }
        $query->delete();
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_enabled
            && $this->two_factor_confirmed_at !== null;
    }

    // ── Context helpers ───────────────────────────────────────────────────────

    // Get the active business from session context (set by store switcher)
    public function activeBusiness(): ?Business
    {
        $businessId = session('active_business_id');

        if (!$businessId) return null;

        if ($this->canActAsOwner()) {
            return $this->organization
                ?->businesses()
                ->find($businessId);
        }

        return $this->businesses()->find($businessId);
    }

    // Resolve the business this user should operate in.
    // Owners use session context; managers/cashiers use their assigned store.
    public function currentBusiness(): ?Business
    {
        if ($this->canActAsOwner()) {
            return $this->activeBusiness()
                ?? $this->organization?->businesses()->first();
        }

        return $this->activeBusiness()
            ?? $this->business();
    }
}
