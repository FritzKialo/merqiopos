@extends('admin.layouts.app')
@section('title', 'Businesses')
@section('subtitle', 'All registered shop owners on the platform')

@push('styles')
<style>
.admin-biz-cell { display: flex; align-items: center; gap: 10px; }
.admin-biz-avatar {
    flex-shrink: 0;
    width: 32px; height: 32px;
    border-radius: 8px;
    background: var(--admin-accent-grad);
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700;
}
.admin-biz-name { font-weight: 600; color: inherit; text-decoration: none; }
.admin-biz-name:hover { text-decoration: underline; }

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
        <h1 class="admin-page-title">Businesses</h1>
        <p class="admin-page-subtitle">All registered shop owners on the platform</p>
    </div>
</div>

{{-- ── Platform KPI strip ── --}}
<div class="admin-stats">
    <div class="admin-stat-card">
        <div class="admin-stat-label">Total Stores</div>
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
        <div class="admin-stat-label">Suspended</div>
        <div class="admin-stat-value" style="color:{{ $stats['suspended'] > 0 ? '#b91c1c' : 'inherit' }};">{{ number_format($stats['suspended']) }}</div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label">M-Pesa Configured</div>
        <div class="admin-stat-value" style="color:#15803d;">{{ number_format($stats['mpesa_on']) }}</div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label">M-Pesa Not Set Up</div>
        <div class="admin-stat-value" style="color:{{ $stats['mpesa_off'] > 0 ? '#a16207' : 'inherit' }};">{{ number_format($stats['mpesa_off']) }}</div>
        <div class="admin-stat-sub">support/upsell opportunity</div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label">Total Staff</div>
        <div class="admin-stat-value">{{ number_format($stats['total_staff']) }}</div>
    </div>
</div>

{{-- ── Quick filter chips ── --}}
<div class="admin-chip-row">
    <a href="{{ route('admin.businesses.index', array_filter(request()->except('status'))) }}"
       class="admin-chip {{ ! request('status') ? 'active' : '' }}">All</a>
    <a href="{{ route('admin.businesses.index', array_merge(request()->except('status'), ['status' => 'active'])) }}"
       class="admin-chip {{ request('status') === 'active' ? 'active' : '' }}">Active</a>
    <a href="{{ route('admin.businesses.index', array_merge(request()->except('status'), ['status' => 'trial'])) }}"
       class="admin-chip {{ request('status') === 'trial' ? 'active' : '' }}">On Trial</a>
    <a href="{{ route('admin.businesses.index', array_merge(request()->except('status'), ['status' => 'expired'])) }}"
       class="admin-chip {{ request('status') === 'expired' ? 'active' : '' }}">Expired</a>
    <a href="{{ route('admin.businesses.index', array_merge(request()->except('status'), ['status' => 'suspended'])) }}"
       class="admin-chip {{ request('status') === 'suspended' ? 'active' : '' }}">
        Suspended <span class="count">{{ $stats['suspended'] }}</span>
    </a>
    <a href="{{ route('admin.businesses.index', array_merge(request()->except('mpesa'), ['mpesa' => 'not_configured'])) }}"
       class="admin-chip {{ request('mpesa') === 'not_configured' ? 'active' : '' }}">
        No M-Pesa <span class="count">{{ $stats['mpesa_off'] }}</span>
    </a>
</div>

<form method="GET" class="admin-filter-bar">
    <input
        type="text"
        name="search"
        class="admin-search-input"
        placeholder="Search by name, email or city…"
        value="{{ request('search') }}"
    >
    <select name="status" class="admin-filter-select" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        <option value="active"    {{ request('status') === 'active'    ? 'selected' : '' }}>Active</option>
        <option value="trial"     {{ request('status') === 'trial'     ? 'selected' : '' }}>On Trial</option>
        <option value="expired"   {{ request('status') === 'expired'   ? 'selected' : '' }}>Expired</option>
        <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
    </select>
    <select name="mpesa" class="admin-filter-select" onchange="this.form.submit()">
        <option value="">M-Pesa: Any</option>
        <option value="configured"     {{ request('mpesa') === 'configured'     ? 'selected' : '' }}>Configured</option>
        <option value="not_configured" {{ request('mpesa') === 'not_configured' ? 'selected' : '' }}>Not configured</option>
    </select>
    <select name="sort" class="admin-filter-select" onchange="this.form.submit()">
        <option value=""       {{ ! request('sort') || request('sort') === 'newest' ? 'selected' : '' }}>Newest first</option>
        <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>Oldest first</option>
        <option value="name"   {{ request('sort') === 'name'   ? 'selected' : '' }}>Name A–Z</option>
    </select>
    <button type="submit" class="admin-btn admin-btn-primary">
        <i class="ph-bold ph-magnifying-glass"></i> Search
    </button>
    @if(request()->hasAny(['search','status','mpesa','sort']))
        <a href="{{ route('admin.businesses.index') }}" class="admin-btn admin-btn-gray">Clear</a>
    @endif
