@extends('admin.layouts.app')
@section('title', $business->name)
@section('subtitle', 'Business details and management')

@push('styles')
<style>
.admin-biz-hero {
    display: flex;
    align-items: flex-start;
    gap: 18px;
    padding: 22px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.admin-biz-hero-avatar {
    flex-shrink: 0;
    width: 60px; height: 60px;
    border-radius: 14px;
    background: var(--admin-accent-grad);
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; font-weight: 700;
}
.admin-biz-hero-info { flex: 1; min-width: 220px; }
.admin-biz-hero-name { font-size: 20px; font-weight: 700; color: #0a0a0a; margin: 0 0 4px; }
.admin-biz-hero-owner { font-size: 13px; color: #666; margin: 0 0 10px; }
.admin-biz-hero-badges { display: flex; gap: 8px; flex-wrap: wrap; }
.admin-biz-hero-actions { display: flex; gap: 8px; flex-wrap: wrap; flex-shrink: 0; }
</style>
@endpush

@section('content')

{{-- Back link --}}
<a href="{{ route('admin.businesses.index') }}" class="admin-btn admin-btn-gray admin-btn-sm" style="margin-bottom:16px; display:inline-flex;">
    <i class="ph-bold ph-arrow-left"></i> Back to Businesses
</a>

{{-- ── Hero ── --}}
<div class="admin-panel admin-biz-hero">
    <div class="admin-biz-hero-avatar">{{ strtoupper(substr($business->name ?: '?', 0, 1)) }}</div>
    <div class="admin-biz-hero-info">
        <h1 class="admin-biz-hero-name">{{ $business->name ?: 'Unnamed Business' }}</h1>
        <p class="admin-biz-hero-owner">
            @php $owner = $business->organization?->owner; @endphp
            @if($owner)
                Owned by <strong>{{ $owner->name }}</strong> · <a href="mailto:{{ $owner->email }}" style="color:inherit;">{{ $owner->email }}</a>
            @else
                {{ $business->email }}
            @endif
        </p>
        <div class="admin-biz-hero-badges">
            @php $orgStatus = $business->organization?->status ?? $business->status; @endphp
            {{-- This store's own suspension takes priority — see the same
                 note on admin/businesses/index.blade.php. --}}
            @if($business->status === 'suspended')
                <span class="admin-badge admin-badge-red"><i class="ph-bold ph-pause-circle"></i> This Store Suspended</span>
            @elseif($orgStatus === 'active')
                <span class="admin-badge admin-badge-green"><i class="ph-bold ph-check-circle"></i> Active</span>
            @elseif($orgStatus === 'trial')
                <span class="admin-badge admin-badge-amber"><i class="ph-bold ph-clock"></i> Trial</span>
            @elseif($orgStatus === 'suspended')
                <span class="admin-badge admin-badge-red"><i class="ph-bold ph-pause-circle"></i> Org Suspended</span>
            @endif
            @if($activeSubscription)
                <span class="admin-badge {{ 'plan-' . $activeSubscription->plan }}">{{ ucfirst($activeSubscription->plan) }} plan</span>
            @elseif($business->organization?->subscription_plan)
                {{-- A trial isn't a Subscription row, so $activeSubscription is
                     null for every trial org — without this, the badges here
                     showed "Trial" status with no plan at all, leaving an
                     admin unable to tell which plan the trial is even for. --}}
                <span class="admin-badge {{ 'plan-' . $business->organization->subscription_plan }}">{{ ucfirst($business->organization->subscription_plan) }} plan</span>
            @endif
            @if($business->hasMpesaConfigured())
                <span class="admin-badge admin-badge-green"><i class="ph-bold ph-device-mobile"></i> M-Pesa configured</span>
            @else
                <span class="admin-badge admin-badge-gray"><i class="ph-bold ph-device-mobile"></i> No M-Pesa</span>
            @endif
        </div>
    </div>
    <div class="admin-biz-hero-actions">
        @if($business->status !== 'suspended')
        <form method="POST" action="{{ route('admin.businesses.suspend', $business) }}" style="display:contents">
            @csrf @method('PATCH')
            <button type="submit" class="admin-btn admin-btn-red admin-btn-sm"
                onclick="return confirm('Suspend this store? Its staff will be logged out and blocked from it.')">
                <i class="ph-bold ph-pause-circle"></i> Suspend this store
            </button>
        </form>
        @else
        <form method="POST" action="{{ route('admin.businesses.activate', $business) }}" style="display:contents">
            @csrf @method('PATCH')
            <button type="submit" class="admin-btn admin-btn-green admin-btn-sm">
                <i class="ph-bold ph-play-circle"></i> Activate this store
            </button>
        </form>
        @endif
        @if($business->organization)
        <a href="{{ route('admin.organizations.show', $business->organization) }}" class="admin-btn admin-btn-gray">
            <i class="ph-bold ph-buildings"></i> Manage organization
        </a>
        @endif
    </div>
</div>

{{-- ── KPI strip ── --}}
<div class="admin-stats admin-stats-5">
    <div class="admin-stat-card">
        <div class="admin-stat-label">Team Members</div>
        <div class="admin-stat-value">{{ $business->users->count() }}</div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label">Lifetime Sales</div>
        <div class="admin-stat-value">KSh {{ number_format($lifetimeSales, 0) }}</div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label">This Month</div>
        <div class="admin-stat-value">KSh {{ number_format(end($salesTrend), 0) }}</div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label">M-Pesa</div>
        <div class="admin-stat-value" style="font-size:16px; color:{{ $business->hasMpesaConfigured() ? '#15803d' : '#a16207' }};">
            {{ $business->hasMpesaConfigured() ? 'Configured' : 'Not set up' }}
        </div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label">Registered</div>
        <div class="admin-stat-value" style="font-size:16px;">{{ $business->created_at->format('d M Y') }}</div>
        <div class="admin-stat-sub">{{ $business->created_at->diffForHumans() }}</div>
    </div>
</div>

{{-- ── Sales trend for this store ── --}}
<div class="admin-panel" style="margin-bottom: 20px;">
    <div class="admin-panel-header">
        <h3 class="admin-panel-title">Sales — this store, last 6 months</h3>
    </div>
    <div class="admin-chart-wrap" style="height:220px;">
        <canvas id="bizSalesChart"></canvas>
    </div>
</div>

{{-- ── Details + Actions grid ── --}}
<div class="admin-detail-grid" style="margin-bottom:20px;">

    {{-- Business Info --}}
    <div class="admin-panel" style="padding:20px;">
        <h3 class="admin-panel-title" style="margin-bottom:16px;">Business Info</h3>
        <div style="display:flex; flex-direction:column; gap:14px;">
            <div class="admin-detail-row">
                <span class="admin-detail-label">Email</span>
                <span class="admin-detail-value">{{ $business->email }}</span>
            </div>
            <div class="admin-detail-row">
                <span class="admin-detail-label">Phone</span>
                <span class="admin-detail-value">{{ $business->phone ?? '—' }}</span>
            </div>
            <div class="admin-detail-row">
                <span class="admin-detail-label">Industry</span>
                <span class="admin-detail-value">{{ $business->industry ?? '—' }}</span>
            </div>
            <div class="admin-detail-row">
                <span class="admin-detail-label">Location</span>
                <span class="admin-detail-value">{{ $business->city ?? '' }}{{ $business->address ? ', ' . $business->address : '' }}</span>
            </div>
            <div class="admin-detail-row">
                <span class="admin-detail-label">Trial Ends</span>
                <span class="admin-detail-value">{{ $business->organization?->trial_ends_at ? $business->organization->trial_ends_at->format('d M Y') : '—' }}</span>
            </div>
            <div class="admin-detail-row">
                <span class="admin-detail-label">Registered</span>
                <span class="admin-detail-value">{{ $business->created_at->format('d M Y, H:i') }}</span>
            </div>
        </div>
    </div>

    {{-- Subscription is managed at the organization level --}}
    <div class="admin-panel" style="padding:20px;">
        <h3 class="admin-panel-title" style="margin-bottom:8px;"><i class="ph-bold ph-buildings"></i> Subscription</h3>
        <p style="font-size:13px; color: var(--color-text-secondary); margin:0 0 12px;">
            Trials and subscriptions are managed for the whole organization — they cover every store at once.
        </p>
        @if($business->organization)
        <a href="{{ route('admin.organizations.show', $business->organization) }}" class="admin-btn admin-btn-primary admin-btn-sm">
            <i class="ph-bold ph-arrow-right"></i> Manage {{ $business->organization->name }}
        </a>
        @endif
    </div>
</div>

{{-- ── Team Members ── --}}
<div class="admin-panel" style="margin-bottom:20px;">
    <div class="admin-panel-header">
        <h3 class="admin-panel-title">Team Members ({{ $business->users->count() }})</h3>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($business->users as $member)
                <tr>
                    <td style="font-weight:600;">{{ $member->name }}</td>
                    <td style="color: var(--color-text-secondary);">{{ $member->email }}</td>
                    <td><span class="admin-badge admin-badge-indigo">{{ ucfirst($member->role) }}</span></td>
                    <td>
                        @if($member->is_active)
                            <span class="admin-badge admin-badge-green">Active</span>
                        @else
                            {{-- Now actually enforced at login and mid-session
                                 (LoginController/CheckSubscription) — this used
                                 to just be a label with no real effect. --}}
                            <span class="admin-badge admin-badge-red">Deactivated</span>
                        @endif
                    </td>
                    <td style="color: var(--color-text-secondary); font-size:12px;">
                        {{ $member->last_login_at ? $member->last_login_at->diffForHumans() : 'Never' }}
                    </td>
                    <td>
                        @if($member->isOwner())
                            <span style="font-size:11.5px; color: var(--color-text-secondary);">Owner account</span>
                        @else
                        <form method="POST" action="{{ route('admin.businesses.members.toggle', [$business, $member]) }}" style="display:contents">
                            @csrf @method('PATCH')
                            @if($member->is_active)
                            <button type="submit" class="admin-btn admin-btn-red admin-btn-sm"
                                onclick="return confirm('Deactivate {{ addslashes($member->name) }}? They will be logged out and blocked from logging back in.')">
                                <i class="ph-bold ph-user-minus"></i> Deactivate
                            </button>
                            @else
                            <button type="submit" class="admin-btn admin-btn-green admin-btn-sm">
                                <i class="ph-bold ph-user-plus"></i> Activate
                            </button>
                            @endif
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center; padding:24px; color: var(--color-text-secondary);">No team members found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ── Subscription History ── --}}
<div class="admin-panel">
    <div class="admin-panel-header">
        <h3 class="admin-panel-title">Subscription History</h3>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Plan</th>
                    <th>Amount</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Reference</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($business->organization?->subscriptions ?? [] as $sub)
                <tr>
                    <td><span class="admin-badge {{ 'plan-' . $sub->plan }}">{{ ucfirst($sub->plan) }}</span></td>
                    <td style="font-weight:600;">KES {{ number_format($sub->amount, 2) }}</td>
                    <td style="color: var(--color-text-secondary); font-size:12px;">{{ $sub->start_date->format('d M Y') }}</td>
                    <td style="color: var(--color-text-secondary); font-size:12px;">{{ $sub->end_date->format('d M Y') }}</td>
                    <td style="font-size:11px; font-family:monospace; color: var(--color-text-secondary);">{{ $sub->payment_reference ?? '—' }}</td>
                    <td>
                        @if($sub->isActive())
                            <span class="admin-badge admin-badge-green">Active</span>
                        @else
                            <span class="admin-badge admin-badge-gray">Expired</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center; padding:24px; color: var(--color-text-secondary);">No subscriptions yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('js/chart.umd.min.js') }}?v={{ @filemtime(public_path('js/chart.umd.min.js')) ?: '1' }}"></script>
<script>
(function () {
    var ctx = document.getElementById('bizSalesChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: @json($salesTrendLabels),
                datasets: [{
                    label: 'Sales (KSh)',
                    data: @json($salesTrend),
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99,102,241,0.08)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                    pointBackgroundColor: '#6366f1',
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: function (v) { return 'KSh ' + v.toLocaleString(); } },
                        grid: { color: '#f0f0f0' },
                    },
                    x: { grid: { display: false } },
                },
            },
        });
    }
}());
</script>
@endpush
