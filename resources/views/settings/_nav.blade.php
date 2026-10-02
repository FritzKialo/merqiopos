<nav class="settings-nav">
    @php $user = auth()->user(); @endphp

    <div class="settings-nav-title">Settings</div>

    <ul>
        @if($user->canActAsOwner())
        <li class="{{ request()->routeIs('settings.business') ? 'active' : '' }}">
            <a href="{{ route('settings.business') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Business Profile
            </a>
        </li>
        {{-- Was reachable only by typing the URL directly — no nav link
        existed anywhere in the app. --}}
        <li class="{{ request()->routeIs('settings.branding') ? 'active' : '' }}">
            <a href="{{ route('settings.branding') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                Receipt & Invoice Branding
            </a>
        </li>
        <li class="{{ request()->routeIs('settings.currencies') ? 'active' : '' }}">
            <a href="{{ route('settings.currencies') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                Currencies
            </a>
        </li>
        @endif
        <li class="{{ request()->routeIs('settings.password') ? 'active' : '' }}">
            <a href="{{ route('settings.password') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Change Password
            </a>
        </li>
        @if($user->isSuperAdmin() || $user->canActAsOwner())
        <li class="{{ request()->routeIs('settings.2fa.*') ? 'active' : '' }}">
            <a href="{{ route('settings.2fa.setup') }}" style="display: flex; align-items: center; justify-content: space-between;">
                <span style="display:flex; align-items:center; gap: var(--space-2);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Two-Factor Auth
                </span>
                @if($user->hasTwoFactorEnabled())
                    <span style="
                        font-size: 10px;
                        font-weight: 700;
                        color: var(--color-success);
                        background: var(--color-success-light, #f0fdf4);
                        border: 1px solid var(--color-success);
                        border-radius: 999px;
                        padding: 1px 7px;
                        letter-spacing: 0.03em;">ON</span>
                @endif
            </a>
        </li>
        @endif
        @if($user->canActAsOwner() || $user->hasRole('manager'))
        <li class="{{ request()->routeIs('settings.team', 'settings.team.*') ? 'active' : '' }}">
            <a href="{{ route('settings.team') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Team Members
            </a>
        </li>
        <li class="{{ request()->routeIs('settings.memos.*') ? 'active' : '' }}">
            <a href="{{ route('settings.memos.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
                Memos
            </a>
        </li>
        <li class="{{ request()->routeIs('settings.newsletters.*') ? 'active' : '' }}">
            <a href="{{ route('settings.newsletters.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1 1-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8V6Z"/></svg>
                Newsletters
            </a>
        </li>
        @if($user->currentBusiness()?->organization?->hasFeature('payroll'))
        <li class="{{ request()->routeIs('settings.payroll') ? 'active' : '' }}">
            <a href="{{ route('settings.payroll') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                Payroll Setup
            </a>
        </li>
        @endif
        <li class="{{ request()->routeIs('settings.leave-types.*') ? 'active' : '' }}">
            <a href="{{ route('settings.leave-types.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Leave Types
            </a>
        </li>
        <li class="{{ request()->routeIs('settings.custom-fields.*') ? 'active' : '' }}">
            <a href="{{ route('settings.custom-fields.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                Custom Fields
            </a>
        </li>
        <li class="{{ request()->routeIs('settings.customer-tags.*') ? 'active' : '' }}">
            <a href="{{ route('settings.customer-tags.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41L13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                Customer Tags
            </a>
        </li>
        <li class="{{ request()->routeIs('settings.void-reasons.*') ? 'active' : '' }}">
            <a href="{{ route('settings.void-reasons.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                Void Reasons
            </a>
        </li>
        <li class="{{ request()->routeIs('settings.tax-rules.*') ? 'active' : '' }}">
            <a href="{{ route('settings.tax-rules.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>
                Tax Rules
            </a>
        </li>
        @endif
        @if($user->canActAsOwner())
        <li class="{{ request()->routeIs('settings.mpesa', 'settings.mpesa.*') ? 'active' : '' }}">
            <a href="{{ route('settings.mpesa') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                M-Pesa
            </a>
        </li>
        <li class="{{ request()->routeIs('settings.pesapal', 'settings.pesapal.*') ? 'active' : '' }}">
            <a href="{{ route('settings.pesapal') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                Pesapal (Card)
            </a>
        </li>
        {{-- Reachable only by direct URL until now — no nav link existed. --}}
        <li class="{{ request()->routeIs('settings.store', 'settings.store.*') ? 'active' : '' }}">
            <a href="{{ route('settings.store') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                Online Store
            </a>
        </li>
        <li class="{{ request()->routeIs('settings.delivery', 'settings.delivery.*') ? 'active' : '' }}">
            <a href="{{ route('settings.delivery') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                Delivery Settings
            </a>
        </li>
        {{-- Feature access can come from either the org's plan (payroll,
        cross-store reports, etc.) or the store's own plan (sms_notifications
        and most other features) — CheckPlanFeature's route middleware ORs
        both. This condition previously checked org-level only, which for
        sms_notifications is always false (no org plan defines that key) —
        hiding this link for every business, including ones on a store plan
        that genuinely has it (verified against real data: 3 of 6 test
        businesses have business->hasFeature true but were never shown the
        link). Matching the middleware's OR logic here. --}}
        @php $currentBiz = $user->currentBusiness(); @endphp
        @if(($currentBiz?->organization?->hasFeature('sms_notifications')) || ($currentBiz?->hasFeature('sms_notifications')))
        <li class="{{ request()->routeIs('settings.sms', 'settings.sms.*') ? 'active' : '' }}">
            <a href="{{ route('settings.sms') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                SMS Notifications
            </a>
        </li>
        @endif
        {{-- WhatsApp is built entirely on top of the SMS provider/API key
        configured on the page above — gated behind the same feature (and
        the same org-OR-business check) so a plan without SMS access doesn't
        show a WhatsApp settings page that can never actually work. --}}
        @if(($currentBiz?->organization?->hasFeature('sms_notifications')) || ($currentBiz?->hasFeature('sms_notifications')))
        <li class="{{ request()->routeIs('settings.whatsapp', 'settings.whatsapp.*') ? 'active' : '' }}">
            <a href="{{ route('settings.whatsapp') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                WhatsApp
            </a>
        </li>
        @endif
        <li class="{{ request()->routeIs('settings.loyalty', 'settings.loyalty.*') ? 'active' : '' }}">
            <a href="{{ route('settings.loyalty') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                Loyalty Program
            </a>
        </li>
        {{-- The Loyalty Tiers page itself was fully dead until earlier this
        session (routes/features_marketing.php had never been require()'d
        at all) — now reachable, but still had zero nav link of its own. --}}
        <li class="{{ request()->routeIs('settings.loyalty.tiers*') ? 'active' : '' }}">
            <a href="{{ route('settings.loyalty.tiers') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>
                Loyalty Tiers
            </a>
        </li>
        <li class="{{ request()->routeIs('settings.api') ? 'active' : '' }}">
            <a href="{{ route('settings.api') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                API Access
            </a>
        </li>
        <li class="{{ request()->routeIs('settings.email-domain') ? 'active' : '' }}">
            <a href="{{ route('settings.email-domain') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z"/><path d="M22 6l-10 7L2 6"/></svg>
                Email Domain
            </a>
        </li>
        <li class="{{ request()->routeIs('settings.dashboard-link') ? 'active' : '' }}">
            <a href="{{ route('settings.dashboard-link') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
                Manager Dashboard Link
            </a>
        </li>
        <li class="{{ request()->routeIs('settings.webhooks.*') ? 'active' : '' }}">
            <a href="{{ route('settings.webhooks.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 7h3a5 5 0 0 1 5 5 5 5 0 0 1-5 5h-3m-6 0H6a5 5 0 0 1-5-5 5 5 0 0 1 5-5h3"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                Webhooks
            </a>
        </li>
        <li class="{{ request()->routeIs('settings.google-sheets.*') ? 'active' : '' }}">
            <a href="{{ route('settings.google-sheets.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg>
                Google Sheets Export
            </a>
        </li>
        <li class="{{ request()->routeIs('settings.export', 'settings.export.*') ? 'active' : '' }}">
            <a href="{{ route('settings.export') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                Accounting Exports
            </a>
        </li>
        <li class="{{ request()->routeIs('settings.vat', 'settings.vat.*') ? 'active' : '' }}">
            <a href="{{ route('settings.vat') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>
                VAT Settings
            </a>
        </li>
        <li class="{{ request()->routeIs('settings.cash-float', 'settings.cash-float.*') ? 'active' : '' }}">
            <a href="{{ route('settings.cash-float') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
                Digital Float
            </a>
        </li>
        {{-- Shown to every owner: KRA's eTIMS also covers non-VAT taxpayers, so hiding
             it unless "VAT registered" was ticked left them no way to find the page. --}}
        @if($user->canActAsOwner())
        <li class="{{ request()->routeIs('settings.etims', 'settings.etims.*') ? 'active' : '' }}">
            <a href="{{ route('settings.etims') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                eTIMS (KRA)
            </a>
        </li>
        @endif
        <li class="{{ request()->routeIs('settings.subscription') ? 'active' : '' }}">
            <a href="{{ route('settings.subscription') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                Subscription
            </a>
        </li>
        @endif
    </ul>
</nav>
