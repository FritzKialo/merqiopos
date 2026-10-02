<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Customer Portal') — {{ $portalBusiness->name ?? config('app.name') }}</title>
    <style>
        :root {
            --color-surface:    #fff;
            --color-border:     #e0e0e0;
            --color-text:       #111;
            --color-text-muted: #666;
            --color-success:    #16a34a;
            --color-warning:    #ca8a04;
            --color-danger:     #dc2626;
            --color-primary:    #000;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #eef1f5;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: var(--color-text);
            font-size: 15px;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        .portal-tile { box-shadow: 0 1px 3px rgba(0,0,0,0.06); transition: transform .14s ease, box-shadow .14s ease; }
        .portal-tile:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.10); border-color: #cfcfcf; }

        /* Nav */
        .portal-nav {
            background: var(--color-primary);
            color: #fff;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .portal-nav-inner {
            max-width: 900px;
            margin: 0 auto;
            padding: 0 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 52px;
        }
        .portal-brand {
            font-size: 1.1rem;
            font-weight: bold;
            color: #fff;
            text-decoration: none;
        }
        .portal-nav-links {
            display: flex;
            gap: 1.5rem;
            list-style: none;
            align-items: center;
        }
        .portal-nav-links a {
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.15s;
        }
        .portal-nav-links a:hover,
        .portal-nav-links a.active { color: #fff; }
        .portal-nav-links .nav-separator {
            width: 1px;
            height: 20px;
            background: rgba(255,255,255,0.3);
        }
        /* Dropdown */
        .portal-dropdown { position: relative; }
        .portal-dropdown-toggle {
            background: none;
            border: none;
            color: rgba(255,255,255,0.85);
            font: inherit;
            font-size: 0.9rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .portal-dropdown-toggle:hover { color: #fff; }
        .portal-dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 6px;
            min-width: 160px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
            z-index: 200;
        }
        .portal-dropdown:hover .portal-dropdown-menu { display: block; }
        .portal-dropdown-menu a,
        .portal-dropdown-menu button {
            display: block;
            width: 100%;
            padding: 0.6rem 1rem;
            color: var(--color-text);
            text-decoration: none;
            font-size: 0.9rem;
            background: none;
            border: none;
            text-align: left;
            cursor: pointer;
            font-family: inherit;
        }
        .portal-dropdown-menu a:hover,
        .portal-dropdown-menu button:hover { background: #f5f5f5; }
        /* Notification bell */
        .portal-notif-toggle { position: relative; background: none; border: none; color: rgba(255,255,255,0.85); cursor: pointer; display: flex; align-items: center; padding: 0; }
        .portal-notif-toggle:hover { color: #fff; }
        .portal-notif-toggle svg { width: 20px; height: 20px; }
        .portal-notif-badge { position: absolute; top: -6px; right: -8px; background: var(--color-danger); color: #fff; font-size: 10px; font-weight: 700; line-height: 1; padding: 3px 5px; border-radius: 999px; min-width: 16px; text-align: center; }
        .portal-notif-menu { width: 320px; max-width: 88vw; max-height: 420px; overflow-y: auto; }
        .portal-notif-header { display: flex; align-items: center; justify-content: space-between; padding: 0.7rem 1rem; border-bottom: 1px solid var(--color-border); font-size: 0.85rem; font-weight: bold; }
        .portal-notif-header button { background: none; border: none; color: #2563eb; font-size: 0.78rem; cursor: pointer; padding: 0; font-family: inherit; }
        .portal-notif-row { display: flex; gap: 8px; padding: 0.7rem 1rem; border-bottom: 1px solid var(--color-border); }
        .portal-notif-row:last-child { border-bottom: none; }
        .portal-notif-row.is-unread { background: #f8fafc; }
        .portal-notif-icon { flex-shrink: 0; width: 30px; height: 30px; border-radius: 50%; background: #eef1f5; display: flex; align-items: center; justify-content: center; color: #444; }
        .portal-notif-icon svg { width: 16px; height: 16px; }
        .portal-notif-body { flex: 1; min-width: 0; }
        .portal-notif-title { font-size: 0.85rem; font-weight: 600; }
        .portal-notif-message { font-size: 0.8rem; color: var(--color-text-muted); margin-top: 2px; }
        .portal-notif-time { font-size: 0.72rem; color: var(--color-text-muted); margin-top: 4px; }
        .portal-notif-empty { padding: 2rem 1rem; text-align: center; color: var(--color-text-muted); font-size: 0.85rem; }
        .portal-notif-mark-btn { background: none; border: none; color: #2563eb; font-size: 0.72rem; cursor: pointer; padding: 0; margin-top: 4px; font-family: inherit; }
        /* Wrap */
        .portal-wrap {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        /* Alert */
        .alert {
            padding: 0.8rem 1rem;
            border-radius: 6px;
            margin-bottom: 1.2rem;
            font-size: 0.9rem;
        }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-warning { background: #fef9c3; color: #854d0e; border: 1px solid #fde047; }
        /* Card */
        .card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .card h3 { margin-bottom: 1rem; font-size: 1rem; }
        /* Table */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th { text-align: left; padding: 0.6rem 0.8rem; border-bottom: 2px solid var(--color-border); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--color-text-muted); }
        td { padding: 0.6rem 0.8rem; border-bottom: 1px solid var(--color-border); }
        tr:last-child td { border-bottom: none; }
        /* Badge */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 99px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .badge-paid    { background: #dcfce7; color: #166534; }
        .badge-partial { background: #fef9c3; color: #854d0e; }
        .badge-sent    { background: #dbeafe; color: #1e40af; }
        .badge-draft   { background: #f3f4f6; color: #374151; }
        .badge-overdue { background: #fee2e2; color: #991b1b; }
        .badge-cancelled { background: #f3f4f6; color: #374151; }
        /* Btn */
        .btn {
            display: inline-block;
            padding: 0.45rem 1rem;
            border-radius: 5px;
            font-size: 0.85rem;
            font-family: inherit;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: opacity 0.15s;
        }
        .btn:hover { opacity: 0.85; }
        .btn-primary { background: var(--color-primary); color: #fff; }
        .btn-outline { background: transparent; border: 1px solid var(--color-border); color: var(--color-text); }
        .btn-danger  { background: var(--color-danger); color: #fff; }
        .btn-sm { padding: 0.3rem 0.7rem; font-size: 0.8rem; }
        /* Grid */
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 8px; padding: 1.2rem 1.5rem; }
        .stat-label { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--color-text-muted); margin-bottom: 0.4rem; }
        .stat-value { font-size: 1.6rem; font-weight: bold; line-height: 1.2; }
        .stat-value.danger { color: var(--color-danger); }
        .stat-value.success { color: var(--color-success); }
        /* Form */
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: bold; margin-bottom: 0.3rem; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.55rem 0.8rem;
            border: 1px solid var(--color-border);
            border-radius: 5px;
            font: inherit;
            font-size: 0.9rem;
            background: var(--color-surface);
            color: var(--color-text);
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: 2px solid var(--color-primary);
            outline-offset: 2px;
        }
        .form-error { color: var(--color-danger); font-size: 0.82rem; margin-top: 0.25rem; }
        /* Section title */
        .section-title { font-size: 1.1rem; margin-bottom: 1.2rem; padding-bottom: 0.6rem; border-bottom: 2px solid var(--color-border); }
        /* Page title */
        .page-title { font-size: 1.4rem; margin-bottom: 1.5rem; }

        /* This whole layout is self-contained (its own inline <style>, no
           link to the main app's responsive.css) and had zero mobile
           handling at all — the nav bar's unwrapped flex row would overflow
           on every single portal page since it's shared by all of them. */
        @media (max-width: 640px) {
            .portal-nav-inner { flex-wrap: wrap; height: auto; padding: 0.6rem 1rem; row-gap: 0.4rem; }
            .portal-nav-links { flex-wrap: wrap; gap: 0.6rem 1rem; row-gap: 0.5rem; }
            .portal-brand { font-size: 1rem; }
            .portal-wrap { padding: 1.25rem 0.85rem; }
            .stat-value { font-size: 1.35rem; }

            /* Every portal table (invoices, statement, loyalty history) was
               a fixed-min-width grid inside .table-wrap's horizontal-scroll
               fallback — usable but awkward with one thumb on a phone.
               Collapse into the same label/value card-stack the main app's
               responsive.css uses for its own tables, driven by the
               data-label attribute each <td> below now carries. !important
               is needed throughout since every cell here sets its own
               inline style="..." (higher specificity than a plain rule). */
            .table-wrap { overflow-x: visible; }
            table, thead, tbody, th, td, tr { display: block; }
            table { width: 100% !important; min-width: 0 !important; }
            thead tr { position: absolute; top: -9999px; left: -9999px; visibility: hidden; }
            tbody tr {
                border: none !important;
                border-bottom: 2px solid var(--color-border) !important;
                padding: 0.6rem 0 !important;
            }
            tbody tr:last-child { border-bottom: none !important; }
            td {
                border: none !important;
                padding: 0.25rem 0 !important;
                padding-left: 44% !important;
                position: relative !important;
                text-align: right !important;
                min-height: 22px;
            }
            td:empty { display: none; }
            td[colspan] { padding-left: 0 !important; text-align: left !important; }
            td[colspan]::before { display: none; }
            tr:has(td[colspan]) td:not([colspan]) { padding-left: 0 !important; text-align: right !important; font-weight: bold; }
            /* A trailing "View →" action cell has no label/value pairing —
               give it a class instead of a data-label and let it sit flush. */
            td.td-action { padding-left: 0 !important; text-align: right !important; }
            td::before {
                position: absolute;
                left: 0;
                width: 40%;
                font-size: 0.72rem;
                font-weight: bold;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                color: var(--color-text-muted);
                content: attr(data-label);
            }
        }
    </style>
    @stack('styles')
    @include('layouts.partials.brand-icons')
</head>
<body>
@php $portalBusiness = optional(Auth::guard('customer')->user())->business; @endphp
<nav class="portal-nav">
    <div class="portal-nav-inner">
        <a href="{{ route('portal.dashboard') }}" class="portal-brand">
            {{ $portalBusiness->name ?? config('app.name') }}
        </a>
        <ul class="portal-nav-links">
            <li><a href="{{ route('portal.dashboard') }}" @class(['active' => request()->routeIs('portal.dashboard')])>Dashboard</a></li>
            <li><a href="{{ route('portal.invoices') }}"  @class(['active' => request()->routeIs('portal.invoices*')])>Invoices</a></li>
            <li><a href="{{ route('portal.statement') }}" @class(['active' => request()->routeIs('portal.statement')])>Statement</a></li>
            @if(optional(optional(optional(Auth::guard('customer')->user())->business)->loyaltyProgram)->is_active)
            <li><a href="{{ route('portal.loyalty') }}"   @class(['active' => request()->routeIs('portal.loyalty')])>Loyalty</a></li>
            @endif
            @auth('customer')
            @php
                $__portalCustomer = Auth::guard('customer')->user();
                $__custNotifQuery = \App\Models\CustomerNotification::where('customer_id', $__portalCustomer->id)
                    ->where('business_id', $__portalCustomer->business_id);
                $unreadCustomerNotifs = (clone $__custNotifQuery)->whereNull('read_at')->count();
                $navCustomerNotifications = (clone $__custNotifQuery)->latest()->limit(8)->get();
            @endphp
            <li class="portal-dropdown">
                <button type="button" class="portal-notif-toggle" title="Notifications" aria-label="Notifications">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    @if($unreadCustomerNotifs > 0)<span class="portal-notif-badge">{{ $unreadCustomerNotifs > 9 ? '9+' : $unreadCustomerNotifs }}</span>@endif
                </button>
                <div class="portal-dropdown-menu portal-notif-menu">
                    <div class="portal-notif-header">
                        <span>Notifications</span>
                        @if($unreadCustomerNotifs > 0)
                        <form action="{{ route('portal.notifications.read-all') }}" method="POST">
                            @csrf
                            <button type="submit">Mark all read</button>
                        </form>
                        @endif
                    </div>
                    @forelse($navCustomerNotifications as $notif)
                    <div class="portal-notif-row {{ $notif->isRead() ? '' : 'is-unread' }}">
                        <div class="portal-notif-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1 1-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8V6Z"/></svg>
                        </div>
                        <div class="portal-notif-body">
                            <div class="portal-notif-title">{{ $notif->title }}</div>
                            <div class="portal-notif-message">{{ $notif->message }}</div>
                            <div class="portal-notif-time">{{ $notif->created_at->diffForHumans() }}</div>
                            @if(!$notif->isRead())
                            <form action="{{ route('portal.notifications.read', $notif->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="portal-notif-mark-btn">Mark as read</button>
                            </form>
                            @endif
                        </div>
                    </div>
                    @empty
                    <div class="portal-notif-empty">No notifications yet</div>
                    @endforelse
                </div>
            </li>
            @endauth
            <li><span class="nav-separator"></span></li>
            <li class="portal-dropdown">
                <button class="portal-dropdown-toggle">
                    {{ Auth::guard('customer')->user()->name ?? 'My Account' }} &#9660;
                </button>
                <div class="portal-dropdown-menu">
                    <a href="{{ route('portal.profile') }}">Profile</a>
                    <form action="{{ route('portal.logout') }}" method="POST">
                        @csrf
                        <button type="submit">Log out</button>
                    </form>
                </div>
            </li>
        </ul>
    </div>
</nav>

<div class="portal-wrap">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif
    @if($errors->any() && !$errors->has('email') && !$errors->has('password'))
        <div class="alert alert-error">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @yield('content')
</div>

@stack('scripts')
</body>
</html>
