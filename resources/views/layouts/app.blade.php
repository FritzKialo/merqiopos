<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') | Merqio POS</title>

    @php
        // Cache-bust assets by file mtime so deployed CSS/JS changes reach
        // users immediately instead of serving stale cached copies.
        $v = fn ($p) => asset($p) . '?v=' . (@filemtime(public_path($p)) ?: '1');
    @endphp
    <link rel="stylesheet" href="{{ $v('css/main.css') }}">
    <link rel="stylesheet" href="{{ $v('css/layout.css') }}">
    <link rel="stylesheet" href="{{ $v('css/sidebar.css') }}">
    <link rel="stylesheet" href="{{ $v('css/components.css') }}">
    {{-- The notifications bell dropdown lives in this shared layout (every
    authenticated page), so its stylesheet has to load globally too, not
    via a page-specific @push('styles') like a dedicated page would use. --}}
    <link rel="stylesheet" href="{{ $v('css/notifications.css') }}">
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
<body class="@yield('body-class')">
<script>
// Runs synchronously before the sidebar is parsed/painted, so a remembered
// "collapsed" preference applies from the very first frame instead of
// flashing full-width then snapping narrow once the deferred script below
// catches up.
try {
    if (localStorage.getItem('sidebarCollapsed') === '1') {
        document.body.classList.add('sidebar-collapsed');
    }
} catch (e) {}
</script>
<div id="pwa-install-banner" style="display:none;background:var(--color-text);color:var(--color-surface);padding:10px 20px;align-items:center;gap:12px;justify-content:space-between;font-size:0.85rem;font-family:var(--font-main);">
    <span>Install Merqio POS for faster access</span>
    <div style="display:flex;gap:8px;">
        <button onclick="installPwa()" style="background:var(--color-surface);color:var(--color-text);border:none;padding:6px 16px;border-radius:4px;cursor:pointer;font-family:var(--font-main);">Install</button>
        <button onclick="document.getElementById('pwa-install-banner').style.display='none'" style="background:transparent;color:var(--color-surface);border:1px solid var(--color-surface);padding:6px 16px;border-radius:4px;cursor:pointer;">Later</button>
    </div>
</div>

@php
    $user      = Auth::user();
    $business  = $user->currentBusiness();
    $isOwner   = $user->canActAsOwner();
    $allStores = $isOwner ? ($user->organization?->businesses ?? collect()) : collect();
@endphp

