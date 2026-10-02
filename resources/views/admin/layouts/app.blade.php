<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — Merqio POS</title>

    @php $v = fn ($p) => asset($p) . '?v=' . (@filemtime(public_path($p)) ?: '1'); @endphp
    <link rel="stylesheet" href="{{ asset('fonts/phosphor/phosphor-bold.css') }}">
    <link rel="stylesheet" href="{{ $v('css/main.css') }}">
    <link rel="stylesheet" href="{{ $v('css/components.css') }}">
    <link rel="stylesheet" href="{{ $v('css/admin.css') }}">

    @stack('styles')
</head>
<body class="admin-body">

{{-- ── Mobile header ── --}}
<header class="admin-mobile-header" id="adminMobileHeader">
    <span class="admin-mobile-brand">Admin Panel</span>
    <button class="admin-menu-toggle" id="adminMenuToggle" aria-label="Open menu">
        <i class="ph-bold ph-list"></i>
    </button>
</header>

{{-- ── Mobile drawer ── --}}
<div class="admin-mobile-drawer" id="adminMobileDrawer">
    <a href="{{ route('admin.dashboard') }}"
       class="admin-topnav-tab {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
        <i class="ph-bold ph-squares-four"></i> Dashboard
    </a>
    <a href="{{ route('admin.organizations.index') }}"
       class="admin-topnav-tab {{ request()->routeIs('admin.organizations.*') ? 'active' : '' }}">
        <i class="ph-bold ph-buildings"></i> Organizations
    </a>
    <a href="{{ route('admin.subscriptions.index') }}"
       class="admin-topnav-tab {{ request()->routeIs('admin.subscriptions.*') ? 'active' : '' }}">
        <i class="ph-bold ph-credit-card"></i> Subscriptions
    </a>
    <a href="{{ route('admin.promo-codes.index') }}"
       class="admin-topnav-tab {{ request()->routeIs('admin.promo-codes.*') ? 'active' : '' }}">
        <i class="ph-bold ph-ticket"></i> Promo Codes
    </a>
    <a href="{{ route('admin.newsletters.index') }}"
       class="admin-topnav-tab {{ request()->routeIs('admin.newsletters.*') ? 'active' : '' }}">
        <i class="ph-bold ph-newspaper-clipping"></i> Newsletters
    </a>
    <a href="{{ route('admin.support.index') }}"
       class="admin-topnav-tab {{ request()->routeIs('admin.support.*') ? 'active' : '' }}">
        <i class="ph-bold ph-chats-circle"></i> Support <span class="sp-nav-badge" style="display:none;background:#ef4444;color:#fff;border-radius:999px;font-size:11px;font-weight:700;padding:1px 7px;margin-left:4px;"></span>
    </a>
    <a href="{{ route('admin.logs.index') }}"
       class="admin-topnav-tab {{ request()->routeIs('admin.logs.*') ? 'active' : '' }}">
        <i class="ph-bold ph-bug"></i> Logs
    </a>
    <a href="{{ route('admin.password') }}"
       class="admin-topnav-tab {{ request()->routeIs('admin.password') ? 'active' : '' }}">
        <i class="ph-bold ph-gear"></i> Security
    </a>

    <div class="admin-mobile-drawer-footer">
        <a href="{{ route('dashboard') }}" class="admin-topnav-tab">
            <i class="ph-bold ph-arrow-square-out"></i> Back to App
        </a>
        <form method="POST" action="{{ route('logout') }}" style="display:contents">
            @csrf
            <button type="submit" class="admin-topnav-tab" style="background:none;border:none;cursor:pointer;width:100%;text-align:left;">
                <i class="ph-bold ph-sign-out"></i> Logout
            </button>
        </form>
    </div>
</div>

