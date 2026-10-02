@extends('admin.layouts.app')
@section('title', 'Subscriptions')
@section('subtitle', 'All subscription payments to the platform')

@push('styles')
<style>
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
        <h1 class="admin-page-title">Subscriptions</h1>
        <p class="admin-page-subtitle">All subscription payments to the platform</p>
    </div>
</div>

{{-- ── Revenue Summary ── --}}
<div class="admin-stats admin-stats-5">
    <div class="admin-stat-card admin-stat-indigo">
        <div class="admin-stat-label">Total Revenue</div>
        <div class="admin-stat-value">KES {{ number_format($totalRevenue, 2) }}</div>
        <div class="admin-stat-sub">All-time, active subscriptions</div>
    </div>
    <div class="admin-stat-card admin-stat-emerald">
        <div class="admin-stat-label">This Month</div>
        <div class="admin-stat-value">KES {{ number_format($monthRevenue, 2) }}</div>
        <div class="admin-stat-sub">{{ now()->format('F Y') }}</div>
    </div>
    <div class="admin-stat-card admin-stat-emerald">
        <div class="admin-stat-label">Active</div>
        <div class="admin-stat-value">{{ number_format($activeCount) }}</div>
    </div>
    <div class="admin-stat-card admin-stat-red">
        <div class="admin-stat-label">Expired</div>
        <div class="admin-stat-value">{{ number_format($expiredCount) }}</div>
    </div>
    <div class="admin-stat-card admin-stat-violet">
        <div class="admin-stat-label">Total Ever</div>
        <div class="admin-stat-value">{{ number_format($totalCount) }}</div>
    </div>
</div>

{{-- ── Plan distribution — dynamic, whatever plans actually exist ── --}}
<div class="admin-panel" style="margin-bottom: 20px;">
    <div class="admin-panel-header">
        <h3 class="admin-panel-title">Active Plan Distribution</h3>
    </div>
    <div class="admin-plan-list">
        @forelse($planTotals as $row)
        <div class="admin-plan-row">
            <span class="admin-badge {{ 'plan-' . $row->plan }}">{{ ucfirst($row->plan) }}</span>
            <span style="color: var(--color-text-secondary); font-size:12.5px;">{{ $row->count }} subscription(s)</span>
            <span class="admin-plan-count">KES {{ number_format($row->total, 2) }}</span>
        </div>
        @empty
        <p style="font-size:13px; text-align:center; padding:20px 0; color: var(--color-text-secondary);">
            No active subscriptions yet.
        </p>
        @endforelse
    </div>
</div>

{{-- ── Quick filter chips ── --}}
<div class="admin-chip-row">
    <a href="{{ route('admin.subscriptions.index', array_filter(request()->except('status'))) }}"
       class="admin-chip {{ ! request('status') ? 'active' : '' }}">All</a>
    <a href="{{ route('admin.subscriptions.index', array_merge(request()->except('status'), ['status' => 'active'])) }}"
       class="admin-chip {{ request('status') === 'active' ? 'active' : '' }}">
        Active <span class="count">{{ $activeCount }}</span>
    </a>
    <a href="{{ route('admin.subscriptions.index', array_merge(request()->except('status'), ['status' => 'expired'])) }}"
       class="admin-chip {{ request('status') === 'expired' ? 'active' : '' }}">
        Expired <span class="count">{{ $expiredCount }}</span>
    </a>
</div>

