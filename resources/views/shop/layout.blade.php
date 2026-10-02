<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', $business->name) — Online Store</title>

    {{-- Previously this whole layout had zero SEO/sharing tags of any
    kind — no meta description (Google falls back to an auto-picked
    snippet, often the nav/footer boilerplate), no Open Graph/Twitter
    Card (a link shared on WhatsApp — the single most common sharing
    channel for a Kenyan SME's customers — showed no title, image, or
    preview text at all, just a bare URL), and no canonical tag. Each
    page below now sets @yield('meta_description', ...) with something
    real (store_description, or a product's own description). --}}
    @php
        $shopMetaDescription = trim($__env->yieldContent('meta_description')) ?: ($business->store_description ?: "Shop at {$business->name} online — browse products and order directly, with M-Pesa payment.");
        $shopMetaImage = $business->logo ? asset('storage/' . $business->logo) : null;
    @endphp
    <meta name="description" content="{{ Str::limit($shopMetaDescription, 160, '') }}">
    <link rel="canonical" href="{{ url()->current() }}">
    @if(!$business->store_public ?? false)
    <meta name="robots" content="noindex, nofollow">
    @endif
    @stack('robots')

    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="@yield('title', $business->name)">
    <meta property="og:description" content="{{ Str::limit($shopMetaDescription, 200, '') }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:site_name" content="{{ $business->name }}">
    @if($shopMetaImage)
    <meta property="og:image" content="{{ $shopMetaImage }}">
    @endif
    <meta name="twitter:card" content="{{ $shopMetaImage ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="@yield('title', $business->name)">
    <meta name="twitter:description" content="{{ Str::limit($shopMetaDescription, 200, '') }}">
    @if($shopMetaImage)
    <meta name="twitter:image" content="{{ $shopMetaImage }}">
    <link rel="icon" href="{{ $shopMetaImage }}">
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@700;800&display=swap">
    @php
        // Every other layout in the app (app/auth/marketing/org.blade.php)
        // already busts both the browser's own HTTP cache and Cloudflare's
        // edge cache this way — this file was written without it, so a
        // browser that loaded shop.css once kept re-serving its own local
        // copy for up to 7 days (the Cache-Control: max-age on this path)
        // regardless of how many times the origin file was redeployed.
        $v = fn ($p) => asset($p) . '?v=' . (@filemtime(public_path($p)) ?: '1');
        // Each merchant's storefront picks up their own receipt/invoice
        // accent color (already configurable in Settings → Receipt & Invoice
        // Branding) if they've set one, instead of every business's shop
        // looking identical. Falls back to the original near-black default.
        $shopAccent = $business->receipt_color ?: '#16181c';
    @endphp
    <link rel="stylesheet" href="{{ $v('css/shop.css') }}">
    <style>:root { --shop-accent: {{ $shopAccent }}; }</style>
    @stack('styles')
</head>
<body>
<nav class="shop-nav">
    <a href="{{ route('shop.index', $business->store_slug) }}" class="shop-nav-brand">
        @if($business->logo)
        <img src="{{ asset('storage/' . $business->logo) }}" alt="">
        @endif
        <span class="shop-nav-logo">{{ $business->name }}</span>
    </a>
    <a href="{{ route('shop.cart', $business->store_slug) }}" class="shop-nav-cart">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
        <span class="cart-label">Cart</span>
        @if(($cartCount ?? 0) > 0)
        <span class="cart-badge">{{ $cartCount }}</span>
        @endif
    </a>
</nav>

<div class="shop-wrap">
    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    @yield('content')
</div>

<footer class="shop-footer">
    &copy; {{ date('Y') }} {{ $business->name }}. Powered by Merqio POS.
</footer>
@stack('scripts')
</body>
</html>
