@extends('admin.layouts.app')
@section('title', 'Organizations')
@section('subtitle', 'All registered organizations (shop owners) on the platform')

@push('styles')
<style>
/* Org name + avatar pairing in the table — kept local since nothing else
   in admin.css needs a circular initial badge yet. */
.admin-org-cell { display: flex; align-items: center; gap: 10px; }
.admin-org-avatar {
    flex-shrink: 0;
    width: 32px; height: 32px;
    border-radius: 50%;
    background: var(--admin-accent-grad);
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700;
}
.admin-org-name { font-weight: 600; color: inherit; text-decoration: none; }
.admin-org-name:hover { text-decoration: underline; }

/* Quick-filter chips row under the search bar */
.admin-chip-row { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
.admin-chip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 13px; border-radius: 999px;
    font-size: 12.5px; font-weight: 600;
    background: #fff; border: 1px solid #d0d0d0; color: #444;
    text-decoration: none; transition: border-color .12s, color .12s;
}
.admin-chip:hover { border-color: var(--admin-accent); color: var(--admin-accent); }
.admin-chip.active { background: var(--admin-accent-grad); border-color: transparent; color: #fff; }
.admin-chip .count { opacity: .75; font-weight: 700; }
</style>
@endpush

@section('content')

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Organizations</h1>
        <p class="admin-page-subtitle">All registered organizations (shop owners) on the platform</p>
    </div>
</div>

{{-- ── Platform KPI strip ── --}}
<div class="admin-stats">
    <div class="admin-stat-card">
        <div class="admin-stat-label">Total Orgs</div>
        <div class="admin-stat-value">{{ number_format($stats['total']) }}</div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label">Active</div>
        <div class="admin-stat-value" style="color:#15803d;">{{ number_format($stats['active']) }}</div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label">On Trial</div>
        <div class="admin-stat-value" style="color:#a16207;">{{ number_format($stats['trial']) }}</div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label">Trial Ending Soon</div>
        <div class="admin-stat-value" style="color:{{ $stats['expiring_soon'] > 0 ? '#b91c1c' : 'inherit' }};">{{ number_format($stats['expiring_soon']) }}</div>
        <div class="admin-stat-sub">next 7 days</div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label">Suspended</div>
        <div class="admin-stat-value" style="color:{{ $stats['suspended'] > 0 ? '#b91c1c' : 'inherit' }};">{{ number_format($stats['suspended']) }}</div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label">Total Stores</div>
        <div class="admin-stat-value">{{ number_format($stats['stores']) }}</div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label">Est. MRR</div>
        <div class="admin-stat-value">KSh {{ number_format($stats['mrr'], 0) }}</div>
        <div class="admin-stat-sub">{{ number_format($stats['users']) }} users total</div>
    </div>
</div>

{{-- ── Quick filter chips ── --}}
<div class="admin-chip-row">
    <a href="{{ route('admin.organizations.index', array_filter(request()->except('status'))) }}"
       class="admin-chip {{ ! request('status') ? 'active' : '' }}">All</a>
    <a href="{{ route('admin.organizations.index', array_merge(request()->except('status'), ['status' => 'active'])) }}"
       class="admin-chip {{ request('status') === 'active' ? 'active' : '' }}">Active</a>
    <a href="{{ route('admin.organizations.index', array_merge(request()->except('status'), ['status' => 'trial'])) }}"
       class="admin-chip {{ request('status') === 'trial' ? 'active' : '' }}">On Trial</a>
    <a href="{{ route('admin.organizations.index', array_merge(request()->except('status'), ['status' => 'expiring_soon'])) }}"
       class="admin-chip {{ request('status') === 'expiring_soon' ? 'active' : '' }}">
        Trial Ending Soon <span class="count">{{ $stats['expiring_soon'] }}</span>
    </a>
    <a href="{{ route('admin.organizations.index', array_merge(request()->except('status'), ['status' => 'expired'])) }}"
       class="admin-chip {{ request('status') === 'expired' ? 'active' : '' }}">Expired</a>
    <a href="{{ route('admin.organizations.index', array_merge(request()->except('status'), ['status' => 'suspended'])) }}"
       class="admin-chip {{ request('status') === 'suspended' ? 'active' : '' }}">
        Suspended <span class="count">{{ $stats['suspended'] }}</span>
    </a>
</div>

<form method="GET" class="admin-filter-bar">
    <input
        type="text"
        name="search"
        class="admin-search-input"
        placeholder="Search by name, owner email…"
        value="{{ request('search') }}"
    >
    <select name="status" class="admin-filter-select" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        <option value="active"        {{ request('status') === 'active'        ? 'selected' : '' }}>Active</option>
        <option value="trial"         {{ request('status') === 'trial'         ? 'selected' : '' }}>On Trial</option>
        <option value="expiring_soon" {{ request('status') === 'expiring_soon' ? 'selected' : '' }}>Trial Ending Soon</option>
        <option value="expired"       {{ request('status') === 'expired'       ? 'selected' : '' }}>Expired</option>
        <option value="suspended"     {{ request('status') === 'suspended'     ? 'selected' : '' }}>Suspended</option>
    </select>
    <select name="plan" class="admin-filter-select" onchange="this.form.submit()">
        <option value="">All Plans</option>
        <option value="solo"       {{ request('plan') === 'solo'       ? 'selected' : '' }}>Solo</option>
        <option value="growth"     {{ request('plan') === 'growth'     ? 'selected' : '' }}>Growth</option>
        <option value="enterprise" {{ request('plan') === 'enterprise' ? 'selected' : '' }}>Enterprise</option>
    </select>
    <select name="sort" class="admin-filter-select" onchange="this.form.submit()">
        <option value=""            {{ ! request('sort') || request('sort') === 'newest' ? 'selected' : '' }}>Newest first</option>
        <option value="oldest"      {{ request('sort') === 'oldest'      ? 'selected' : '' }}>Oldest first</option>
        <option value="stores_desc" {{ request('sort') === 'stores_desc' ? 'selected' : '' }}>Most stores</option>
        <option value="name"        {{ request('sort') === 'name'        ? 'selected' : '' }}>Name A–Z</option>
    </select>
    <button type="submit" class="admin-btn admin-btn-primary">
        <i class="ph-bold ph-magnifying-glass"></i> Search
    </button>
    @if(request()->hasAny(['search','status','plan','sort']))
        <a href="{{ route('admin.organizations.index') }}" class="admin-btn admin-btn-gray">Clear</a>
    @endif
</form>

<div class="admin-panel">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Organization</th>
                    <th>Owner</th>
                    <th>Stores</th>
                    <th>Plan</th>
                    <th>Est. MRR</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($organizations as $org)
                <tr>
                    <td>
                        <div class="admin-org-cell">
                            <div class="admin-org-avatar">{{ strtoupper(substr($org->name ?: '?', 0, 1)) }}</div>
                            <a href="{{ route('admin.organizations.show', $org) }}" class="admin-org-name">
                                {{ $org->name ?: 'Unnamed Organization' }}
                            </a>
                        </div>
                    </td>
                    <td>
                        <div>{{ $org->owner?->name ?? '—' }}</div>
                        <div style="font-size:12px; color: var(--color-text-secondary);">{{ $org->owner?->email }}</div>
                    </td>
                    <td style="text-align:center;">
                        <span class="admin-badge admin-badge-gray">{{ $org->businesses_count }}</span>
                    </td>
                    <td>
                        @if($org->subscription_plan)
                            <span class="admin-badge {{ 'plan-' . $org->subscription_plan }}">{{ ucfirst($org->subscription_plan) }}</span>
                        @else
                            <span class="admin-badge admin-badge-gray">None</span>
                        @endif
                    </td>
                    <td style="font-weight:600; white-space:nowrap;">
                        @if($org->activeSubscription)
                            KSh {{ number_format($org->estimatedMrr(), 0) }}
                        @else
                            <span style="color: var(--color-text-secondary); font-weight:400;">—</span>
                        @endif
                    </td>
                    <td>
                        @if($org->status === 'active')
                            <span class="admin-badge admin-badge-green">Active</span>
                        @elseif($org->status === 'trial')
                            @php $daysLeft = $org->trialDaysLeft(); @endphp
                            <span class="admin-badge {{ $daysLeft <= 3 ? 'admin-badge-red' : 'admin-badge-amber' }}">
                                Trial
                                @if($org->trial_ends_at)
                                    · {{ $org->trial_ends_at->diffForHumans() }}
                                @endif
                            </span>
                        @elseif($org->status === 'suspended')
                            <span class="admin-badge admin-badge-red">Suspended</span>
                        @else
                            <span class="admin-badge admin-badge-gray">{{ ucfirst($org->status) }}</span>
                        @endif
                    </td>
                    <td style="color: var(--color-text-secondary); font-size:12px; white-space:nowrap;">
                        {{ $org->created_at->format('d M Y') }}
                    </td>
                    <td>
                        <div style="display:flex; gap:6px; flex-wrap:wrap;">
                            <a href="{{ route('admin.organizations.show', $org) }}" class="admin-btn admin-btn-gray admin-btn-sm">
                                <i class="ph-bold ph-eye"></i> View
                            </a>
                            @if($org->status !== 'suspended')
                            <form method="POST" action="{{ route('admin.organizations.suspend', $org) }}" style="display:contents">
                                @csrf @method('PATCH')
                                <button type="submit" class="admin-btn admin-btn-red admin-btn-sm"
                                    onclick="return confirm('Suspend {{ addslashes($org->name) }}?')">
                                    <i class="ph-bold ph-pause-circle"></i> Suspend
                                </button>
                            </form>
                            @else
                            <form method="POST" action="{{ route('admin.organizations.activate', $org) }}" style="display:contents">
                                @csrf @method('PATCH')
                                <button type="submit" class="admin-btn admin-btn-green admin-btn-sm">
                                    <i class="ph-bold ph-play-circle"></i> Activate
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align:center; padding:40px; color: var(--color-text-secondary);">
                        No organizations found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($organizations->hasPages())
    <div class="admin-pagination">
        {{ $organizations->links() }}
    </div>
    @endif
</div>

@endsection