{{-- ── Filter bar ── --}}
<form method="GET" class="admin-filter-bar">
    <input type="text" name="search" class="admin-search-input"
        placeholder="Search by organization name or payment reference…"
        value="{{ request('search') }}">
    <select name="plan" class="admin-filter-select" onchange="this.form.submit()">
        <option value="">All Plans</option>
        <option value="solo"       {{ request('plan') === 'solo'       ? 'selected' : '' }}>Solo</option>
        <option value="growth"     {{ request('plan') === 'growth'     ? 'selected' : '' }}>Growth</option>
        <option value="enterprise" {{ request('plan') === 'enterprise' ? 'selected' : '' }}>Enterprise</option>
        <option value="starter"    {{ request('plan') === 'starter'    ? 'selected' : '' }}>Starter (legacy)</option>
        <option value="business"   {{ request('plan') === 'business'   ? 'selected' : '' }}>Business (legacy)</option>
    </select>
    <select name="status" class="admin-filter-select" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        <option value="active"  {{ request('status') === 'active'  ? 'selected' : '' }}>Active</option>
        <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>Expired</option>
    </select>
    <select name="channel" class="admin-filter-select" onchange="this.form.submit()">
        <option value="">Any Payment Channel</option>
        <option value="mpesa"       {{ request('channel') === 'mpesa'       ? 'selected' : '' }}>M-Pesa</option>
        <option value="paystack"    {{ request('channel') === 'paystack'    ? 'selected' : '' }}>Paystack</option>
        <option value="admin_grant" {{ request('channel') === 'admin_grant' ? 'selected' : '' }}>Admin Grant</option>
    </select>
    <select name="sort" class="admin-filter-select" onchange="this.form.submit()">
        <option value=""             {{ ! request('sort') || request('sort') === 'newest' ? 'selected' : '' }}>Newest first</option>
        <option value="oldest"       {{ request('sort') === 'oldest'       ? 'selected' : '' }}>Oldest first</option>
        <option value="amount_desc"  {{ request('sort') === 'amount_desc'  ? 'selected' : '' }}>Highest amount</option>
    </select>
    <button type="submit" class="admin-btn admin-btn-primary">
        <i class="ph-bold ph-magnifying-glass"></i> Search
    </button>
    @if(request()->hasAny(['search','plan','status','channel','sort']))
        <a href="{{ route('admin.subscriptions.index') }}" class="admin-btn admin-btn-gray">Clear</a>
    @endif
</form>

<div class="admin-panel">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Organization</th>
                    <th>Plan</th>
                    <th>Amount</th>
                    <th>Channel</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Reference</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($subscriptions as $sub)
                <tr>
                    <td style="color: var(--color-text-secondary); font-size:12px;">{{ $sub->id }}</td>
                    <td>
                        <a href="{{ route('admin.organizations.show', $sub->organization_id) }}"
                           style="font-weight:600; color: var(--color-primary); text-decoration:none;">
                            {{ $sub->organization?->name ?? 'Unknown' }}
                        </a>
                    </td>
                    <td><span class="admin-badge {{ 'plan-' . $sub->plan }}">{{ ucfirst($sub->plan) }}</span></td>
                    <td style="font-weight:600;">KES {{ number_format($sub->amount, 2) }}</td>
                    <td>
                        @if($sub->payment_channel === 'mpesa')
                            <span class="admin-badge admin-badge-green"><i class="ph-bold ph-device-mobile"></i> M-Pesa</span>
                        @elseif($sub->payment_channel === 'paystack')
                            <span class="admin-badge admin-badge-indigo"><i class="ph-bold ph-credit-card"></i> Paystack</span>
                        @elseif($sub->payment_channel === 'admin_grant')
                            <span class="admin-badge admin-badge-gray"><i class="ph-bold ph-gift"></i> Admin Grant</span>
                        @else
                            <span class="admin-badge admin-badge-gray">{{ ucfirst($sub->payment_channel ?? 'Unknown') }}</span>
                        @endif
                    </td>
                    <td style="color: var(--color-text-secondary); font-size:12px; white-space:nowrap;">{{ $sub->start_date->format('d M Y') }}</td>
                    <td style="color: var(--color-text-secondary); font-size:12px; white-space:nowrap;">{{ $sub->end_date->format('d M Y') }}</td>
                    <td style="font-size:11px; font-family:monospace; color: var(--color-text-secondary);">
                        {{ $sub->payment_reference ?? '—' }}
                    </td>
                    <td>
                        @if($sub->isActive())
                            <span class="admin-badge admin-badge-green">Active</span>
                        @elseif($sub->status === 'cancelled')
                            {{-- isActive() only ever returns true/false, so a
                                 cancelled subscription used to render
                                 identically to one that simply ran its course
                                 — no way to tell the two apart from this list. --}}
                            <span class="admin-badge admin-badge-red">Cancelled</span>
                        @else
                            <span class="admin-badge admin-badge-gray">Expired</span>
                        @endif
                    </td>
                    <td>
                        @if($sub->isActive() && $sub->organization)
                        <form method="POST" action="{{ route('admin.organizations.subscriptions.cancel', [$sub->organization, $sub]) }}" style="display:contents">
                            @csrf @method('PATCH')
                            <button type="submit" class="admin-btn admin-btn-red admin-btn-sm"
                                onclick="return confirm('Cancel this subscription? The organization will revert to the Free plan on its next visit.')">
                                <i class="ph-bold ph-x-circle"></i> Cancel
                            </button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" style="text-align:center; padding:40px; color: var(--color-text-secondary);">
                        No subscriptions found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($subscriptions->hasPages())
    <div class="admin-pagination">
        {{ $subscriptions->links() }}
    </div>
    @endif
</div>

@endsection
