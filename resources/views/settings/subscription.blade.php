@extends('layouts.app')
@section('title', 'Subscription')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}?v={{ @filemtime(public_path('css/settings.css')) ?: '1' }}">
@endpush

@section('content')
<div class="page">

<div class="page-header">
    <div>
        <h1 class="page-title">Settings</h1>
        <p class="page-subtitle">Manage your business and account</p>
    </div>
</div>

<div class="settings-layout">

    @include('settings._nav')

    <div>

        @php
            $currentPlan = $organization->subscription_plan ?? 'solo';
            $isActive    = $organization->hasActiveAccess();
        @endphp

        {{-- Current plan banner — thinned down from a tall two-column block
        (its own "Current Plan" label, a big heading, then a separately
        right-aligned "Status" label repeating the same word as a badge)
        into one compact row: label, plan name and status badge inline,
        subtitle underneath. Roughly a third of the old height, and wraps
        cleanly instead of leaving a large right-aligned column with
        nothing to shrink on narrow screens. --}}
        <div class="current-plan-banner" style="background: {{ $isActive ? 'var(--color-primary)' : 'var(--color-danger)' }};">
            <div class="current-plan-row">
                <span class="current-plan-label">{{ $isActive ? 'Current Plan' : 'No Active Plan' }}</span>
                <span class="current-plan-name">
                    @if($isActive)
                        {{ $organization->planName() }}
                    @else
                        Subscription Expired
                    @endif
                </span>
                <span class="current-plan-badge">
                    {{ $isActive ? ucfirst($organization->status) : 'Expired' }}
                </span>
            </div>
            <div class="current-plan-sub">
                @if($organization->isOnTrial())
                    Free trial ends {{ $organization->trial_ends_at->format('d M Y') }}
                    ({{ $organization->trial_ends_at->diffForHumans() }})
                    {{-- planName() itself returns "Trial" while on trial (see
                    Organization::planName()) — use the real plan name here or
                    this reads as the nonsensical "Trial-level access during trial". --}}
                    &mdash; {{ $organization->planConfig()['name'] }}-level access during trial
                @elseif($isActive)
                    Active subscription &mdash;
                    {{ $organization->storeLimit() === -1 ? 'Unlimited' : $organization->storeLimit() }} store(s) allowed
                @else
                    Choose a plan below to restore access
                @endif
            </div>
        </div>

        {{-- Promo code --}}
        <div class="settings-card" style="margin-bottom: var(--space-4);">
            <div class="settings-card-body" style="padding: var(--space-4);">
                <label class="form-label" for="promoCodeInput">Have a promo code?</label>
                <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:flex-start;">
                    <input type="text" id="promoCodeInput" class="form-control" style="max-width:220px; text-transform:uppercase;" placeholder="e.g. LAUNCH20">
                    <button type="button" class="btn btn-outline" onclick="applyPromoCode()">Apply</button>
                </div>
                <div id="promoCodeMessage" style="margin-top:8px; font-size: var(--text-sm); display:none;"></div>
            </div>
        </div>

        {{-- Plans Grid --}}
        <div class="plans-grid">
            @foreach($plans as $key => $plan)
            @php
                $isCurrent  = $isActive && !$organization->isOnTrial() && $currentPlan === $key;
                $rawStoreLimit = $plan['limits']['stores']; // -1 or PHP_INT_MAX both mean unlimited, depending on plan
                $rawUserLimit  = $plan['limits']['users'];
                $storeLimit = ($rawStoreLimit === PHP_INT_MAX || $rawStoreLimit === -1) ? 'Unlimited' : $rawStoreLimit;
                $userLimit  = $rawUserLimit === PHP_INT_MAX ? 'Unlimited' : $rawUserLimit;

                // Would switching to this plan leave the org over its store/team
                // limit? Nothing gets deleted or deactivated on a downgrade — this
                // is purely a heads-up before they commit, since it's easy to miss
                // that existing stores/staff stay fully active either way, you just
                // can't add MORE until you upgrade again.
                $storesOverOnThisPlan = !$isCurrent && $storeLimit !== 'Unlimited' && $currentStoreCount > $rawStoreLimit;
                $usersOverOnThisPlan  = !$isCurrent && $userLimit  !== 'Unlimited' && $currentUserCount  > $rawUserLimit;
                $isDowngradeRisk = $storesOverOnThisPlan || $usersOverOnThisPlan;

                $features   = [
                    'solo'       => ['Inventory & Sales', 'Payroll (basic)', 'M-Pesa payments', 'Up to ' . $storeLimit . ' store', $userLimit . ' staff accounts', '1-month free trial'],
                    'growth'     => ['Everything in Solo', 'Statutory deductions (PAYE/NSSF/SHIF)', 'P9 annual tax forms', 'Cross-store reports', 'Up to ' . $storeLimit . ' stores', 'Up to ' . $userLimit . ' team members'],
                    'enterprise' => ['Everything in Growth', 'Unlimited stores', 'Unlimited staff', 'REST API access', 'Priority support'],
                ][$key] ?? [];
            @endphp
            <div id="plan-{{ $key }}" class="plan-card {{ $isCurrent ? 'current' : '' }}" data-plan-key="{{ $key }}" data-plan-price="{{ (int) $plan['price'] }}" data-plan-name="{{ $plan['name'] }}">

                @if($isCurrent)
                    <div class="plan-current-badge">Current Plan</div>
                @endif

                <div class="plan-name">{{ $plan['name'] }}</div>

                <div class="plan-price">
                    <span id="plan-price-display-{{ $key }}">KSh {{ number_format($plan['price'], 0) }}</span>
                    <span>/ month</span>
                </div>

                <ul class="plan-features">
                    @foreach($features as $feature)
                        {{-- The text needs its own element — a bare text node
                        inside a flex li defaults to min-width:auto and won't
                        shrink below an unbreakable run like "(PAYE/NSSF/SHIF)"
                        (no spaces to wrap at), which was spilling text past
                        the card's edge instead of wrapping. See .plan-features
                        li span in settings.css. --}}
                        <li><span>{{ $feature }}</span></li>
                    @endforeach
                </ul>

                @if($isDowngradeRisk)
                    @php
                        $warningBits = [];
                        if ($storesOverOnThisPlan) $warningBits[] = "{$currentStoreCount} stores (this plan allows {$storeLimit})";
                        if ($usersOverOnThisPlan)  $warningBits[] = "{$currentUserCount} team members (this plan allows {$userLimit})";
                        $warningText = 'You currently have ' . implode(' and ', $warningBits)
                            . ". You won't lose access to any of them, but you won't be able to add more until you upgrade again.";
                    @endphp
                    {{-- Was built from 3 undefined CSS variables
                    (--color-warning-bg/-border/-text don't exist in
                    main.css) each silently falling back to a hardcoded hex
                    triple — now uses the app's real alert--warning class. --}}
                    <div
                        class="plan-downgrade-warning alert alert--warning"
                        data-warning-text="{{ $warningText }}"
                        style="font-size: var(--text-xs); margin-bottom: 0.75rem;">
                        {{ $warningText }}
                    </div>
                @endif

                {{-- Wrapped + pushed to the bottom via .plan-cta's margin-top:auto
                (see .plan-card's flex-column in settings.css) — without this,
                a card with a longer feature list or the downgrade-risk warning
                box pushes its own buttons further down than the other cards',
                leaving them at three different heights across one row instead
                of lined up. --}}
                <div class="plan-cta">
                @if($isCurrent)
                    <button class="btn btn-outline" style="width: 100%;" disabled>
                        Active
                    </button>
                @else
                    {{-- Paystack (card / mobile money) --}}
                    <form method="POST" action="{{ route('paystack.subscribe') }}" style="margin-bottom:8px;" @if($isDowngradeRisk) onsubmit="return confirmPlanChange(this, {{ Illuminate\Support\Js::from($warningText) }})" @endif>
                        @csrf
                        <input type="hidden" name="plan" value="{{ $key }}">
                        <input type="hidden" name="promo_code" id="promo-code-{{ $key }}" value="">
                        <button type="submit" class="btn btn-primary" style="width:100%; background:#0ba4db; border-color:#0ba4db;">
                            Pay with Card / Paystack
                        </button>
                    </form>

                    {{-- M-Pesa fallback --}}
                    <button
                        class="btn btn-outline"
                        style="width: 100%;"
                        onclick="{{ $isDowngradeRisk ? 'if(!confirm(' . Illuminate\Support\Js::from($warningText . ' Continue?') . '))return;' : '' }}openSubscribeModalForPlan('{{ $key }}', {{ $plan['price'] }}, '{{ $plan['name'] }}')">
                        Pay via M-Pesa
                    </button>
                @endif
                </div>
            </div>
            @endforeach
        </div>

        {{-- Subscription history --}}
        @if($organization->subscriptions()->exists())
        <div class="settings-card" style="margin-top: var(--space-6);">
            <div class="settings-card-header">
                <h2>Payment History</h2>
            </div>
            <div class="settings-card-body" style="padding: 0;">
                {{-- .data-table-wrap (the app's established horizontal-scroll
                fallback — see components.css) — without it, this 6-column
                table's natural width didn't fit its content column between
                768px (where the global mobile card-stack rule takes over)
                and roughly 1050px, forcing the whole page into horizontal
                scroll in that range instead of just this one table. --}}
                <div class="data-table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Plan</th>
                            <th>Amount</th>
                            <th>Channel</th>
                            <th>Start</th>
                            <th>Expires</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($organization->subscriptions()->latest()->take(10)->get() as $sub)
                        <tr>
                            <td data-label="Plan">{{ ucfirst($sub->plan) }}</td>
                            <td data-label="Amount">KSh {{ number_format($sub->amount, 2) }}</td>
                            <td data-label="Channel" style="font-size:0.8rem;">{{ $sub->payment_channel === 'paystack' ? 'Paystack' : 'M-Pesa' }}</td>
                            {{-- Was the raw "2026-08-13 00:00:00" datetime string
                            — needlessly wide (contributing to the overflow
                            above) and inconsistent with how every other date
                            in this app reads (e.g. "Free trial ends 13 Oct
                            2026" higher up this same page). --}}
                            <td data-label="Start">{{ $sub->start_date->format('d M Y') }}</td>
                            <td data-label="Expires">{{ $sub->end_date->format('d M Y') }}</td>
                            <td data-label="Status">
                                <span class="badge badge-{{ $sub->status === 'active' ? 'success' : 'secondary' }}">
                                    {{ ucfirst($sub->status) }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>
        </div>
        @endif

        {{-- Contact --}}
        <div style="
            background:    var(--color-surface);
            border-radius: var(--radius-lg);
            border:        1px solid var(--color-border);
            padding:       var(--space-6);
            text-align:    center;
            font-size:     var(--text-sm);
            color:         var(--color-text-muted);
            margin-top:    var(--space-6);">
            Need help choosing a plan? Contact us at
            <strong style="color: var(--color-text);">support@merqiopos.com</strong>
            or call <strong style="color: var(--color-text);">0718 215 432</strong>.
        </div>
    </div>
</div>
</div>{{-- end .page --}}

{{-- ── M-Pesa Subscription Modal ──────────────────────────────────────── --}}
<div id="subscribeModal" class="modal-overlay">
    <div class="modal" style="max-width: 420px;">

        {{-- Header --}}
        <div class="modal-header">
            <h3>
                Pay via M-Pesa
            </h3>
            <button class="modal-close" onclick="closeSubscribeModal()">&times;</button>
        </div>

        {{-- Plan summary --}}
        <div style="
            background:    var(--color-surface-2);
            border:        1px solid var(--color-border);
            border-radius: var(--radius-md);
            padding:       var(--space-4);
            margin-bottom: var(--space-5);
            text-align:    center;">
            <div style="font-size: var(--text-xs); color: var(--color-text-muted); margin-bottom: 4px;">
                Selected Plan
            </div>
            <div style="font-size: var(--text-xl); font-weight: 700; color: var(--color-text); font-family: var(--font-heading);">
                <span id="modalPlanName"></span>
            </div>
            <div style="font-size: var(--text-2xl); font-weight: 800; color: var(--color-primary); margin-top: 4px;">
                <span id="modalPlanAmount"></span>
                <span style="font-size: var(--text-xs); font-weight: 400; color: var(--color-text-muted);">/month</span>
            </div>
        </div>

        {{-- Phone input --}}
        <div class="form-group">
            <label class="form-label">M-Pesa Phone Number</label>
            <input
                type="tel"
                id="subPhone"
                class="form-control"
                placeholder="e.g. 0712345678"
                value="{{ Auth::user()->phone ?? '' }}"
                maxlength="13">
        </div>

        {{-- Status feedback --}}
        <div id="subscribeStatus" style="display:none; margin-bottom: var(--space-4);"></div>

        {{-- Pay button --}}
        <button
            id="subscribePayBtn"
            onclick="initiateSubscription()"
            class="btn btn-success"
            style="width: 100%; padding: 0.85rem; font-size: var(--text-base);">
            Pay via M-Pesa
        </button>

        <p style="font-size: var(--text-xs); color: var(--color-text-muted); text-align: center; margin-top: var(--space-3); margin-bottom: 0;">
            You will receive an M-Pesa prompt on your phone. Enter your PIN to complete the payment.
        </p>

        {{-- Hidden inputs --}}
        <input type="hidden" id="modalPlanKey">
        <input type="hidden" id="modalAmount">
        <input type="hidden" id="modalPromoCode">
    </div>
</div>

@endsection

@push('scripts')
    <script src="{{ asset('js/mpesa.js') }}"></script>
    <script>
        // Used on the Paystack "downgrade" forms — confirm() before the
        // browser submits the payment, since the plan-change itself is
        // instant on the backend the moment payment clears.
        function confirmPlanChange(form, warningText) {
            return confirm(warningText + ' Continue?');
        }

        // Promo codes: validated once per plan card via the AJAX preview
        // endpoint, then stashed here so both checkout paths (the Paystack
        // form's hidden field, and the M-Pesa modal opened below) can pick
        // up the discounted amount without re-prompting for the code.
        // Keyed by plan so a code that only applies to some plans (e.g.
        // "GROWTH20") correctly leaves the others at full price.
        window.appliedPromo = { code: null, byPlan: {} };

        function openSubscribeModalForPlan(planKey, basePrice, planName) {
            const discounted = window.appliedPromo.byPlan[planKey];
            const amount = discounted !== undefined ? discounted : basePrice;
            openSubscribeModal(planKey, amount, planName);
            document.getElementById('modalPromoCode').value = discounted !== undefined ? window.appliedPromo.code : '';
        }

        function applyPromoCode() {
            const input = document.getElementById('promoCodeInput');
            const message = document.getElementById('promoCodeMessage');
            const code = input.value.trim();

            if (!code) return;

            message.style.display = 'block';
            message.style.color = 'var(--color-text-muted)';
            message.textContent = 'Checking…';

            const cards = document.querySelectorAll('.plan-card[data-plan-key]');
            window.appliedPromo = { code, byPlan: {} };

            const checks = Array.from(cards).map(card => {
                const planKey = card.dataset.planKey;
                const price   = parseFloat(card.dataset.planPrice);

                return fetch('{{ route('settings.subscription.apply-promo') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ code, plan: planKey }),
                })
                .then(r => r.json())
                .then(data => {
                    const priceDisplay = document.getElementById('plan-price-display-' + planKey);
                    const promoInput   = document.getElementById('promo-code-' + planKey);

                    if (data.valid) {
                        window.appliedPromo.byPlan[planKey] = data.discountedAmount;
                        if (priceDisplay) {
                            priceDisplay.innerHTML = '<s style="opacity:0.5; font-size:0.8em;">KSh ' + price.toLocaleString() + '</s> KSh ' + data.discountedAmount.toLocaleString();
                        }
                        if (promoInput) promoInput.value = code;
                        return true;
                    }

                    if (priceDisplay) priceDisplay.textContent = 'KSh ' + price.toLocaleString();
                    if (promoInput) promoInput.value = '';
                    return false;
                });
            });

            Promise.all(checks).then(results => {
                const anyValid = results.some(Boolean);
                message.style.color = anyValid ? 'var(--color-success)' : 'var(--color-danger)';
                message.textContent = anyValid
                    ? 'Promo code applied — discounted price shown below.'
                    : 'That promo code is not valid for any plan.';
            });
        }
    </script>
@endpush