</form>

<div class="admin-panel">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Business</th>
                    <th>Owner</th>
                    <th>Industry</th>
                    <th>M-Pesa</th>
                    <th>Status</th>
                    <th>Subscription</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($businesses as $biz)
                <tr>
                    <td>
                        <div class="admin-biz-cell">
                            <div class="admin-biz-avatar">{{ strtoupper(substr($biz->name ?: '?', 0, 1)) }}</div>
                            <div>
                                <a href="{{ route('admin.businesses.show', $biz) }}" class="admin-biz-name">
                                    {{ $biz->name ?: 'Unnamed Business' }}
                                </a>
                                <div style="font-size:12px; color: var(--color-text-secondary);">{{ $biz->city }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        @php $owner = $biz->organization?->owner; @endphp
                        <div>{{ $owner?->name ?? '—' }}</div>
                        <div style="font-size:12px; color: var(--color-text-secondary);">{{ $owner?->email ?? $biz->email }}</div>
                    </td>
                    <td style="color: var(--color-text-secondary); font-size:13px;">{{ $biz->industry ?? '—' }}</td>
                    <td>
                        @if($biz->hasMpesaConfigured())
                            <span class="admin-badge admin-badge-green"><i class="ph-bold ph-check-circle"></i> Set up</span>
                        @else
                            <span class="admin-badge admin-badge-gray"><i class="ph-bold ph-minus-circle"></i> None</span>
                        @endif
                    </td>
                    <td>
                        @php $orgStatus = $biz->organization?->status ?? $biz->status; @endphp
                        {{-- This store's own suspension takes priority over the
                             org's status — the org can be perfectly active while
                             this one store is individually suspended, and that
                             now actually blocks its staff from logging in (see
                             CheckSubscription), so it must never be masked by a
                             more permissive org-level badge. --}}
                        @if($biz->status === 'suspended')
                            <span class="admin-badge admin-badge-red"><i class="ph-bold ph-pause-circle"></i> Store Suspended</span>
                        @elseif($orgStatus === 'active')
                            <span class="admin-badge admin-badge-green">Active</span>
                        @elseif($orgStatus === 'trial')
                            <span class="admin-badge admin-badge-amber">
                                Trial
                                @if($biz->organization?->trial_ends_at)
                                    · {{ $biz->organization->trial_ends_at->diffForHumans() }}
                                @endif
                            </span>
                        @elseif($orgStatus === 'suspended')
                            <span class="admin-badge admin-badge-red">Org Suspended</span>
                        @else
                            <span class="admin-badge admin-badge-gray">{{ ucfirst($orgStatus) }}</span>
                        @endif
                    </td>
                    <td>
                        @php $orgSubs = $biz->organization?->subscriptions ?? collect(); @endphp
                        @if($orgSubs->isNotEmpty())
                            @php $active = $orgSubs->first(); @endphp
                            <span class="admin-badge {{ 'plan-' . $active->plan }}">{{ ucfirst($active->plan) }}</span>
                            <div style="font-size:11px; color: var(--color-text-secondary); margin-top:3px;">
                                Exp: {{ $active->end_date->format('d M Y') }}
                            </div>
                        @else
                            <span class="admin-badge admin-badge-gray">None</span>
                        @endif
                    </td>
                    <td style="color: var(--color-text-secondary); font-size:12px; white-space:nowrap;">
                        {{ $biz->created_at->format('d M Y') }}
                    </td>
                    <td>
                        <div style="display:flex; gap:6px; flex-wrap:wrap;">
                            <a href="{{ route('admin.businesses.show', $biz) }}" class="admin-btn admin-btn-gray admin-btn-sm">
                                <i class="ph-bold ph-eye"></i> View
                            </a>
                            @if($biz->status !== 'suspended')
                            <form method="POST" action="{{ route('admin.businesses.suspend', $biz) }}" style="display:contents">
                                @csrf @method('PATCH')
                                <button type="submit" class="admin-btn admin-btn-red admin-btn-sm"
                                    onclick="return confirm('Suspend {{ addslashes($biz->name) }}? Its staff will be logged out and blocked from this store.')">
                                    <i class="ph-bold ph-pause-circle"></i> Suspend
                                </button>
                            </form>
                            @else
                            <form method="POST" action="{{ route('admin.businesses.activate', $biz) }}" style="display:contents">
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
                        No businesses found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($businesses->hasPages())
    <div class="admin-pagination">
        {{ $businesses->links() }}
    </div>
    @endif
</div>

@endsection
