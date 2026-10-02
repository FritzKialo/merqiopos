<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Overview') | Merqio POS</title>

    @php $v = fn ($p) => asset($p) . '?v=' . (@filemtime(public_path($p)) ?: '1'); @endphp
    <link rel="stylesheet" href="{{ asset('fonts/phosphor/phosphor-bold.css') }}">
    <link rel="stylesheet" href="{{ $v('css/main.css') }}">
    <link rel="stylesheet" href="{{ $v('css/layout.css') }}">
    <link rel="stylesheet" href="{{ $v('css/sidebar.css') }}">
    <link rel="stylesheet" href="{{ $v('css/components.css') }}">
    <link rel="stylesheet" href="{{ $v('css/responsive.css') }}">
    <link rel="stylesheet" href="{{ $v('css/org.css') }}">
    <!-- PWA -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#ffffff">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="Merqio POS">
    @include('layouts.partials.brand-icons')
    @stack('styles')
</head>
<body>

@php $organization = Auth::user()->organization; @endphp

<div class="app-shell">

    {{-- ═══════════ SIDEBAR ═══════════ --}}
    <aside class="app-sidebar" id="appSidebar">

        <a href="{{ route('org.dashboard') }}" class="sidebar-brand">
            <span class="sidebar-brand-mark" aria-hidden="true"></span>
            <span class="sidebar-brand-name">Merqio<strong>POS</strong></span>
        </a>

        <div class="sidebar-store">
            <div class="sidebar-store-btn" style="cursor:default;">
                <span class="sidebar-store-icon" style="background:#6366f1;">{{ substr($organization?->name ?? 'O', 0, 1) }}</span>
                <span class="sidebar-store-name">{{ $organization?->name ?? 'Organisation' }}</span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <a href="{{ route('org.dashboard') }}" class="sidebar-link {{ request()->routeIs('org.dashboard') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
                <span>{{ __('common.overview') }}</span>
            </a>
            <a href="{{ route('org.stores') }}" class="sidebar-link {{ request()->routeIs('org.stores*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l1-5h16l1 5"/><path d="M5 9v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9"/><path d="M3 9h18"/><path d="M9 21v-6h6v6"/></svg>
                <span>{{ __('common.stores') }}</span>
            </a>
            <a href="{{ route('org.transfers.index') }}" class="sidebar-link {{ request()->routeIs('org.transfers*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                <span>Stock Transfers</span>
            </a>
            <a href="{{ route('payroll.index') }}" class="sidebar-link {{ request()->routeIs('payroll*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                <span>{{ __('common.payroll') }}</span>
            </a>
            <a href="{{ route('reports.index') }}" class="sidebar-link {{ request()->routeIs('reports*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                <span>{{ __('common.reports') }}</span>
            </a>
            <a href="{{ route('settings.business') }}" class="sidebar-link {{ request()->routeIs('settings*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                <span>{{ __('common.settings') }}</span>
            </a>

            <div class="sidebar-label">Store</div>
            <a href="{{ route('dashboard') }}" class="sidebar-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                <span>Go to Store</span>
            </a>
        </nav>

        <div class="sidebar-foot">
            @if($organization)
            <div class="sidebar-plan">
                {{-- planName() never actually returns null (always "Trial" or a
                real plan name) — the fallback was dead code, and "Free" is a
                defunct tier since the pricing restructure, so it's removed
                rather than left to mislead a future reader. --}}
                <span class="sidebar-plan-name">{{ $organization->planName() }} plan</span>
                @if($organization->upgradePlan())
                <a href="{{ route('settings.subscription') }}" class="sidebar-plan-upgrade">Upgrade</a>
                @endif
            </div>
            @endif
        </div>
    </aside>

    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    {{-- ═══════════ MAIN ═══════════ --}}
    <div class="app-main">
        <header class="app-topbar">
            <button class="topbar-menu-btn" id="menuToggle" aria-label="Menu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <h1 class="topbar-title">@yield('title', __('common.overview'))</h1>

            <div class="topbar-actions">
                <div class="topbar-lang">
                    <a href="{{ route('language.switch', 'en') }}" class="{{ app()->getLocale()==='en'?'active':'' }}">EN</a>
                    <a href="{{ route('language.switch', 'sw') }}" class="{{ app()->getLocale()==='sw'?'active':'' }}">SW</a>
                </div>
                <div class="app-nav-user" id="userMenuWrap">
                    <div class="app-nav-avatar">{{ substr(Auth::user()->name, 0, 1) }}</div>
                    <div class="app-nav-user-dropdown" id="userMenuDropdown">
                        <div class="app-nav-user-header">
                            <span class="app-nav-user-name">{{ Auth::user()->name }}</span>
                            <span class="app-nav-user-role">{{ ucfirst(str_replace('_',' ', Auth::user()->role)) }}</span>
                        </div>
                        <a href="{{ route('dashboard') }}" class="app-nav-dropdown-item">Go to Store</a>
                        <a href="{{ route('settings.business') }}" class="app-nav-dropdown-item">{{ __('common.settings') }}</a>
                        <a href="{{ route('settings.2fa.setup') }}" class="app-nav-dropdown-item">Two-Factor Auth</a>
                        <div class="app-nav-dropdown-divider"></div>
                        <form method="POST" action="{{ route('logout') }}" style="display:contents">
                            @csrf
                            <button type="submit" class="app-nav-dropdown-item">Logout</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="main-content">
            @include('partials.demo-banner')
            @yield('content')
        </main>
    </div>
</div>

<div id="toast-container" class="toast-container"
    data-success="{{ session('success') }}"
    data-error="{{ session('error') }}"
    data-info="{{ session('info') }}"
    data-warning="{{ session('warning') }}">
</div>

<script>
// User dropdown
(function() {
    var wrap = document.getElementById('userMenuWrap');
    var menu = document.getElementById('userMenuDropdown');
    if (!wrap || !menu) return;
    wrap.addEventListener('click', function(e) { e.stopPropagation(); menu.classList.toggle('open'); });
    document.addEventListener('click', function() { menu.classList.remove('open'); });
})();

// Mobile sidebar
(function() {
    var toggle   = document.getElementById('menuToggle');
    var sidebar  = document.getElementById('appSidebar');
    var backdrop = document.getElementById('sidebarBackdrop');
    if (!toggle || !sidebar) return;
    function open()  { sidebar.classList.add('open'); if(backdrop) backdrop.classList.add('active'); }
    function close() { sidebar.classList.remove('open'); if(backdrop) backdrop.classList.remove('active'); }
    toggle.addEventListener('click', function(e){ e.stopPropagation(); open(); });
    if (backdrop) backdrop.addEventListener('click', close);
    sidebar.querySelectorAll('.sidebar-link').forEach(function(el) { el.addEventListener('click', close); });
})();
</script>

<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/sw.js').then(function(reg) {
            console.log('SW registered');
        }).catch(function(err) {
            console.log('SW registration failed:', err);
        });
    });
}
</script>
@include('partials.support-widget')
</body>
</html>
