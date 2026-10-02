@extends('admin.layouts.app')
@section('title', 'Dashboard')

@section('content')

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Dashboard</h1>
        <p class="admin-page-subtitle">{{ now()->format('l, d F Y') }}</p>
    </div>
</div>

{{-- ── Stat Cards ── --}}
<div class="admin-stats">
    <div class="admin-stat-card admin-stat-indigo">
        <div class="admin-stat-label">Organizations</div>
        <div class="admin-stat-value">{{ number_format($totalOrgs) }}</div>
        <div class="admin-stat-sub">{{ $totalStores }} stores · +{{ $newOrgsThisWeek }} this week</div>
    </div>
    <div class="admin-stat-card admin-stat-emerald">
        <div class="admin-stat-label">Active</div>
        <div class="admin-stat-value">{{ number_format($activeOrgs) }}</div>
        <div class="admin-stat-sub">Paying subscribers</div>
    </div>
    <div class="admin-stat-card admin-stat-amber">
        <div class="admin-stat-label">On Trial</div>
        <div class="admin-stat-value">{{ number_format($trialOrgs) }}</div>
        <div class="admin-stat-sub">Trial window active</div>
    </div>
    <div class="admin-stat-card admin-stat-violet">
        <div class="admin-stat-label">Free Plan</div>
        <div class="admin-stat-value">{{ number_format($freeOrgs) }}</div>
        <div class="admin-stat-sub">Not yet converted</div>
    </div>
    <div class="admin-stat-card admin-stat-rose">
        <div class="admin-stat-label">Expired</div>
        <div class="admin-stat-value">{{ number_format($expiredOrgs) }}</div>
        <div class="admin-stat-sub">Need renewal</div>
    </div>
    <div class="admin-stat-card admin-stat-emerald">
        <div class="admin-stat-label">Month Revenue</div>
        <div class="admin-stat-value">KES {{ number_format($monthRevenue, 2) }}</div>
        <div class="admin-stat-sub">{{ now()->format('F Y') }}</div>
    </div>
    <div class="admin-stat-card admin-stat-indigo">
        <div class="admin-stat-label">Total Revenue</div>
        <div class="admin-stat-value">KES {{ number_format($totalRevenue, 2) }}</div>
        <div class="admin-stat-sub">All-time subscriptions</div>
    </div>
    {{-- Platform usage — distinct from the subscription-billing cards
         above: this is the real value moving through every store's till,
         regardless of what plan they're on. --}}
    <div class="admin-stat-card admin-stat-emerald">
        <div class="admin-stat-label">Platform GMV — Month</div>
        <div class="admin-stat-value">KES {{ number_format($platformGmvMonth, 0) }}</div>
        <div class="admin-stat-sub">all stores, all plans</div>
    </div>
    <div class="admin-stat-card admin-stat-violet">
        <div class="admin-stat-label">Platform GMV — All-Time</div>
        <div class="admin-stat-value">KES {{ number_format($platformGmvAllTime, 0) }}</div>
        <div class="admin-stat-sub">real trading activity</div>
    </div>
</div>

{{-- ── Needs Attention ── --}}
@php $needsAttention = $trialsEndingSoon->isNotEmpty() || $mpesaNotConfiguredCount > 0 || $suspendedOrgsCount > 0; @endphp
<div class="admin-panel" style="margin-bottom: 16px;">
    <div class="admin-panel-header">
        <h3 class="admin-panel-title">
            @if($needsAttention)
                <i class="ph-bold ph-warning-circle" style="color:#a16207;"></i> Needs Attention
            @else
                <i class="ph-bold ph-check-circle" style="color:#15803d;"></i> Needs Attention
            @endif
        </h3>
    </div>
    @if(! $needsAttention)
    <div style="padding:24px; text-align:center; color: var(--color-text-secondary); font-size:13.5px;">
        All clear — no trials ending imminently, no suspended organizations, and every store has M-Pesa configured.
    </div>
    @else
    <div style="padding:8px 18px 14px; display:flex; flex-direction:column; gap:0;">
        @foreach($trialsEndingSoon as $org)
        <div class="admin-plan-row">
            <span>
                <i class="ph-bold ph-clock" style="color:#a16207;"></i>
                <a href="{{ route('admin.organizations.show', $org) }}" style="color:inherit; text-decoration:none; font-weight:600;">{{ $org->name }}</a>
                <span style="color: var(--color-text-secondary); font-size:12.5px;">— trial ends {{ $org->trial_ends_at->diffForHumans() }}</span>
            </span>
            <a href="{{ route('admin.organizations.show', $org) }}" class="admin-btn admin-btn-gray admin-btn-sm">Review</a>
        </div>
        @endforeach

        @if($suspendedOrgsCount > 0)
        <div class="admin-plan-row">
            <span>
                <i class="ph-bold ph-pause-circle" style="color:#b91c1c;"></i>
                {{ $suspendedOrgsCount }} organization(s) currently suspended
            </span>
            <a href="{{ route('admin.organizations.index', ['status' => 'suspended']) }}" class="admin-btn admin-btn-gray admin-btn-sm">View</a>
        </div>
        @endif

        @if($mpesaNotConfiguredCount > 0)
        <div class="admin-plan-row">
            <span>
                <i class="ph-bold ph-device-mobile" style="color:#a16207;"></i>
                {{ $mpesaNotConfiguredCount }} store(s) without M-Pesa configured
            </span>
            <a href="{{ route('admin.businesses.index', ['mpesa' => 'not_configured']) }}" class="admin-btn admin-btn-gray admin-btn-sm">View</a>
        </div>
        @endif
    </div>
    @endif
</div>

{{-- ── Charts Row ── --}}
<div class="admin-grid-chart">

    <div class="admin-panel">
        <div class="admin-panel-header">
            <h3 class="admin-panel-title">Subscription revenue — last 6 months</h3>
        </div>
        <div class="admin-chart-wrap">
            @if($totalRevenue > 0)
            <canvas id="revenueChart"></canvas>
            @else
            <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:240px;text-align:center;gap:8px;color:var(--color-text-secondary);">
                <i class="ph-bold ph-chart-line" style="font-size:34px;opacity:.35;"></i>
                <div style="font-weight:600;color:var(--color-text);">No paid subscriptions yet</div>
                <div style="font-size:13px;">Revenue will appear here once organizations start paying.</div>
            </div>
            @endif
        </div>
    </div>

    <div class="admin-panel">
        <div class="admin-panel-header">
            <h3 class="admin-panel-title">Active plan distribution</h3>
        </div>
        <div class="admin-plan-list">
            @forelse($planBreakdown as $plan)
            <div class="admin-plan-row">
                <span class="admin-badge {{ 'plan-' . $plan->plan }}">{{ ucfirst($plan->plan) }}</span>
                <span class="admin-plan-count">{{ $plan->count }}</span>
            </div>
            @empty
            <p class="text-muted" style="font-size:13px; text-align:center; padding:20px 0;">No active subscriptions yet.</p>
            @endforelse
        </div>
    </div>

</div>

{{-- ── Tables Row ── --}}
<div class="admin-grid-2">

    <div class="admin-panel">
        <div class="admin-panel-header">
            <h3 class="admin-panel-title">Recent organizations</h3>
            <a href="{{ route('admin.organizations.index') }}" class="admin-panel-link">View all →</a>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Organization</th>
                        <th>Stores</th>
                        <th>Status</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentOrgs as $org)
                    <tr>
                        <td>
                            <div style="font-weight:600;">
                                <a href="{{ route('admin.organizations.show', $org) }}" style="color:inherit; text-decoration:none;">{{ $org->name }}</a>
                            </div>
                            <div class="text-muted" style="font-size:12px;">{{ $org->owner?->email }}</div>
                        </td>
                        <td style="text-align:center;">{{ $org->businesses_count }}</td>
                        <td>
                            @if($org->status === 'active')
                                <span class="admin-badge admin-badge-green">Active</span>
                            @elseif($org->status === 'trial')
                                <span class="admin-badge admin-badge-amber">Trial</span>
                            @else
                                <span class="admin-badge admin-badge-red">Expired</span>
                            @endif
                        </td>
                        <td class="text-muted" style="font-size:12px;">{{ $org->created_at->format('d M Y') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-muted" style="text-align:center; padding:24px;">No organizations yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="admin-panel">
        <div class="admin-panel-header">
            <h3 class="admin-panel-title">Recent payments</h3>
            <a href="{{ route('admin.subscriptions.index') }}" class="admin-panel-link">View all →</a>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Organization</th>
                        <th>Plan</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentPayments as $sub)
                    <tr>
                        <td style="font-weight:600;">{{ $sub->organization?->name ?? '—' }}</td>
                        <td><span class="admin-badge {{ 'plan-' . $sub->plan }}">{{ ucfirst($sub->plan) }}</span></td>
                        <td style="font-weight:600;">KES {{ number_format($sub->amount, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="text-muted" style="text-align:center; padding:24px;">No payments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

{{-- ── Recent Admin Activity — audit trail for every state-changing admin
     action (suspend/activate, trial extensions, subscription grants/
     cancels, notes, team-member toggles, admin password changes). This is
     the first place any of that is actually visible to anyone. ── --}}
<div class="admin-panel" style="margin-top: 16px;">
    <div class="admin-panel-header">
        <h3 class="admin-panel-title"><i class="ph-bold ph-clipboard-text"></i> Recent Admin Activity</h3>
    </div>
    @if($recentAdminActivity->isEmpty())
    <div style="padding:24px; text-align:center; color: var(--color-text-secondary); font-size:13.5px;">
        No admin actions recorded yet.
    </div>
    @else
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Admin</th>
                    <th>Action</th>
                    <th>Target</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentAdminActivity as $entry)
                @php
                    // "admin.organization.suspended" → "Organization Suspended"
                    $label = ucwords(str_replace(['admin.', '.', '_'], ['', ' ', ' '], $entry->event));

                    $target = match(true) {
                        $entry->subject instanceof \App\Models\Organization => $entry->subject->name,
                        $entry->subject instanceof \App\Models\Business     => $entry->subject->name,
                        $entry->subject instanceof \App\Models\User         => $entry->subject->name,
                        $entry->subject instanceof \App\Models\Subscription => ucfirst($entry->subject->plan) . ' — KES ' . number_format($entry->subject->amount, 0),
                        default => $entry->metadata['organization'] ?? $entry->metadata['business'] ?? '—',
                    };
                @endphp
                <tr>
                    <td style="color: var(--color-text-secondary); font-size:12px; white-space:nowrap;">{{ $entry->created_at->diffForHumans() }}</td>
                    <td style="font-weight:600;">{{ $entry->user?->name ?? 'Unknown' }}</td>
                    <td>{{ $label }}</td>
                    <td style="color: var(--color-text-secondary);">{{ $target }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

@endsection

@push('scripts')
<script src="{{ asset('js/chart.umd.min.js') }}"></script>
<script>
(function() {
    const labels  = @json($chartLabels);
    const revenue = @json($chartRevenue);

    const ctx = document.getElementById('revenueChart');
    if (!ctx) return;

    const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 220);
    gradient.addColorStop(0, 'rgba(99,102,241,0.85)');
    gradient.addColorStop(1, 'rgba(129,140,248,0.55)');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Revenue (KES)',
                data: revenue,
                backgroundColor: gradient,
                borderColor:     'rgba(79,70,229,0.9)',
                borderWidth: 1,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: {
                    grid:  { color: 'rgba(99,102,241,.06)' },
                    ticks: { color: '#888', font: { size: 11 } },
                },
                y: {
                    grid:  { color: 'rgba(99,102,241,.06)' },
                    ticks: { color: '#888', font: { size: 11 }, callback: v => 'KES ' + v.toLocaleString() },
                    beginAtZero: true,
                }
            }
        }
    });
})();
</script>
@endpush
