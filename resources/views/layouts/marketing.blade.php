<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Was `@yield('title', 'Merqio POS') — Business Management for Kenyan
    SMEs` — unconditionally appending that suffix regardless of whether a
    page had already set its own complete title, so every single page's
    <title> was a broken, duplicated mess (e.g. "Merqio POS — Complete
    Business Management for Kenyan SMEs — Business Management for Kenyan
    SMEs"). The full string is now just the DEFAULT, used only when a page
    doesn't set its own — every page already does, so this only matters as
    a fallback. --}}
    <title>@yield('title', 'Merqio POS — Business Management for Kenyan SMEs')</title>
    @php
        $mktMetaDescription = trim($__env->yieldContent('meta_description')) ?: 'Merqio POS helps Kenyan small businesses manage inventory, sales, expenses, customers and staff — all in one place.';
        $mktOgImage = asset('icons/icon.svg');
    @endphp
    <meta name="description" content="{{ $mktMetaDescription }}">
    {{-- Hardcoded to the real production domain regardless of which host
    served this request — previously used url()->current(), which made the
    staging environment (smemanagers.com) self-canonicalize instead of
    pointing back at merqiopos.com. Google had no signal telling it these
    were the same content with one authoritative source, and ended up
    ranking the older, longer-indexed smemanagers.com for brand searches
    instead of the real production domain. Production already resolves to
    this exact same URL, so this is a no-op there — it only changes
    staging's (previously wrong) behavior. --}}
    <link rel="canonical" href="https://merqiopos.com{{ request()->getRequestUri() }}">

    {{-- Previously none of this existed at all — a marketing link shared
    on WhatsApp (the most common sharing channel for a Kenyan SME's own
    customers and word-of-mouth referrals) showed a bare URL with no
    title, description, or preview image whatsoever. --}}
    <meta property="og:type" content="website">
    <meta property="og:title" content="@yield('title', 'Merqio POS — Business Management for Kenyan SMEs')">
    <meta property="og:description" content="{{ $mktMetaDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:site_name" content="Merqio POS">
    <meta property="og:image" content="{{ $mktOgImage }}">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="@yield('title', 'Merqio POS — Business Management for Kenyan SMEs')">
    <meta name="twitter:description" content="{{ $mktMetaDescription }}">
    <meta name="twitter:image" content="{{ $mktOgImage }}">

    @include('layouts.partials.brand-icons')

    {{-- Organization schema — lets Google associate the brand name/logo
    with this site in search results (e.g. a knowledge-panel-style logo
    next to the listing) instead of treating it as an anonymous URL. Built
    as a PHP array below, then json_encode()'d — writing that key directly
    as raw JSON in ordinary template markup breaks Blade compilation
    outright (Laravel reserves that exact word as a real directive there),
    but the identical text works fine as a plain PHP string literal in the
    raw-PHP region right underneath this comment. --}}
    @php
        $mktOrgSchema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Organization',
            'name'        => 'Merqio POS',
            'url'         => url('/'),
            'logo'        => asset('icons/icon.svg'),
            'description' => 'Business management software for Kenyan SMEs — point of sale, inventory, invoicing, payroll, and M-Pesa payments in one platform.',
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($mktOrgSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

    @php $v = fn ($p) => asset($p) . '?v=' . (@filemtime(public_path($p)) ?: '1'); @endphp
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500;1,9..144,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ $v('css/main.css') }}">
    <link rel="stylesheet" href="{{ $v('css/marketing.css') }}">
    @stack('styles')
</head>
<body class="mkt-body">

@php $loggedIn = auth()->check(); @endphp

<header class="mkt-nav" id="mktNav">
    <div class="mkt-nav-inner">
        <a href="{{ route('home') }}" class="mkt-logo">
            <div class="mkt-logo-icon" aria-hidden="true"></div>
            Merqio<span>POS</span>
        </a>

        <nav class="mkt-nav-links" id="mktNavLinks">
            <a href="{{ route('home') }}"    class="{{ request()->routeIs('home')    ? 'active' : '' }}">Home</a>
            <a href="{{ route('home') }}#features" class="">Features</a>
            <a href="{{ route('pricing') }}" class="{{ request()->routeIs('pricing') ? 'active' : '' }}">Plans</a>
            <a href="{{ route('guide') }}"   class="{{ request()->routeIs('guide')   ? 'active' : '' }}">User Guide</a>
            <a href="{{ route('contact') }}" class="{{ request()->routeIs('contact') ? 'active' : '' }}">Contact</a>
        </nav>

        <div class="mkt-nav-actions">
            @if($loggedIn)
                <a href="{{ route('menu') }}" class="mkt-btn-ghost-nav">My Dashboard</a>
                <a href="{{ route('menu') }}" class="mkt-btn-nav-primary">Go to App &rarr;</a>
            @else
                <form method="POST" action="{{ route('demo.start') }}" style="display:inline;margin:0;">@csrf<button type="submit" class="mkt-btn-ghost-nav" style="font:inherit;cursor:pointer;background:none;border:0;">Try demo</button></form>
                <a href="{{ route('login') }}"    class="mkt-btn-ghost-nav">Log in</a>
                <a href="{{ route('register') }}" class="mkt-btn-nav-primary">Sign Up &rarr;</a>
            @endif
        </div>

        <button class="mkt-hamburger" id="mktHamburger" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
    </div>

    <div class="mkt-mobile-menu" id="mktMobileMenu">
        <a href="{{ route('home') }}">Home</a>
        <a href="{{ route('home') }}#features">Features</a>
        <a href="{{ route('pricing') }}">Plans</a>
        <a href="{{ route('guide') }}">User Guide</a>
        <a href="{{ route('contact') }}">Contact</a>
        <hr>
        @if($loggedIn)
            <a href="{{ route('menu') }}" class="mkt-btn-nav-primary" style="text-align:center; margin-top:0.25rem;">Go to App &rarr;</a>
        @else
            <form method="POST" action="{{ route('demo.start') }}" style="margin:0;">@csrf<button type="submit" style="font:inherit;cursor:pointer;background:none;border:0;padding:0;color:inherit;text-align:left;">Try the demo</button></form>
            <a href="{{ route('login') }}">Log in</a>
            <a href="{{ route('register') }}" class="mkt-btn-nav-primary" style="text-align:center; margin-top:0.25rem;">Sign Up &rarr;</a>
        @endif
    </div>
</header>

<div class="mkt-mobile-backdrop" id="mktMobileBackdrop"></div>

<main>@yield('content')</main>

{{-- Sticky contact bubbles — same real business number used in the footer --}}
<div class="mkt-float-contact" aria-label="Contact us">
    <a href="https://wa.me/254718215432" target="_blank" rel="noopener" class="mkt-float-btn mkt-float-btn--whatsapp" aria-label="Chat on WhatsApp">
        <svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.148.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12.02 2C6.5 2 2 6.477 2 11.99c0 1.87.518 3.702 1.5 5.303L2 22l4.833-1.469a10.06 10.06 0 0 0 5.187 1.421h.005c5.522 0 10.02-4.477 10.02-9.99C22.045 6.476 17.542 2 12.02 2zm0 18.13h-.004a8.15 8.15 0 0 1-4.155-1.137l-.298-.176-3.099.942.955-3.03-.194-.31a8.14 8.14 0 0 1-1.259-4.42c0-4.506 3.673-8.174 8.187-8.174 2.186 0 4.242.85 5.788 2.394a8.104 8.104 0 0 1 2.397 5.78c0 4.505-3.674 8.13-8.318 8.13z"/></svg>
    </a>
    <a href="tel:+254718215432" class="mkt-float-btn mkt-float-btn--call" aria-label="Call us">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.362 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
    </a>
</div>

<footer class="mkt-footer">
    <div class="mkt-container">
        <div class="mkt-footer-grid">
            <div class="mkt-footer-brand">
                <a href="{{ route('home') }}" class="mkt-logo">
                    <div class="mkt-logo-icon" aria-hidden="true"></div>
                    Merqio<span>POS</span>
                </a>
                <p>Simple, powerful business management built for Kenyan SMEs. Track every shilling, every product, every customer — from any device.</p>
            </div>
            <div>
                <h4>Product</h4>
                <a href="{{ route('home') }}#features">Features</a>
                <a href="{{ route('pricing') }}">Plans</a>
                <a href="{{ route('guide') }}">Getting Started Guide</a>
                <a href="{{ route('register') }}">Sign Up Free</a>
                <a href="{{ route('login') }}">Log In</a>
            </div>
            <div>
                <h4>Company</h4>
                <a href="{{ route('contact') }}">Contact Us</a>
                <a href="{{ route('privacy') }}">Privacy Policy</a>
                <a href="{{ route('terms') }}">Terms of Service</a>
                <a href="{{ route('disclaimer') }}">Disclaimer</a>
            </div>
            <div>
                <h4>Support</h4>
                <a href="mailto:support@merqiopos.com">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22 6 12 13 2 6"/></svg>
                    support@merqiopos.com
                </a>
                <a href="tel:+254718215432">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.68 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.54 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.18 6.18l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    +254 718 215 432
                </a>
                <a href="https://wa.me/254718215432" target="_blank" rel="noopener">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                    WhatsApp Support
                </a>
                <p class="mkt-footer-support-hours">Mon–Fri, 8am–6pm EAT</p>
            </div>
        </div>
        <div class="mkt-footer-bottom">
            <p>&copy; {{ date('Y') }} Merqio POS. All rights reserved.</p>
            <p>Built for Kenya</p>
        </div>
    </div>
</footer>

<script>
    window.addEventListener('scroll', function () {
        document.getElementById('mktNav').classList.toggle('scrolled', window.scrollY > 40);
    });
    // Trigger once on load
    document.getElementById('mktNav').classList.toggle('scrolled', window.scrollY > 40);

    // The fixed WhatsApp/call bubbles sit wherever page content happens to
    // scroll underneath them — on a long one-page scroller that's
    // frequently a heading or paragraph (confirmed on mobile: headings
    // losing their first letter or more behind the circles). Fading them
    // out while the page is actually moving, then back in once it settles,
    // removes most of that exposure without changing where the buttons live.
    (function () {
        var floatContact = document.querySelector('.mkt-float-contact');
        if (!floatContact) return;
        var hideTimer = null;
        window.addEventListener('scroll', function () {
            floatContact.classList.add('is-scrolling');
            clearTimeout(hideTimer);
            hideTimer = setTimeout(function () {
                floatContact.classList.remove('is-scrolling');
            }, 400);
        }, { passive: true });
    })();

    (function () {
        var hamburger = document.getElementById('mktHamburger');
        var menu      = document.getElementById('mktMobileMenu');
        var backdrop  = document.getElementById('mktMobileBackdrop');

        function closeMenu() {
            hamburger.classList.remove('open');
            menu.classList.remove('open');
            backdrop.classList.remove('open');
            document.body.classList.remove('mkt-menu-open');
        }
        function openMenu() {
            hamburger.classList.add('open');
            menu.classList.add('open');
            backdrop.classList.add('open');
            document.body.classList.add('mkt-menu-open');
        }

        hamburger.addEventListener('click', function () {
            if (menu.classList.contains('open')) closeMenu(); else openMenu();
        });
        // Tapping outside the menu (the dimmed area) closes it, same as
        // any modern drawer/sheet pattern — previously there was no way
        // to dismiss it except hitting the hamburger again.
        backdrop.addEventListener('click', closeMenu);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeMenu();
        });
        // A tapped link still navigates normally (real page loads here,
        // no SPA routing) — this just avoids a jarring flash of the old
        // page's menu sitting open for an instant while the next page loads.
        menu.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', closeMenu);
        });
    })();
</script>
@stack('scripts')
</body>
</html>
