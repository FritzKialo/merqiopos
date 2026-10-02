@extends('admin.layouts.app')
@section('title', $organization->name)
@section('subtitle', 'Organization detail — managed by ' . ($organization->owner?->name ?? 'unknown'))

@push('styles')
<style>
/* .admin-detail-grid's own mobile collapse rule (admin.css) has no
   !important, so it never actually overrode this element's own inline
   grid-template-columns — an inline style always wins over an external
   rule for the same property regardless of specificity or media query. */
@media (max-width: 1024px) {
    .admin-detail-grid { grid-template-columns: 1fr !important; }
}

/* ── Org hero ─────────────────────────────────────────────── */
.admin-org-hero {
    display: flex;
    align-items: flex-start;
    gap: 18px;
    padding: 22px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.admin-org-hero-avatar {
    flex-shrink: 0;
    width: 60px; height: 60px;
    border-radius: 50%;
    background: var(--admin-accent-grad);
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; font-weight: 700;
}
.admin-org-hero-info { flex: 1; min-width: 220px; }
.admin-org-hero-name { font-size: 20px; font-weight: 700; color: #0a0a0a; margin: 0 0 4px; }
.admin-org-hero-owner { font-size: 13px; color: #666; margin: 0 0 10px; }
.admin-org-hero-badges { display: flex; gap: 8px; flex-wrap: wrap; }
.admin-org-hero-actions { display: flex; gap: 8px; flex-wrap: wrap; flex-shrink: 0; }

/* ── Trial countdown bar ─────────────────────────────────── */
.admin-trial-bar-track {
    height: 8px; border-radius: 4px;
    background: #e8e8e8; overflow: hidden; margin-top: 4px;
}
.admin-trial-bar-fill { height: 100%; border-radius: 4px; transition: width .3s; }

/* ── Quick-pick day/month buttons ────────────────────────── */
.admin-quickpick { display: flex; gap: 6px; flex-wrap: wrap; }
.admin-quickpick button {
    padding: 5px 11px; border-radius: 6px; font-size: 12.5px; font-weight: 600;
    background: #f0f0f0; border: 1px solid #d0d0d0; color: #444; cursor: pointer;
    font-family: inherit;
}
.admin-quickpick button:hover { background: #e4e4e4; }
.admin-quickpick button.active { background: var(--admin-accent-grad); border-color: transparent; color: #fff; }

/* ── Notes ────────────────────────────────────────────────── */
.admin-notes-textarea {
    width: 100%; min-height: 110px; resize: vertical;
    background: var(--color-surface-2); border: 1px solid var(--color-border);
    border-radius: 8px; padding: 10px 12px; color: var(--color-text);
    font-size: 13.5px; font-family: inherit; outline: none; line-height: 1.5;
}
.admin-notes-textarea:focus { border-color: var(--admin-accent); }
</style>
@endpush

@section('content')

<div class="admin-page-header">
    <div>
        <a href="{{ route('admin.organizations.index') }}" class="admin-btn admin-btn-gray admin-btn-sm" style="margin-bottom:10px; display:inline-flex;">
            <i class="ph-bold ph-arrow-left"></i> Back
        </a>
    </div>
</div>

{{-- ── Hero ── --}}
<div class="admin-panel admin-org-hero">
    <div class="admin-org-hero-avatar">{{ strtoupper(substr($organization->name ?: '?', 0, 1)) }}</div>
    <div class="admin-org-hero-info">
        <h1 class="admin-org-hero-name">{{ $organization->name ?: 'Unnamed Organization' }}</h1>
        <p class="admin-org-hero-owner">
            Managed by <strong>{{ $organization->owner?->name ?? 'unknown' }}</strong>
            @if($organization->owner?->email)
                · <a href="mailto:{{ $organization->owner->email }}" style="color:inherit;">{{ $organization->owner->email }}</a>
            @endif
        </p>
        <div class="admin-org-hero-badges">
            @if($organization->status === 'active')
                <span class="admin-badge admin-badge-green">Active</span>
            @elseif($organization->status === 'trial')
                @php $daysLeft = $organization->trialDaysLeft(); @endphp
                <span class="admin-badge {{ $daysLeft <= 3 ? 'admin-badge-red' : 'admin-badge-amber' }}">
                    Trial · {{ $daysLeft }} day(s) left
                </span>
            @elseif($organization->status === 'suspended')
                <span class="admin-badge admin-badge-red">Suspended</span>
            @else
                <span class="admin-badge admin-badge-gray">{{ ucfirst($organization->status) }}</span>
            @endif
            <span class="admin-badge {{ 'plan-' . ($organization->subscription_plan ?? 'none') }}">
                {{ ucfirst($organization->subscription_plan ?? 'None') }} plan
            </span>
        </div>
        @if($organization->isOnTrial())
            @php
                // Approximate the trial's total span as 30 days (1 month)
                // unless it's clearly longer (an admin-extended trial) —
                // just enough to give the bar a sensible fill, not a precise ledger.
                $totalGuess = max(30, $daysLeft);
                $pctLeft = max(0, min(100, (int) round(($daysLeft / $totalGuess) * 100)));
                $barColor = $daysLeft <= 3 ? '#b91c1c' : ($daysLeft <= 7 ? '#a16207' : '#15803d');
            @endphp
            <div class="admin-trial-bar-track" style="width:220px;">
                <div class="admin-trial-bar-fill" style="width:{{ $pctLeft }}%; background:{{ $barColor }};"></div>
            </div>
        @endif
    </div>
    <div class="admin-org-hero-actions">
        @if($organization->status !== 'suspended')
        <form method="POST" action="{{ route('admin.organizations.suspend', $organization) }}" style="display:contents">
            @csrf @method('PATCH')
            <button type="submit" class="admin-btn admin-btn-red admin-btn-sm"
                onclick="return confirm('Suspend this organization?')">
                <i class="ph-bold ph-pause-circle"></i> Suspend
            </button>
        </form>
        @else
        <form method="POST" action="{{ route('admin.organizations.activate', $organization) }}" style="display:contents">
            @csrf @method('PATCH')
            <button type="submit" class="admin-btn admin-btn-green admin-btn-sm">
                <i class="ph-bold ph-play-circle"></i> Activate
            </button>
        </form>
        @endif
    </div>
</div>

{{-- ── KPI strip ── --}}
<div class="admin-stats admin-stats-5">
    <div class="admin-stat-card">
        <div class="admin-stat-label">Stores</div>
        <div class="admin-stat-value">{{ $organization->businesses->count() }}</div>
        <div class="admin-stat-sub">of {{ $organization->storeLimit() === -1 ? '∞' : $organization->storeLimit() }} allowed</div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label">Team Members</div>
        <div class="admin-stat-value">{{ $organization->userCount() }}</div>
        <div class="admin-stat-sub">of {{ $organization->userLimit() >= PHP_INT_MAX ? '∞' : $organization->userLimit() }} allowed</div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label">Current MRR</div>
        <div class="admin-stat-value">KSh {{ number_format($organization->estimatedMrr(), 0) }}</div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label">Lifetime Revenue</div>
        <div class="admin-stat-value">KSh {{ number_format($lifetimeRevenue, 0) }}</div>
        <div class="admin-stat-sub">all-time, all plans</div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label">Registered</div>
        <div class="admin-stat-value" style="font-size:16px;">{{ $organization->created_at->format('d M Y') }}</div>
        <div class="admin-stat-sub">{{ $organization->created_at->diffForHumans() }}</div>
    </div>
</div>

{{-- ── Sales trend across all stores ── --}}
<div class="admin-panel" style="margin-bottom: var(--space-5);">
    <div class="admin-panel-header">
        <h3 class="admin-panel-title">Sales — all stores combined, last 6 months</h3>
    </div>
    <div class="admin-chart-wrap" style="height:240px;">
        <canvas id="orgSalesChart"></canvas>
    </div>
</div>

<div class="admin-detail-grid" style="display:grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: var(--space-5);">

    {{-- ── Org Overview ── --}}
    <div class="admin-panel">
        <div class="admin-panel-header">
            <h3 class="admin-panel-title">Overview</h3>
        </div>
        <div style="padding: 20px; display:flex; flex-direction:column; gap:14px;">
            <div class="detail-row">
                <span class="detail-label">Status</span>
                @if($organization->status === 'active')
                    <span class="admin-badge admin-badge-green">Active</span>
                @elseif($organization->status === 'trial')
                    <span class="admin-badge admin-badge-amber">Trial</span>
                @elseif($organization->status === 'suspended')
                    <span class="admin-badge admin-badge-red">Suspended</span>
                @else
                    <span class="admin-badge admin-badge-gray">{{ ucfirst($organization->status) }}</span>
                @endif
            </div>
            <div class="detail-row">
                <span class="detail-label">Plan</span>
                <span class="admin-badge {{ 'plan-' . ($organization->subscription_plan ?? 'none') }}">
                    {{ ucfirst($organization->subscription_plan ?? 'None') }}
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Stores</span>
                <strong>{{ $organization->businesses->count() }}</strong>
            </div>
            <div class="detail-row">
                <span class="detail-label">Total Staff</span>
                <strong>{{ $organization->businesses->sum(fn ($b) => $b->users->count()) }}</strong>
            </div>
            @if($organization->trial_ends_at)
            <div class="detail-row">
                <span class="detail-label">Trial Ends</span>
                <span>{{ $organization->trial_ends_at->format('d M Y') }}</span>
            </div>
            @endif
            <div class="detail-row">
                <span class="detail-label">Registered</span>
                <span>{{ $organization->created_at->format('d M Y') }}</span>
            </div>
        </div>
    </div>

    {{-- ── Owner ── --}}
    <div class="admin-panel">
        <div class="admin-panel-header">
            <h3 class="admin-panel-title">Owner</h3>
        </div>
        <div style="padding: 20px; display:flex; flex-direction:column; gap:14px;">
            <div class="detail-row">
                <span class="detail-label">Name</span>
                <strong>{{ $organization->owner?->name ?? '—' }}</strong>
            </div>
            <div class="detail-row">
                <span class="detail-label">Email</span>
                <span>
                    {{ $organization->owner?->email ?? '—' }}
                    @if($organization->owner?->email)
                    <button type="button" class="admin-btn admin-btn-gray admin-btn-sm" style="padding:2px 8px; margin-left:6px;"
                        onclick="navigator.clipboard.writeText('{{ addslashes($organization->owner->email) }}'); this.innerHTML='<i class=\'ph-bold ph-check\'></i>'; setTimeout(() => this.innerHTML='<i class=\'ph-bold ph-copy\'></i>', 1200);"
                        title="Copy email">
                        <i class="ph-bold ph-copy"></i>
                    </button>
                    @endif
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Phone</span>
                <span>{{ $organization->owner?->phone ?? '—' }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">2FA</span>
                @if($organization->owner?->hasTwoFactorEnabled())
                    <span class="admin-badge admin-badge-green">Enabled</span>
                    <form method="POST" action="{{ route('admin.organizations.reset-2fa', $organization) }}" style="display:inline; margin-left:8px;"
                        onsubmit="return confirm('Reset two-factor authentication for {{ addslashes($organization->owner->name) }}? They will be able to sign in with just their password and set up 2FA again from Settings. Only do this after verifying their identity through another channel.');">
                        @csrf
                        <button type="submit" class="admin-btn admin-btn-gray admin-btn-sm">Reset 2FA</button>
                    </form>
                @else
                    <span class="admin-badge admin-badge-gray">Disabled</span>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ── Stores ── --}}
<div class="admin-panel" style="margin-top: var(--space-5);">
    <div class="admin-panel-header">
        <h3 class="admin-panel-title">Stores ({{ $organization->businesses->count() }})</h3>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Store</th>
                    <th>Type</th>
                    <th>City</th>
                    <th>Staff</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($organization->businesses as $store)
                <tr>
                    <td style="font-weight:600;">{{ $store->name }}</td>
                    <td class="detail-label">{{ ucfirst($store->business_type ?? 'Retail') }}</td>
                    <td class="detail-label">{{ $store->city ?? '—' }}</td>
                    <td style="text-align:center;">{{ $store->users->count() }}</td>
                    <td>
                        @if($store->status === 'active')
                            <span class="admin-badge admin-badge-green">Active</span>
                        @elseif($store->status === 'suspended')
                            <span class="admin-badge admin-badge-red">Suspended</span>
                        @else
                            <span class="admin-badge admin-badge-gray">{{ ucfirst($store->status) }}</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex; gap:6px; flex-wrap:wrap;">
                            <a href="{{ route('admin.businesses.show', $store) }}" class="admin-btn admin-btn-gray admin-btn-sm">
                                <i class="ph-bold ph-eye"></i> View
                            </a>
                            {{-- Inline, matching what admin/businesses now has directly
                                 — no need to leave this page just to suspend one store. --}}
                            @if($store->status !== 'suspended')
                            <form method="POST" action="{{ route('admin.businesses.suspend', $store) }}" style="display:contents">
                                @csrf @method('PATCH')
                                <button type="submit" class="admin-btn admin-btn-red admin-btn-sm"
                                    onclick="return confirm('Suspend {{ addslashes($store->name) }}? Its staff will be logged out and blocked from it.')">
                                    <i class="ph-bold ph-pause-circle"></i> Suspend
                                </button>
                            </form>
                            @else
                            <form method="POST" action="{{ route('admin.businesses.activate', $store) }}" style="display:contents">
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
                    <td colspan="6" style="text-align:center; padding:24px; color: var(--color-text-secondary);">No stores yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ── Subscription History ── --}}
<div class="admin-panel" style="margin-top: var(--space-5);">
    <div class="admin-panel-header">
        <h3 class="admin-panel-title">Subscription History</h3>
    </div>
    @if($organization->subscriptions->isNotEmpty())
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Plan</th>
                    <th>Amount</th>
                    <th>Start</th>
                    <th>Expires</th>
                    <th>Reference</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($organization->subscriptions as $sub)
                <tr>
                    <td><span class="admin-badge {{ 'plan-' . $sub->plan }}">{{ ucfirst($sub->plan) }}</span></td>
                    <td style="font-weight:600;">KES {{ number_format($sub->amount, 2) }}</td>
                    <td style="font-size:13px; color: var(--color-text-secondary);">{{ $sub->start_date }}</td>
                    <td style="font-size:13px; color: var(--color-text-secondary);">{{ $sub->end_date }}</td>
                    <td style="font-size:11px; color: var(--color-text-secondary); font-family:monospace;">{{ $sub->payment_reference }}</td>
                    <td>
                        @php
                            $subBadge = match($sub->status) {
                                'active'    => 'admin-badge-green',
                                'cancelled' => 'admin-badge-red',
                                default     => 'admin-badge-gray',
                            };
                        @endphp
                        <span class="admin-badge {{ $subBadge }}">{{ ucfirst($sub->status) }}</span>
                    </td>
                    <td>
                        {{-- 'cancelled' has existed as a valid status since this
                             table was created, but nothing ever wrote it — there
                             was no way to revoke a subscription early at all. --}}
                        @if($sub->status === 'active' && $sub->end_date->isFuture())
                        <form method="POST" action="{{ route('admin.organizations.subscriptions.cancel', [$organization, $sub]) }}" style="display:contents">
                            @csrf @method('PATCH')
                            <button type="submit" class="admin-btn admin-btn-red admin-btn-sm"
                                onclick="return confirm('Cancel this subscription? The organization will revert to the Free plan on its next visit.')">
                                <i class="ph-bold ph-x-circle"></i> Cancel
                            </button>
                        </form>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div style="padding:24px; text-align:center; color: var(--color-text-secondary); font-size:14px;">
        No subscription history.
    </div>
    @endif
</div>

{{-- ── Internal Admin Notes ── --}}
<div class="admin-panel" style="margin-top: var(--space-5);">
    <div class="admin-panel-header">
        <h3 class="admin-panel-title">Internal Notes</h3>
        <span style="font-size:11px; color:#888;">Visible to platform admins only</span>
    </div>
    <form method="POST" action="{{ route('admin.organizations.notes', $organization) }}" style="padding:20px;">
        @csrf @method('PATCH')
        <textarea name="admin_notes" class="admin-notes-textarea"
            placeholder="e.g. escalation history, discount agreements, churn risk notes…">{{ old('admin_notes', $organization->admin_notes) }}</textarea>
        <div style="margin-top:10px; display:flex; justify-content:flex-end;">
            <button type="submit" class="admin-btn admin-btn-primary">
                <i class="ph-bold ph-floppy-disk"></i> Save Notes
            </button>
        </div>
    </form>
</div>

{{-- ── Admin Actions ── --}}
<div class="admin-detail-grid" style="display:grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: var(--space-5); margin-top: var(--space-5);">

    {{-- Extend Trial --}}
    <div class="admin-panel">
        <div class="admin-panel-header">
            <h3 class="admin-panel-title">Extend Trial</h3>
        </div>
        <form method="POST" action="{{ route('admin.organizations.extend-trial', $organization) }}"
              style="padding:20px; display:flex; flex-direction:column; gap:12px;">
            @csrf @method('PATCH')
            <div class="admin-quickpick" data-target="extendDays">
                <button type="button" data-value="7">+7 days</button>
                <button type="button" data-value="14" class="active">+14 days</button>
                <button type="button" data-value="30">+30 days</button>
                <button type="button" data-value="90">+90 days</button>
            </div>
            <div style="display:flex; gap:10px; align-items:flex-end;">
                <div style="flex:1;">
                    <label style="font-size:12px; color: var(--color-text-secondary); display:block; margin-bottom:6px;">
                        Additional days
                    </label>
                    <input type="number" name="days" id="extendDays" min="1" max="90" value="14"
                        style="width:100%; background: var(--color-surface-2); border: 1px solid var(--color-border); border-radius:6px; padding:8px 12px; color: var(--color-text); font-size:14px;">
                </div>
                <button type="submit" class="admin-btn admin-btn-primary">
                    <i class="ph-bold ph-clock"></i> Extend
                </button>
            </div>
        </form>
    </div>

    {{-- Grant Subscription --}}
    <div class="admin-panel">
        <div class="admin-panel-header">
            <h3 class="admin-panel-title">Grant Subscription</h3>
        </div>
        <form method="POST" action="{{ route('admin.organizations.grant-subscription', $organization) }}"
              style="padding:20px; display:flex; flex-direction:column; gap:12px;">
            @csrf
            <div class="admin-quickpick" data-target="grantMonths">
                <button type="button" data-value="1" class="active">1 mo</button>
                <button type="button" data-value="3">3 mo</button>
                <button type="button" data-value="6">6 mo</button>
                <button type="button" data-value="12">12 mo</button>
            </div>
            <div style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
                <div style="flex:1; min-width:120px;">
                    <label style="font-size:12px; color: var(--color-text-secondary); display:block; margin-bottom:6px;">Plan</label>
                    <select name="plan"
                        style="width:100%; background: var(--color-surface-2); border: 1px solid var(--color-border); border-radius:6px; padding:8px 12px; color: var(--color-text); font-size:14px;">
                        <option value="solo">Solo</option>
                        <option value="growth" selected>Growth</option>
                        <option value="enterprise">Enterprise</option>
                    </select>
                </div>
                <div style="min-width:80px;">
                    <label style="font-size:12px; color: var(--color-text-secondary); display:block; margin-bottom:6px;">Months</label>
                    <input type="number" name="months" id="grantMonths" min="1" max="12" value="1"
                        style="width:100%; background: var(--color-surface-2); border: 1px solid var(--color-border); border-radius:6px; padding:8px 12px; color: var(--color-text); font-size:14px;">
                </div>
                <button type="submit" class="admin-btn admin-btn-green">
                    <i class="ph-bold ph-gift"></i> Grant
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('js/chart.umd.min.js') }}?v={{ @filemtime(public_path('js/chart.umd.min.js')) ?: '1' }}"></script>
<script>
(function () {
    // Quick-pick day/month buttons — plain click-to-fill, no framework.
    document.querySelectorAll('.admin-quickpick').forEach(function (group) {
        var input = document.getElementById(group.dataset.target);
        group.querySelectorAll('button').forEach(function (btn) {
            btn.addEventListener('click', function () {
                group.querySelectorAll('button').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                input.value = btn.dataset.value;
            });
        });
    });

    var ctx = document.getElementById('orgSalesChart');
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