<div class="app-shell">

    {{-- ═══════════ SIDEBAR ═══════════ --}}
    <aside class="app-sidebar" id="appSidebar">

        {{-- Brand + collapse toggle. Previously the toggle was absolutely
        positioned to straddle the sidebar's right edge — there's only a
        15px gap there between .app-topbar's bottom (52px) and the store
        switcher's top (67px), too little room for a 22px button to sit in
        without visually touching one or the other (box-shadow bleed made
        it worse). Moving it into the header's own flex row sidesteps the
        problem entirely: normal layout, vertically centered with the logo,
        no absolute-position math against unrelated elements at all. --}}
        <div class="sidebar-header">
            <a href="{{ route('dashboard') }}" class="sidebar-brand">
                <span class="sidebar-brand-mark" aria-hidden="true"></span>
                <span class="sidebar-brand-name">Merqio<strong>POS</strong></span>
            </a>
            <button type="button" class="sidebar-collapse-btn" id="sidebarCollapseBtn" title="Collapse sidebar" aria-label="Collapse sidebar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
        </div>

        {{-- Store switcher --}}
        <div class="sidebar-store" id="storeSwitcherWrap">
            <button class="sidebar-store-btn" id="storeSwitcherBtn" type="button">
                <span class="sidebar-store-icon">{{ substr($business?->name ?? 'S', 0, 1) }}</span>
                <span class="sidebar-store-name">{{ $business?->name ?? 'Store' }}</span>
                @if($isOwner)<span class="sidebar-store-caret">▾</span>@endif
            </button>
            @if($isOwner)
            <div class="app-store-dropdown" id="storeSwitcherMenu">
                <div class="app-store-menu-header">
                    <a href="{{ route('org.dashboard') }}" class="app-store-overview-link">Organization Overview</a>
                </div>
                @foreach($allStores as $store)
                <form method="POST" action="{{ route('org.switch', $store) }}" style="display:contents">
                    @csrf
                    <button type="submit" class="app-store-option {{ $store->id === $business?->id ? 'active' : '' }}">
                        {{ $store->name }}
                        @if($store->id === $business?->id)<span style="margin-left:auto;font-size:11px;opacity:.6;">current</span>@endif
                    </button>
                </form>
                @endforeach
                <div class="app-store-divider"></div>
                <a href="{{ route('org.stores.create') }}" class="app-store-option app-store-add">+ Add store</a>
            </div>
            @endif
        </div>

        {{-- Primary navigation --}}
        <nav class="sidebar-nav">

            {{-- Dashboard --}}
            <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
                <span>{{ __('common.dashboard') }}</span>
            </a>

            {{-- ── SALES ── --}}
            @if($user->hasAnyRole('owner', 'overall_manager', 'manager'))
            <div class="sidebar-link-row" data-group-toggle="sales">
                <a href="{{ route('sales.index') }}" class="sidebar-link {{ request()->routeIs('sales.index','sales.show','sales.payment') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    <span>{{ __('common.sales') }}</span>
                </a>
                <button type="button" class="sidebar-group-toggle-btn" aria-label="Toggle Sales items">
                    <svg class="sidebar-group-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
            </div>
            @elseif($user->hasAnyRole('cashier'))
            <a href="{{ route('sales.create') }}" class="sidebar-link {{ request()->routeIs('sales.create') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                <span>{{ __('common.new_sale') }}</span>
            </a>
            @endif

            @if($user->hasAnyRole('owner', 'overall_manager', 'manager'))
            @if($user->hasAnyRole('owner', 'manager'))
            <a href="{{ route('sales.create') }}" class="sidebar-link {{ request()->routeIs('sales.create') ? 'active' : '' }}" data-group="sales" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>New Sale (POS)</span>
            </a>
            @endif
            <a href="{{ route('invoices.index') }}" class="sidebar-link {{ request()->routeIs('invoices*') ? 'active' : '' }}" data-group="sales" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <span>Invoices</span>
            </a>
            <a href="{{ route('returns.index') }}" class="sidebar-link {{ request()->routeIs('returns*') ? 'active' : '' }}" data-group="sales" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.53"/></svg>
                <span>Returns</span>
            </a>
            @if($business?->hasFeature('quotes'))
            <a href="{{ route('quotes.index') }}" class="sidebar-link {{ request()->routeIs('quotes*') ? 'active' : '' }}" data-group="sales" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                <span>{{ __('common.quotes') }}</span>
            </a>
            @endif
            {{-- proforma-invoices.* has always had a full working
            route/controller/view set with zero UI entry point anywhere in
            the app — reachable only by typing the URL directly. --}}
            <a href="{{ route('proforma-invoices.index') }}" class="sidebar-link {{ request()->routeIs('proforma-invoices*') ? 'active' : '' }}" data-group="sales" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                <span>Proforma Invoices</span>
            </a>
            @if($business?->hasFeature('recurring_invoices'))
            <a href="{{ route('recurring.index') }}" class="sidebar-link {{ request()->routeIs('recurring*') ? 'active' : '' }}" data-group="sales" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                <span>{{ __('common.recurring') }}</span>
            </a>
            @endif
            <a href="{{ route('shifts.index') }}" class="sidebar-link {{ request()->routeIs('shifts*') ? 'active' : '' }}" data-group="sales" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span>Shifts</span>
            </a>
            @if($business?->store_slug)
            <a href="{{ route('online-orders.index') }}" class="sidebar-link {{ request()->routeIs('online-orders*') ? 'active' : '' }}" data-group="sales" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                <span>Online Orders</span>
            </a>
            @endif
            {{-- Cash Registers had a fully working controller/routes but no
            nav link anywhere in the app. --}}
            <a href="{{ route('cash-registers.index') }}" class="sidebar-link {{ request()->routeIs('cash-registers*') ? 'active' : '' }}" data-group="sales" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
                <span>Cash Registers</span>
            </a>
            @if($user->hasAnyRole('owner', 'manager'))
            <a href="{{ route('void-requests.index') }}" class="sidebar-link {{ request()->routeIs('void-requests*') ? 'active' : '' }}" data-group="sales" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                <span>Pending Voids</span>
            </a>
            @endif
            @endif

            {{-- ── INVENTORY ── --}}
            @if($user->hasAnyRole('owner', 'overall_manager', 'manager', 'cashier'))
            <div class="sidebar-link-row" data-group-toggle="inventory">
                <a href="{{ route('inventory.index') }}" class="sidebar-link {{ request()->routeIs('inventory.index','inventory.show','inventory.create','inventory.edit') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                    <span>{{ __('common.inventory') }}</span>
                </a>
                @if($user->hasAnyRole('owner', 'overall_manager', 'manager'))
                <button type="button" class="sidebar-group-toggle-btn" aria-label="Toggle Inventory items">
                    <svg class="sidebar-group-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                @endif
            </div>
            @endif

            @if($user->hasAnyRole('owner', 'overall_manager', 'manager'))
            <a href="{{ route('inventory.adjustments') }}" class="sidebar-link {{ request()->routeIs('inventory.adjustments*') ? 'active' : '' }}" data-group="inventory" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>Adjustments</span>
            </a>
            <a href="{{ route('receives.index') }}" class="sidebar-link {{ request()->routeIs('receives*') ? 'active' : '' }}" data-group="inventory" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="8 17 12 21 16 17"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"/></svg>
                <span>Receive Stock</span>
            </a>
            {{-- Product Waitlist (notify customers when an out-of-stock item
            is back) had a fully working controller/routes but no nav link
            anywhere. --}}
            <a href="{{ route('inventory.waitlist.index') }}" class="sidebar-link {{ request()->routeIs('inventory.waitlist*') ? 'active' : '' }}" data-group="inventory" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <span>Product Waitlist</span>
            </a>
            {{-- Bundles, Warehouses, and Stock Transfers each had a fully
            working controller/routes but no nav link anywhere — same
            orphaned-feature shape found repeatedly across this app. --}}
            <a href="{{ route('bundles.index') }}" class="sidebar-link {{ request()->routeIs('bundles*') ? 'active' : '' }}" data-group="inventory" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                <span>Bundles</span>
            </a>
            <a href="{{ route('warehouses.index') }}" class="sidebar-link {{ request()->routeIs('warehouses*') ? 'active' : '' }}" data-group="inventory" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21V10l9-6 9 6v11"/><path d="M9 21v-8h6v8"/></svg>
                <span>Warehouses</span>
            </a>
            <a href="{{ route('org.transfers.index') }}" class="sidebar-link {{ request()->routeIs('org.transfers*') ? 'active' : '' }}" data-group="inventory" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                <span>Stock Transfers</span>
            </a>
            @endif

            {{-- ── CUSTOMERS ── --}}
            @if($user->hasAnyRole('owner', 'overall_manager', 'manager', 'cashier') && $business?->hasFeature('customers'))
            <a href="{{ route('customers.index') }}" class="sidebar-link {{ request()->routeIs('customers*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span>{{ __('common.customers') }}</span>
            </a>
            @endif

            {{-- ── BOOKINGS ── (Services/Appointments/Tables — no sidebar
            link existed anywhere for any of these three, despite all three
            controllers and their routes being fully functional; a business
            could never discover this area of the app at all without
            already knowing the exact URL) ── --}}
            @if($user->hasAnyRole('owner', 'overall_manager', 'manager', 'cashier'))
            <div class="sidebar-label" data-group-toggle="bookings">
                Bookings
                <svg class="sidebar-group-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <a href="{{ route('services.index') }}" class="sidebar-link {{ request()->routeIs('services*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                <span>Services</span>
            </a>
            <a href="{{ route('appointments.index') }}" class="sidebar-link {{ request()->routeIs('appointments*') ? 'active' : '' }}" data-group="bookings" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span>Appointments</span>
            </a>
            <a href="{{ route('tables.floor') }}" class="sidebar-link {{ request()->routeIs('tables*') ? 'active' : '' }}" data-group="bookings" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                <span>Tables</span>
            </a>
            @endif

            {{-- ── FINANCE ── (was the only one of these groups with no
            visible section label at all — Bookings/Purchasing/Marketing/HR
            all have one, this just fell through the same "Expenses" link
            doubling as the header instead). --}}
            @if($user->hasAnyRole('owner', 'overall_manager', 'manager'))
            <div class="sidebar-label" data-group-toggle="finance">
                Finance
                <svg class="sidebar-group-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            @if($business?->hasFeature('expenses'))
            <a href="{{ route('expenses.index') }}" class="sidebar-link {{ request()->routeIs('expenses*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                <span>{{ __('common.expenses') }}</span>
            </a>
            @endif
            <a href="{{ route('petty-cash.index') }}" class="sidebar-link {{ request()->routeIs('petty-cash*') ? 'active' : '' }}" data-group="finance" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                <span>Petty Cash</span>
            </a>
            @if($business?->enable_digital_float)
            <a href="{{ route('cash-deposits.index') }}" class="sidebar-link {{ request()->routeIs('cash-deposits*') ? 'active' : '' }}" data-group="finance" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
                <span>Cash Deposits</span>
            </a>
            @endif
            <a href="{{ route('credit-notes.index') }}" class="sidebar-link {{ request()->routeIs('credit-notes*') ? 'active' : '' }}" data-group="finance" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                <span>Credit Notes</span>
            </a>
            <a href="{{ route('bank-reconciliation.index') }}" class="sidebar-link {{ request()->routeIs('bank-reconciliation*') ? 'active' : '' }}" data-group="finance" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                <span>Reconciliation</span>
            </a>
            <a href="{{ route('reports.index') }}" class="sidebar-link {{ request()->routeIs('reports*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                <span>{{ __('common.reports') }}</span>
            </a>
            {{-- Budgets had a fully working controller/routes but no nav
            link anywhere. --}}
            <a href="{{ route('budgets.index') }}" class="sidebar-link {{ request()->routeIs('budgets*') ? 'active' : '' }}" data-group="finance" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
                <span>Budgets</span>
            </a>
            {{-- Loans, Customer Deposits, and Withholding Tax each had a
            fully working controller/routes but no nav link anywhere. --}}
            <a href="{{ route('loans.index') }}" class="sidebar-link {{ request()->routeIs('loans*') ? 'active' : '' }}" data-group="finance" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/><line x1="6" y1="15" x2="9" y2="15"/></svg>
                <span>Loans</span>
            </a>
            <a href="{{ route('customer-deposits.index') }}" class="sidebar-link {{ request()->routeIs('customer-deposits*') ? 'active' : '' }}" data-group="finance" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                <span>Customer Deposits</span>
            </a>
            <a href="{{ route('withholding-tax.index') }}" class="sidebar-link {{ request()->routeIs('withholding-tax*') ? 'active' : '' }}" data-group="finance" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>
                <span>Withholding Tax</span>
            </a>
            {{-- Fixed Assets had a fully working controller/routes (index/
            create/edit/destroy) but no nav link anywhere — not even a
            button from a related page, unlike P9 Forms/remittance reports
            which are reachable via Payroll's own action buttons. --}}
            <a href="{{ route('assets.index') }}" class="sidebar-link {{ request()->routeIs('assets*') ? 'active' : '' }}" data-group="finance" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                <span>Fixed Assets</span>
            </a>
            @if($user->isOwner())
            {{-- Audit Log — same as its route, owner-only (this was the
            gap fixed this session: the intended role:owner restriction had
            been silently overwritten by a second, unrestricted route
            registration for the same URI — see routes/features_ux.php). --}}
            <a href="{{ route('audit-log.index') }}" class="sidebar-link {{ request()->routeIs('audit-log*') ? 'active' : '' }}" data-group="finance" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/><line x1="9" y1="11" x2="15" y2="11"/></svg>
                <span>Audit Log</span>
            </a>
            <a href="{{ route('activity-log.index') }}" class="sidebar-link {{ request()->routeIs('activity-log*') ? 'active' : '' }}" data-group="finance" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                <span>Activity Log</span>
            </a>
            @endif
            @endif

            {{-- ── PURCHASING ── --}}
            @if($user->hasAnyRole('owner', 'overall_manager', 'manager') && $business?->hasFeature('suppliers'))
            <div class="sidebar-label" data-group-toggle="purchasing">
                Purchasing
                <svg class="sidebar-group-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <a href="{{ route('suppliers.index') }}" class="sidebar-link {{ request()->routeIs('suppliers*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                <span>{{ __('common.suppliers') }}</span>
            </a>
            <a href="{{ route('purchases.index') }}" class="sidebar-link {{ request()->routeIs('purchases*') ? 'active' : '' }}" data-group="purchasing" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                <span>Purchase Orders</span>
            </a>
            <a href="{{ route('delivery-notes.index') }}" class="sidebar-link {{ request()->routeIs('delivery-notes*') ? 'active' : '' }}" data-group="purchasing" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                <span>Delivery Notes</span>
            </a>
            {{-- Purchase Requisitions, Supplier Credit Notes, and Stock
            Counts each had a full working route/controller/view set with
            zero sidebar link — same orphaned-feature shape as Proforma
            Invoices/Price Tiers/Campaigns above. --}}
            <a href="{{ route('purchase-requisitions.index') }}" class="sidebar-link {{ request()->routeIs('purchase-requisitions*') ? 'active' : '' }}" data-group="purchasing" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                <span>Purchase Requisitions</span>
            </a>
            <a href="{{ route('supplier-credit-notes.index') }}" class="sidebar-link {{ request()->routeIs('supplier-credit-notes*') ? 'active' : '' }}" data-group="purchasing" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                <span>Supplier Credit Notes</span>
            </a>
            <a href="{{ route('inventory.stock-counts.index') }}" class="sidebar-link {{ request()->routeIs('inventory.stock-counts*') ? 'active' : '' }}" data-group="purchasing" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                <span>Stock Counts</span>
            </a>
            @endif

            {{-- ── MARKETING ── --}}
            @if($user->hasAnyRole('owner', 'overall_manager', 'manager'))
            <div class="sidebar-label" data-group-toggle="marketing">
                Marketing
                <svg class="sidebar-group-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <a href="{{ route('discounts.index') }}" class="sidebar-link {{ request()->routeIs('discounts*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                <span>Discounts</span>
            </a>
            <a href="{{ route('coupons.index') }}" class="sidebar-link {{ request()->routeIs('coupons*') ? 'active' : '' }}" data-group="marketing" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                <span>Coupons</span>
            </a>
            {{-- Price Tiers (customer-group pricing) had a fully working
            controller/routes but no nav link anywhere. --}}
            <a href="{{ route('price-tiers.index') }}" class="sidebar-link {{ request()->routeIs('price-tiers*') ? 'active' : '' }}" data-group="marketing" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                <span>Price Tiers</span>
            </a>
            {{-- Campaigns/Payment Links/Product Reviews had no sidebar link
            at all — undiscoverable even after routes/features_marketing.php
            was wired back up earlier this session (it had never been
            require()'d, so these were fully dead until then). --}}
            <a href="{{ route('campaigns.index') }}" class="sidebar-link {{ request()->routeIs('campaigns*') ? 'active' : '' }}" data-group="marketing" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4Z"/></svg>
                <span>Campaigns</span>
            </a>
            <a href="{{ route('payment-links.index') }}" class="sidebar-link {{ request()->routeIs('payment-links*') ? 'active' : '' }}" data-group="marketing" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                <span>Payment Links</span>
            </a>
            @if($business?->store_slug)
            <a href="{{ route('product-reviews.index') }}" class="sidebar-link {{ request()->routeIs('product-reviews*') ? 'active' : '' }}" data-group="marketing" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                <span>Product Reviews</span>
            </a>
            @endif
            @endif

            {{-- ── HR ── --}}
            @if($user->hasAnyRole('owner', 'overall_manager', 'manager') && $business?->organization?->hasFeature('payroll'))
            <div class="sidebar-label" data-group-toggle="hr">
                HR
                <svg class="sidebar-group-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <a href="{{ route('staff.index') }}" class="sidebar-link {{ request()->routeIs('staff.index','staff.show','staff.create','staff.edit') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg>
                <span>{{ __('common.staff') }}</span>
            </a>
            @if($user->canActAsOwner() || $user->hasRole('manager'))
            @if($user->canActAsOwner())
            <a href="{{ route('payroll.index') }}" class="sidebar-link {{ request()->routeIs('payroll*') ? 'active' : '' }}" data-group="hr" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                <span>{{ __('common.payroll') }}</span>
            </a>
            @endif
            <a href="{{ route('staff.leave.index') }}" class="sidebar-link {{ (request()->routeIs('staff.leave*') && !request()->boolean('mine')) ? 'active' : '' }}" data-group="hr" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span>Leave</span>
            </a>
            <a href="{{ route('staff.attendance.index') }}" class="sidebar-link {{ (request()->routeIs('staff.attendance*') && !request()->boolean('mine')) ? 'active' : '' }}" data-group="hr" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg>
                <span>Attendance</span>
            </a>
            <a href="{{ route('staff.advances.index') }}" class="sidebar-link {{ (request()->routeIs('staff.advances*') && !request()->boolean('mine')) ? 'active' : '' }}" data-group="hr" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                <span>Salary Advances</span>
            </a>
            {{-- Staff Commissions and Expense Claims: same orphaned-feature
            shape — fully working, no sidebar link anywhere. --}}
            <a href="{{ route('staff.commissions.index') }}" class="sidebar-link {{ request()->routeIs('staff.commissions*') ? 'active' : '' }}" data-group="hr" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><line x1="12" y1="6" x2="12" y2="18"/></svg>
                <span>Commissions</span>
            </a>
            <a href="{{ route('expense-claims.index') }}" class="sidebar-link {{ request()->routeIs('expense-claims*') ? 'active' : '' }}" data-group="hr" style="padding-left:2.25rem;font-size:.875rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                <span>Expense Claims</span>
            </a>
            @endif
            @endif

            {{-- ── MY WORK (cashiers / staff) ── their own leave, advances, payslips
                 and expense claims. These only existed as small links inside the
                 dashboard widget, so they were easy to miss. --}}
            @if($user->hasAnyRole('cashier', 'staff'))
            <div class="sidebar-label" style="margin-top:.75rem;">My Work</div>
            {{-- Cashiers already have access to the cash register and to their own
                 expenses (each only lists the cashier's own), but had no way to
                 reach either from the menu. --}}
            @if($user->hasRole('cashier'))
            <a href="{{ route('cash-registers.index') }}" class="sidebar-link {{ request()->routeIs('cash-registers*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
                <span>Cash Register</span>
            </a>
            @if($business?->hasFeature('expenses'))
            <a href="{{ route('expenses.index') }}" class="sidebar-link {{ request()->routeIs('expenses*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                <span>My Expenses</span>
            </a>
            @endif
            @endif
            <a href="{{ route('staff.leave.index', ['mine' => 1]) }}" class="sidebar-link {{ request()->routeIs('staff.leave.*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span>My Leave</span>
            </a>
            <a href="{{ route('staff.advances.index', ['mine' => 1]) }}" class="sidebar-link {{ request()->routeIs('staff.advances.*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                <span>My Advances</span>
            </a>
            @if($business?->organization?->hasFeature('payroll'))
            <a href="{{ route('payroll.payslips.mine') }}" class="sidebar-link {{ request()->routeIs('payroll.payslips.mine') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <span>My Payslips</span>
            </a>
            @endif
            <a href="{{ route('expense-claims.index') }}" class="sidebar-link {{ request()->routeIs('expense-claims.*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                <span>My Expense Claims</span>
            </a>
            @endif

            {{-- ── HELP (everyone) ── --}}
            <div class="sidebar-label" style="margin-top:.75rem;">Help</div>
            <a href="{{ route('menu') }}" class="sidebar-link {{ request()->routeIs('menu') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <span>Home Menu</span>
            </a>
            <a href="{{ route('guide') }}" class="sidebar-link {{ request()->routeIs('guide') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <span>Getting Started Guide</span>
            </a>

        </nav>

        {{-- Sidebar footer --}}
        <div class="sidebar-foot">
            @if($user->hasAnyRole('owner', 'overall_manager', 'manager'))
            @php $navOrg = $user->organization ?? $business?->organization; @endphp
            @if($navOrg)
            <div class="sidebar-plan">
                {{-- planName() never actually returns null (always "Trial" or a
                real plan name) — the fallback was dead code, and "Free" is a
                defunct tier since the pricing restructure, so it's removed
                rather than left to mislead a future reader. --}}
                <span class="sidebar-plan-name">{{ $navOrg->planName() }} plan</span>
                @if($user->isOwner() && $navOrg->upgradePlan())
                <a href="{{ route('settings.subscription') }}" class="sidebar-plan-upgrade">Upgrade</a>
                @endif
            </div>
            @endif
            @endif
        </div>
    </aside>

    <script>
    // Every link click is a full page reload (no SPA routing here), which
    // resets the sidebar's own scroll position to the top every time —
    // annoying on a sidebar this long when the active section is scrolled
    // halfway down. Restored here, synchronously, as soon as the sidebar's
    // own markup is parsed (before the rest of the page / images finish
    // loading), so there's no visible jump-then-snap-back.
    (function () {
        var sidebar = document.getElementById('appSidebar');
        if (!sidebar) return;

        try {
            var saved = sessionStorage.getItem('sidebarScrollTop');
            if (saved !== null) sidebar.scrollTop = parseInt(saved, 10) || 0;
        } catch (e) {}

        var saveTimer = null;
        sidebar.addEventListener('scroll', function () {
            clearTimeout(saveTimer);
            saveTimer = setTimeout(function () {
                try { sessionStorage.setItem('sidebarScrollTop', sidebar.scrollTop); } catch (e) {}
            }, 100);
        });
    })();
    </script>

    <script>
    // Collapsible sidebar sections (Sales/Inventory/Finance/Bookings/
    // Purchasing/Marketing/HR) — this sidebar has 25+ links when every
    // group is expanded, most of which nobody touches day to day.
    // Per-group state lives in localStorage (so it persists across visits,
    // unlike the sessionStorage scroll-position above) — but a group
    // containing the CURRENT page is always forced open regardless of its
    // saved state, otherwise landing on a page you navigated to could hide
    // its own nav item.
    (function () {
        var nav = document.querySelector('.sidebar-nav');
        if (!nav) return;

        var ALL_GROUPS = ['sales', 'inventory', 'finance', 'bookings', 'purchasing', 'marketing', 'hr'];
        var STORAGE_KEY = 'sidebarCollapsedGroups';

        // null = no preference saved yet (first visit ever) → default to
        // everything expanded, so a new user can see every feature the app
        // has without hunting for chevrons. Once a person touches any
        // chevron, an actual array gets saved (even an empty one) and that
        // explicit choice is respected from then on instead of re-defaulting.
        function getSavedCollapsed() {
            try {
                var raw = localStorage.getItem(STORAGE_KEY);
                return raw === null ? null : JSON.parse(raw);
            } catch (e) { return null; }
        }

        function groupsContainingActiveLink() {
            var found = [];
            ALL_GROUPS.forEach(function (g) {
                if (nav.querySelector('[data-group="' + g + '"] .active, [data-group="' + g + '"].sidebar-link.active')) {
                    found.push(g);
                }
            });
            return found;
        }

        var savedCollapsed = getSavedCollapsed();
        var collapsed = (savedCollapsed === null ? [] : savedCollapsed).filter(function (g) {
            return ALL_GROUPS.indexOf(g) !== -1;
        });
        var mustStayOpen = groupsContainingActiveLink();
        collapsed = collapsed.filter(function (g) { return mustStayOpen.indexOf(g) === -1; });

        nav.setAttribute('data-collapsed', collapsed.join(' '));

        nav.addEventListener('click', function (e) {
            var trigger = e.target.closest('[data-group-toggle]');
            if (!trigger) return;

            // Sales/Inventory's trigger row also contains a real link — only
            // the chevron button (or the row itself away from the <a>) should
            // toggle; a click that landed on the link must navigate normally.
            if (e.target.closest('a')) return;

            e.preventDefault();
            var group = trigger.getAttribute('data-group-toggle');
            var current = (nav.getAttribute('data-collapsed') || '').split(' ').filter(Boolean);
            var idx = current.indexOf(group);
            if (idx === -1) current.push(group); else current.splice(idx, 1);

            nav.setAttribute('data-collapsed', current.join(' '));
            try { localStorage.setItem(STORAGE_KEY, JSON.stringify(current)); } catch (err) {}
        });
    })();
    </script>

    {{-- backdrop for mobile sidebar --}}
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    {{-- ═══════════ MAIN ═══════════ --}}
    <div class="app-main">

        {{-- Top bar --}}
        <header class="app-topbar">
            <button class="topbar-menu-btn" id="menuToggle" aria-label="Menu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <h1 class="topbar-title">@yield('title', __('common.dashboard'))</h1>

            <div class="topbar-actions">
                @auth
                @php
                    // Was a link to a dedicated /notifications page — that
                    // was more than this feature needs. Now the bell opens
                    // a compact dropdown right here with the same data,
                    // no extra page, no navigation away from wherever the
                    // user currently is.
                    $__navBizId = Auth::user()->currentBusiness()?->id;
                    $__navNotifQuery = $__navBizId
                        ? \App\Models\AppNotification::where('user_id', Auth::id())->where('business_id', $__navBizId)
                        : null;
                    $unreadNotifs = $__navNotifQuery ? (clone $__navNotifQuery)->whereNull('read_at')->count() : 0;
                    $navNotifications = $__navNotifQuery ? (clone $__navNotifQuery)->latest()->limit(8)->get() : collect();
                    $navNotifIcons = [
                        'bell'         => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
                        'warning'      => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
                        'money'        => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
                        'dollar-sign'  => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
                        'package'      => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
                        'people'       => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
                        'check-circle' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
                        'x-circle'     => '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
                        'truck'        => '<rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
                        'megaphone'    => '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
                        'newspaper'    => '<path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1 1-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8V6Z"/>',
                    ];
                @endphp
                <div class="notif-dropdown-wrap" id="notifDropdownWrap">
                    <button type="button" class="topbar-icon-btn" id="notifDropdownBtn" title="Notifications" aria-label="Notifications" aria-haspopup="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        @if($unreadNotifs > 0)<span class="topbar-badge" id="notifBadge">{{ $unreadNotifs > 9 ? '9+' : $unreadNotifs }}</span>@endif
                    </button>
                    <div class="notif-dropdown-panel" id="notifDropdownPanel">
                        <div class="notif-dropdown-header">
                            <span>Notifications</span>
                            @if($unreadNotifs > 0)
                            <button type="button" class="notif-mark-all-btn" id="notifMarkAllBtn" data-url="{{ route('notifications.read-all') }}">Mark all read</button>
                            @endif
                        </div>
                        <div class="notif-dropdown-list" id="notifDropdownList">
                            @forelse($navNotifications as $notif)
                            @php $accent = $notif->accentColor(); @endphp
                            <div class="notif-row notif-{{ $accent }} {{ $notif->isRead() ? 'is-read' : 'is-unread' }}">
                                <div class="notif-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $navNotifIcons[$notif->icon] ?? $navNotifIcons['bell'] !!}</svg>
                                </div>
                                <div class="notif-body">
                                    <div class="notif-title-row">
                                        @if(!$notif->isRead())<span class="notif-dot" aria-hidden="true"></span>@endif
                                        <span class="notif-title">{{ $notif->title }}</span>
                                    </div>
                                    <div class="notif-message">{{ $notif->message }}</div>
                                    <div class="notif-meta">
                                        <span class="notif-time">{{ $notif->created_at->diffForHumans() }}</span>
                                        @if($notif->action_url)
                                            <a href="{{ $notif->action_url }}" class="notif-view-link">View &rarr;</a>
                                        @endif
                                    </div>
                                </div>
                                <div class="notif-actions">
                                    @if(!$notif->isRead())
                                    <button type="button" class="notif-action-btn notif-mark-read-btn" data-url="{{ route('notifications.read', $notif->id) }}" title="Mark as read">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    </button>
                                    @endif
                                    <button type="button" class="notif-action-btn notif-action-danger notif-delete-btn" data-url="{{ route('notifications.destroy', $notif->id) }}" title="Delete">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    </button>
                                </div>
                            </div>
                            @empty
                            <div class="notif-dropdown-empty">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                                <p>No notifications yet</p>
                            </div>
                            @endforelse
                        </div>
                    </div>
                </div>
                @endauth

                <div class="topbar-lang">
                    <a href="{{ route('language.switch', 'en') }}" class="{{ app()->getLocale()==='en'?'active':'' }}">EN</a>
                    <a href="{{ route('language.switch', 'sw') }}" class="{{ app()->getLocale()==='sw'?'active':'' }}">SW</a>
                </div>

                <div class="app-nav-user" id="userMenuWrap">
                    <div class="app-nav-avatar">{{ substr($user->name, 0, 1) }}</div>
                    <div class="app-nav-user-dropdown" id="userMenuDropdown">
                        <div class="app-nav-user-header">
                            <span class="app-nav-user-name">{{ $user->name }}</span>
                            <span class="app-nav-user-role">{{ ucfirst(str_replace('_',' ',$user->role)) }}</span>
                        </div>
                        @if($user->canActAsOwner())
                        <a href="{{ route('org.dashboard') }}" class="app-nav-dropdown-item">Organization</a>
                        <div class="app-nav-dropdown-divider"></div>
                        <a href="{{ route('settings.business') }}" class="app-nav-dropdown-item">Settings</a>
                        @elseif($user->hasRole('manager'))
                        <a href="{{ route('settings.team') }}" class="app-nav-dropdown-item">Team Members</a>
                        <a href="{{ route('settings.password') }}" class="app-nav-dropdown-item">Settings</a>
                        @else
                        <a href="{{ route('settings.password') }}" class="app-nav-dropdown-item">Settings</a>
                        @endif
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

        {{-- Page content --}}
        <main class="main-content">
            @include('partials.demo-banner')
            @if($business?->isLockedByPlan())
            <div class="alert alert-warning" style="margin-bottom: var(--space-4);">
                This store is <strong>read-only</strong> — your organisation's plan was
                downgraded below its store count and this store wasn't one you kept active.
                <a href="{{ route('settings.subscription') }}" style="font-weight:600;">Upgrade your plan</a>
                or
                <a href="{{ route('org.stores.select-active') }}" style="font-weight:600;">change which stores are active</a>
                to resume making changes here.
            </div>
            @endif
            @yield('content')
        </main>
    </div>
</div>

{{-- Toast --}}
<div id="toast-container" class="toast-container"
    data-success="{{ session('success') }}"
    data-error="{{ session('error') }}"
    data-info="{{ session('info') }}"
    data-warning="{{ session('warning') }}">
</div>

<script>
// Store switcher
(function() {
    var btn  = document.getElementById('storeSwitcherBtn');
    var menu = document.getElementById('storeSwitcherMenu');
    if (!btn || !menu) return;
    btn.addEventListener('click', function(e) { e.stopPropagation(); menu.classList.toggle('open'); });
    document.addEventListener('click', function() { menu.classList.remove('open'); });
})();

// User dropdown
(function() {
    var wrap = document.getElementById('userMenuWrap');
    var menu = document.getElementById('userMenuDropdown');
    if (!wrap || !menu) return;
    wrap.addEventListener('click', function(e) { e.stopPropagation(); menu.classList.toggle('open'); });
    document.addEventListener('click', function() { menu.classList.remove('open'); });
})();

// Notifications dropdown — replaces the old dedicated /notifications page.
// Mark-read/delete/mark-all-read hit the same endpoints that page used;
// they're called here with fetch() so the panel updates in place instead
// of navigating or reloading.
(function() {
    var btn   = document.getElementById('notifDropdownBtn');
    var panel = document.getElementById('notifDropdownPanel');
    var list  = document.getElementById('notifDropdownList');
    if (!btn || !panel || !list) return;

    btn.addEventListener('click', function(e) { e.stopPropagation(); panel.classList.toggle('open'); });
    document.addEventListener('click', function() { panel.classList.remove('open'); });
    panel.addEventListener('click', function(e) { e.stopPropagation(); });

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    function post(url, method) {
        return fetch(url, {
            method: method,
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
    }

    function updateBadge(delta) {
        var badge = document.getElementById('notifBadge');
        if (!badge) return;
        var next = (parseInt(badge.textContent, 10) || 0) + delta;
        if (next <= 0) { badge.remove(); }
        else { badge.textContent = next > 9 ? '9+' : next; }
    }

    function showEmptyIfNoneLeft() {
        if (list.querySelector('.notif-row')) return;
        list.innerHTML = '<div class="notif-dropdown-empty">'
            + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>'
            + '<p>No notifications yet</p></div>';
    }

    list.addEventListener('click', function(e) {
        var markBtn = e.target.closest('.notif-mark-read-btn');
        var delBtn  = e.target.closest('.notif-delete-btn');

        if (markBtn) {
            e.preventDefault();
            var row = markBtn.closest('.notif-row');
            post(markBtn.dataset.url, 'POST').then(function(r) {
                if (!r.ok || !row) return;
                row.classList.remove('is-unread');
                row.classList.add('is-read');
                var dot = row.querySelector('.notif-dot');
                if (dot) dot.remove();
                markBtn.remove();
                updateBadge(-1);
            });
        }

        if (delBtn) {
            e.preventDefault();
            var row2 = delBtn.closest('.notif-row');
            var wasUnread = row2 && row2.classList.contains('is-unread');
            post(delBtn.dataset.url, 'DELETE').then(function(r) {
                if (!r.ok) return;
                if (row2) row2.remove();
                if (wasUnread) updateBadge(-1);
                showEmptyIfNoneLeft();
            });
        }
    });

    var markAllBtn = document.getElementById('notifMarkAllBtn');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            post(markAllBtn.dataset.url, 'POST').then(function(r) {
                if (!r.ok) return;
                list.querySelectorAll('.notif-row.is-unread').forEach(function(row) {
                    row.classList.remove('is-unread');
                    row.classList.add('is-read');
                    var dot = row.querySelector('.notif-dot');
                    if (dot) dot.remove();
                    var mb = row.querySelector('.notif-mark-read-btn');
                    if (mb) mb.remove();
                });
                var badge = document.getElementById('notifBadge');
                if (badge) badge.remove();
                markAllBtn.remove();
            });
        });
    }
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
    sidebar.querySelectorAll('.sidebar-link, .app-store-option').forEach(function(el) {
        el.addEventListener('click', close);
    });
})();

// Sidebar collapse / expand (desktop) — remembered per browser via
// localStorage so it stays put across page loads, same as any real app.
// The 'sidebar-collapsed' class itself is applied to <body> by the inline
// script at the top of <head> (before first paint); this just wires up the
// toggle button and keeps tooltips/title text in sync with that state.
(function() {
    var btn     = document.getElementById('sidebarCollapseBtn');
    var sidebar = document.getElementById('appSidebar');
    if (!btn || !sidebar) return;

    function applyTooltips(collapsed) {
        sidebar.querySelectorAll('.sidebar-link').forEach(function(link) {
            var label = link.querySelector('span');
            if (!label) return;
            if (collapsed) link.setAttribute('title', label.textContent.trim());
            else link.removeAttribute('title');
        });
    }

    var collapsed = document.body.classList.contains('sidebar-collapsed');
    btn.setAttribute('title', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
    if (collapsed) applyTooltips(true);

    btn.addEventListener('click', function() {
        var isCollapsed = document.body.classList.toggle('sidebar-collapsed');
        try { localStorage.setItem('sidebarCollapsed', isCollapsed ? '1' : '0'); } catch (e) {}
        btn.setAttribute('title', isCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
        applyTooltips(isCollapsed);
    });
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