{{-- ── Top Nav ── --}}
<nav class="admin-topnav">
    <a href="{{ route('admin.dashboard') }}" class="admin-topnav-brand">
        <div class="admin-topnav-brand-icon"><i class="ph-bold ph-lightning"></i></div>
        Merqio Admin
    </a>

    <div class="admin-topnav-tabs">
        <a href="{{ route('admin.dashboard') }}"
           class="admin-topnav-tab {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <i class="ph-bold ph-squares-four"></i> Dashboard
        </a>
        <a href="{{ route('admin.organizations.index') }}"
           class="admin-topnav-tab {{ request()->routeIs('admin.organizations.*') ? 'active' : '' }}">
            <i class="ph-bold ph-buildings"></i> Organizations
        </a>
        <a href="{{ route('admin.subscriptions.index') }}"
           class="admin-topnav-tab {{ request()->routeIs('admin.subscriptions.*') ? 'active' : '' }}">
            <i class="ph-bold ph-credit-card"></i> Subscriptions
        </a>
        <a href="{{ route('admin.promo-codes.index') }}"
           class="admin-topnav-tab {{ request()->routeIs('admin.promo-codes.*') ? 'active' : '' }}">
            <i class="ph-bold ph-ticket"></i> Promo Codes
        </a>
        <a href="{{ route('admin.newsletters.index') }}"
           class="admin-topnav-tab {{ request()->routeIs('admin.newsletters.*') ? 'active' : '' }}">
            <i class="ph-bold ph-newspaper-clipping"></i> Newsletters
        </a>
        <a href="{{ route('admin.support.index') }}"
           class="admin-topnav-tab {{ request()->routeIs('admin.support.*') ? 'active' : '' }}">
            <i class="ph-bold ph-chats-circle"></i> Support <span class="sp-nav-badge" style="display:none;background:#ef4444;color:#fff;border-radius:999px;font-size:11px;font-weight:700;padding:1px 7px;margin-left:4px;"></span>
        </a>
        <a href="{{ route('admin.logs.index') }}"
           class="admin-topnav-tab {{ request()->routeIs('admin.logs.*') ? 'active' : '' }}">
            <i class="ph-bold ph-bug"></i> Logs
        </a>
        <a href="{{ route('admin.password') }}"
           class="admin-topnav-tab {{ request()->routeIs('admin.password') ? 'active' : '' }}">
            <i class="ph-bold ph-gear"></i> Security
        </a>
    </div>

    <div class="admin-topnav-right">
        <div class="admin-topnav-user" id="adminUserMenu">
            <div class="admin-topnav-avatar">{{ substr(auth()->user()->name, 0, 1) }}</div>
            <span class="admin-topnav-user-name">{{ auth()->user()->name }}</span>
            <i class="ph-bold ph-caret-down" style="font-size:12px; color:#888;"></i>

            <div class="admin-user-dropdown" id="adminUserDropdown">
                <a href="{{ route('dashboard') }}" class="admin-dropdown-item">
                    <i class="ph-bold ph-arrow-square-out"></i> Back to App
                </a>
                <a href="{{ route('admin.password') }}" class="admin-dropdown-item">
                    <i class="ph-bold ph-lock-key"></i> Change Password
                </a>
                <div class="admin-dropdown-divider"></div>
                <form method="POST" action="{{ route('logout') }}" style="display:contents">
                    @csrf
                    <button type="submit" class="admin-dropdown-item">
                        <i class="ph-bold ph-sign-out"></i> Logout
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>

{{-- ── Main ── --}}
<main class="admin-main">

    @if(session('success'))
        <div class="admin-alert admin-alert-success">
            <i class="ph-bold ph-check-circle"></i>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="admin-alert admin-alert-error">
            <i class="ph-bold ph-warning-circle"></i>
            {{ session('error') }}
        </div>
    @endif

    <div class="admin-content">
        @yield('content')
    </div>

</main>

<script>
    // Auto-dismiss alerts
    document.querySelectorAll('.admin-alert').forEach(function(el) {
        setTimeout(function() {
            el.style.opacity = '0';
            el.style.transform = 'translateY(-6px)';
            setTimeout(function() { el.remove(); }, 300);
        }, 4000);
    });

    // Avatar dropdown
    (function() {
        var trigger  = document.getElementById('adminUserMenu');
        var dropdown = document.getElementById('adminUserDropdown');
        if (!trigger || !dropdown) return;
        trigger.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('open');
        });
        document.addEventListener('click', function() {
            dropdown.classList.remove('open');
        });
    })();

    // Mobile drawer
    (function() {
        var toggle = document.getElementById('adminMenuToggle');
        var drawer = document.getElementById('adminMobileDrawer');
        if (!toggle || !drawer) return;
        toggle.addEventListener('click', function() {
            drawer.classList.toggle('open');
        });
        drawer.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', function() { drawer.classList.remove('open'); });
        });
    })();
</script>

@stack('scripts')
<script>
(function () {
    // New support messages: a red count on the Support tab and in the browser tab title.
    var base = document.title, url = @json(route('admin.support.unread'));
    function tick() {
        if (document.hidden) return;
        fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) {
            if (!d) return;
            document.querySelectorAll('.sp-nav-badge').forEach(function (b) { b.textContent = d.unread; b.style.display = d.unread > 0 ? 'inline-block' : 'none'; });
            document.title = (d.unread > 0 ? '(' + d.unread + ') ' : '') + base.replace(/^\(\d+\)\s*/, '');
        }).catch(function () {});
    }
    tick(); setInterval(tick, 30000);
    document.addEventListener('visibilitychange', tick);
})();
</script>
</body>
</html>
