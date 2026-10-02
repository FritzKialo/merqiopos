@extends('shop.layout')
@section('title', $business->name . ' — Shop')
@section('meta_description', $business->store_description ?: "Browse and shop {$business->name}'s products online — order directly with M-Pesa payment.")

@php
    // No product-image field exists anywhere in the app yet — every card
    // previously showed the exact same generic gray "broken image" icon.
    // A deterministic per-product accent + initial + icon watermark reads
    // as an intentional design choice instead of a missing-asset
    // placeholder, and gives the grid visual variety without real photos.
    $tilePalette = ['#3b5a7a', '#a15c3e', '#3f6b52', '#7a4a6b', '#8a6a2e', '#2f6e6b'];
    $tileColor = fn($id) => $tilePalette[crc32((string) $id) % count($tilePalette)];
    $stockBadge = function ($qty) {
        if ($qty <= 0) return ['out', 'Out of Stock'];
        if ($qty <= 5) return ['low', 'Low Stock'];
        return ['in', 'In Stock'];
    };
@endphp

@section('content')
<div class="shop-hero">
    <h1>{{ $business->name }}</h1>
    @if($business->store_description)
    <p>{{ $business->store_description }}</p>
    @endif
</div>

@if($categories->isNotEmpty())
<div class="category-chips">
    <a href="{{ route('shop.index', $business->store_slug) }}" class="chip {{ request('category') ? '' : 'active' }}">All</a>
    @foreach($categories as $category)
    <a href="{{ route('shop.index', ['slug' => $business->store_slug, 'category' => $category->id]) }}"
       class="chip {{ (string) request('category') === (string) $category->id ? 'active' : '' }}">
        {{ $category->name }}
    </a>
    @endforeach
</div>
@endif

@if($bundles->isNotEmpty())
<h2 style="font-size:1.1rem; font-weight:800; margin:0 0 12px;">Bundles &amp; Deals</h2>
<div class="product-grid" style="margin-bottom:32px;">
    @foreach($bundles as $bundle)
    <div class="product-card">
        @if($bundle->image)
            <img src="{{ asset('storage/' . $bundle->image) }}" alt="{{ $bundle->name }}" class="product-tile product-tile-photo">
        @else
            <div class="product-tile" style="background:{{ $tileColor('bundle-' . $bundle->id) }};">
                <span>{{ strtoupper(substr($bundle->name, 0, 1)) }}</span>
            </div>
        @endif
        <div class="product-card-body">
            <p class="product-card-name">{{ $bundle->name }}</p>
            <p style="font-size:0.8rem; color:var(--shop-ink-soft); margin:2px 0 6px;">
                {{ $bundle->items->map(fn($i) => $i->product->name ?? '')->filter()->implode(' + ') }}
            </p>
            <div class="product-card-price">KSh {{ number_format($bundle->price, 0) }}</div>
            <span class="stock-badge in">Bundle Deal</span>
            <form method="POST" action="{{ route('shop.cart.add-bundle', $business->store_slug) }}">
                @csrf
                <input type="hidden" name="bundle_id" value="{{ $bundle->id }}">
                <input type="hidden" name="qty" value="1">
                <button type="submit" class="btn btn-dark btn-block">Add to Cart</button>
            </form>
        </div>
    </div>
    @endforeach
</div>
@endif

@if($products->isEmpty() && $bundles->isEmpty())
<div class="empty-state">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
    <p>No products available right now.</p>
</div>
@elseif($products->isNotEmpty())
<div class="product-grid">
    @foreach($products as $product)
    @php [$badgeClass, $badgeLabel] = $stockBadge($product->stock_qty); @endphp
    <div class="product-card">
        <a href="{{ route('shop.product', [$business->store_slug, $product]) }}" class="product-card-link">
            @if($product->imageUrl())
                <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="product-tile product-tile-photo">
            @else
                <div class="product-tile" style="background:{{ $tileColor($product->id) }};">
                    <span>{{ strtoupper(substr($product->name, 0, 1)) }}</span>
                </div>
            @endif
        </a>
        <div class="product-card-body">
            <a href="{{ route('shop.product', [$business->store_slug, $product]) }}" class="product-card-link">
                <p class="product-card-name">{{ $product->name }}</p>
            </a>
            @php $tierPrice = $product->priceForTier($tierId); @endphp
            <div class="product-card-price">
                KSh {{ number_format($tierPrice, 0) }}
                @if($tierPrice != $product->selling_price)
                    <span style="font-size:0.75rem; font-weight:400; color:var(--shop-ink-soft); text-decoration:line-through;">KSh {{ number_format($product->selling_price, 0) }}</span>
                    <span style="font-size:0.7rem; font-weight:700; color:var(--shop-success,#16a34a);">Your Price</span>
                @endif
            </div>
            <span class="stock-badge {{ $badgeClass }}">{{ $badgeLabel }}</span>
            @if($product->has_variants)
            <a href="{{ route('shop.product', [$business->store_slug, $product]) }}" class="btn btn-outline btn-block">Select Options</a>
            @else
            <form method="POST" action="{{ route('shop.cart.add', $business->store_slug) }}">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="qty" value="1">
                <button type="submit" class="btn btn-dark btn-block">Add to Cart</button>
            </form>
            @endif
        </div>
    </div>
    @endforeach
</div>
{{ $products->links() }}
@endif
@endsection
