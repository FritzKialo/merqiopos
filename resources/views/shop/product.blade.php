@extends('shop.layout')
@section('title', $product->name . ' — ' . $business->name)
@section('og_type', 'product')
@section('meta_description', $product->description ? \Illuminate\Support\Str::limit($product->description, 155, '') : "{$product->name} — KSh " . number_format($product->selling_price, 0) . " at {$business->name}. Order online, pay with M-Pesa.")

@php
    $tilePalette = ['#3b5a7a', '#a15c3e', '#3f6b52', '#7a4a6b', '#8a6a2e', '#2f6e6b'];
    $tileColor = $tilePalette[crc32((string) $product->id) % count($tilePalette)];
    // For a variant-tracked product, cart/checkout only ever look at the
    // SELECTED VARIANT's own stock_qty (see StoreController) — the parent's
    // own stock_qty column is not what actually gets sold. Judging
    // out-of-stock/disabled state off the parent alone could show "Out of
    // Stock" (and disable Add to Cart entirely) for a product whose
    // variants are all fully in stock.
    $availableStock = $product->has_variants
        ? $product->activeVariants->sum('stock_qty')
        : $product->stock_qty;
    [$badgeClass, $badgeLabel] = $availableStock <= 0
        ? ['out', 'Out of Stock']
        : ($availableStock <= 5 ? ['low', 'Low Stock'] : ['in', 'In Stock']);
@endphp

{{-- Product structured data — lets Google show price/availability/rating
directly in search results instead of just a plain blue link. Rating is
only included when there's at least one real approved review, since a
fabricated 0-review rating would be flagged by Google's own guidelines.
Built as a real PHP array + json_encode() rather than interpolating
Js::from() per-field — Js::from() is meant for JS literals and quotes
strings with single quotes, which is invalid JSON and would make Google
silently fail to parse this whole block. The @context/@type keys as
plain single-@ PHP string literals inside the raw-PHP block below are
fine; the same key written directly in template markup previously broke
compilation entirely, since Laravel registers @context as a real Blade
directive — this comment itself had to be reworded once already because
even mentioning the raw-PHP directive's own name in here triggered it. --}}
@php
    $structuredData = [
        '@context' => 'https://schema.org',
        '@type'    => 'Product',
        'name'      => $product->name,
        'sku'       => $product->sku ?: (string) $product->id,
    ];
    if ($product->description) {
        $structuredData['description'] = \Illuminate\Support\Str::limit($product->description, 500, '');
    }
    if ($product->imageUrl()) {
        $structuredData['image'] = $product->imageUrl();
    }
    if ($reviewCount > 0) {
        $structuredData['aggregateRating'] = [
            '@type'      => 'AggregateRating',
            'ratingValue' => $avgRating,
            'reviewCount' => $reviewCount,
        ];
    }
    $structuredData['offers'] = [
        '@type'        => 'Offer',
        'url'           => url()->current(),
        'priceCurrency' => 'KES',
        'price'         => (string) $product->selling_price,
        'availability'  => 'https://schema.org/' . ($product->stock_qty > 0 ? 'InStock' : 'OutOfStock'),
    ];
@endphp
@push('styles')
<script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
<a href="{{ route('shop.index', $business->store_slug) }}" class="back-link">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;"><path d="m15 18-6-6 6-6"/></svg>
    Back to shop
</a>

<div class="product-detail">
    <div>
        @if($product->imageUrl())
            <div class="product-img-box product-img-box-photo">
                <img id="productMainImage" src="{{ $product->imageUrl() }}" alt="{{ $product->name }}">
            </div>
            @if($product->images->isNotEmpty())
            <div class="product-gallery-thumbs">
                <img src="{{ $product->imageUrl() }}" class="gallery-thumb active" onclick="document.getElementById('productMainImage').src=this.src; document.querySelectorAll('.gallery-thumb').forEach(t=>t.classList.remove('active')); this.classList.add('active');">
                @foreach($product->images as $img)
                <img src="{{ asset('storage/' . $img->path) }}" class="gallery-thumb" onclick="document.getElementById('productMainImage').src=this.src; document.querySelectorAll('.gallery-thumb').forEach(t=>t.classList.remove('active')); this.classList.add('active');">
                @endforeach
            </div>
            @endif
        @else
            <div class="product-img-box" style="background:{{ $tileColor }};">
                <span>{{ strtoupper(substr($product->name, 0, 1)) }}</span>
            </div>
        @endif
    </div>
    <div>
        <h1 class="product-title">{{ $product->name }}</h1>
        @php $tierPrice = $product->priceForTier($tierId); @endphp
        <div class="product-price" id="display-price">
            KSh {{ number_format($tierPrice, 0) }}
            @if($tierPrice != $product->selling_price)
                <span style="font-size:0.85rem; font-weight:400; color:var(--shop-ink-soft); text-decoration:line-through;">KSh {{ number_format($product->selling_price, 0) }}</span>
                <span style="font-size:0.75rem; font-weight:700; color:var(--shop-success,#16a34a);">Your Price</span>
            @endif
        </div>
        <span class="stock-badge {{ $badgeClass }}" style="margin-bottom:16px;">{{ $badgeLabel }}</span>
        @if($product->description)
        <p class="product-desc">{{ $product->description }}</p>
        @endif

        <form method="POST" action="{{ route('shop.cart.add', $business->store_slug) }}">
            @csrf
            <input type="hidden" name="product_id" value="{{ $product->id }}">

            @if($product->has_variants && $product->activeVariants->isNotEmpty())
            <div class="form-field">
                <label>Select Variant</label>
                {{-- activeVariants only filters is_active, not stock — a
                variant with 0 left is still a real, selectable option here
                (correctly so; hiding it would just make the size/color
                disappear with no explanation). The qty max/availability
                text/button below previously never reflected the SELECTED
                variant's own stock_qty at all — only ever the parent
                product's, which is a different, often-unrelated number for
                a variant-tracked product. A customer could pick a
                2-in-stock variant, the qty box would still let them type up
                to the parent's stock (e.g. 20), and they'd only find out it
                was wrong after filling in their whole checkout form (the
                real stock re-check happens there, so nothing oversells —
                it was just a needlessly late, frustrating rejection). --}}
                <select name="variant_id" class="form-control" id="variant-select" required>
                    <option value="">— Choose —</option>
                    @foreach($product->activeVariants as $variant)
                    <option value="{{ $variant->id }}" data-price="{{ $variant->price ?? $product->selling_price }}" data-stock="{{ $variant->stock_qty }}">
                        {{ $variant->name }}
                        @if($variant->price) — KSh {{ number_format($variant->price, 0) }} @endif
                        {{ $variant->stock_qty <= 0 ? ' (Out of stock)' : '' }}
                    </option>
                    @endforeach
                </select>
            </div>
            @endif

            <div class="qty-row">
                <label>Qty</label>
                <input type="number" name="qty" value="1" min="1" max="{{ $availableStock }}" class="form-control qty-input" id="qty-input">
            </div>

            <button type="submit" class="btn btn-dark" id="add-to-cart-btn" {{ $availableStock > 0 ? '' : 'disabled' }}>
                {{ $availableStock > 0 ? 'Add to Cart' : 'Out of Stock' }}
            </button>
        </form>

        <p style="font-size:0.82rem;color:var(--shop-ink-soft);margin-top:18px;" id="stock-available-text">{{ $availableStock }} available</p>
    </div>
</div>

{{-- ── Customer Reviews ──────────────────────────────────────────────
Approved-only display + a submission form. A review submitted here lands
as "pending" and stays invisible until a staff member approves it under
Product Reviews — see ProductReviewController. --}}
<div class="review-section" style="max-width:640px;">
    <h2 style="font-size:1.15rem;margin-bottom:2px;">Customer Reviews</h2>

    @if($reviewCount > 0)
    <div class="review-summary">
        <span class="avg-rating">{{ $avgRating }}</span>
        <span class="stars-display">
            @for($i = 1; $i <= 5; $i++)<span class="{{ $i <= round($avgRating) ? '' : 'star-empty' }}">★</span>@endfor
        </span>
        <span class="review-count">based on {{ $reviewCount }} {{ Str::plural('review', $reviewCount) }}</span>
    </div>

    <div class="review-list">
        @foreach($reviews as $review)
        <div class="review-item">
            <div class="review-item-head">
                <span class="review-author">{{ $review->reviewer_name }}</span>
                <span class="review-date">{{ $review->created_at->format('d M Y') }}</span>
            </div>
            <span class="stars-display">
                @for($i = 1; $i <= 5; $i++)<span class="{{ $i <= $review->rating ? '' : 'star-empty' }}">★</span>@endfor
            </span>
            @if($review->review_body)
            <p class="review-body">{{ $review->review_body }}</p>
            @endif
        </div>
        @endforeach
    </div>
    @else
    <p class="no-reviews">No reviews yet — be the first to review this product.</p>
    @endif

    <div class="review-form-box">
        <h3>Write a Review</h3>
        <p class="hint">Your review is checked by the shop before it appears publicly.</p>

        @if(session('review_success'))
        <div class="alert alert-success">{{ session('review_success') }}</div>
        @endif
        @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('shop.reviews.store', $business->store_slug) }}">
            @csrf
            <input type="hidden" name="product_id" value="{{ $product->id }}">

            <label>Your Rating *</label>
            <div class="star-picker">
                @for($i = 5; $i >= 1; $i--)
                <input type="radio" name="rating" id="star-{{ $i }}" value="{{ $i }}" {{ old('rating') == $i ? 'checked' : '' }} required>
                <label for="star-{{ $i }}">★</label>
                @endfor
            </div>

            <div class="form-grid-2">
                <div class="form-field"><label>Your Name *</label><input type="text" name="reviewer_name" class="form-control" value="{{ old('reviewer_name') }}" required maxlength="200"></div>
                <div class="form-field"><label>Email (optional)</label><input type="email" name="reviewer_email" class="form-control" value="{{ old('reviewer_email') }}" maxlength="200"></div>
            </div>
            <div class="form-field">
                <label>Your Review (optional)</label>
                <textarea name="review_body" class="form-control" rows="3" maxlength="1000">{{ old('review_body') }}</textarea>
            </div>

            <button type="submit" class="btn btn-dark">Submit Review</button>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('variant-select')?.addEventListener('change', function(){
    var opt = this.selectedOptions[0];
    if (!opt || !opt.value) return;

    var price = opt.dataset.price;
    if (price) document.getElementById('display-price').textContent = 'KSh ' + parseInt(price).toLocaleString();

    // Swap every stock-dependent bit of the page to the SELECTED variant's
    // own stock_qty, not the parent product's — see the comment above the
    // <select> for why that was wrong before.
    var stock   = parseInt(opt.dataset.stock, 10) || 0;
    var qtyInput = document.getElementById('qty-input');
    var addBtn   = document.getElementById('add-to-cart-btn');
    var availText = document.getElementById('stock-available-text');

    qtyInput.max = stock;
    if (stock > 0 && parseInt(qtyInput.value, 10) > stock) qtyInput.value = stock;

    addBtn.disabled = stock <= 0;
    addBtn.textContent = stock <= 0 ? 'Out of Stock' : 'Add to Cart';
    availText.textContent = stock + ' available';
});
</script>
@endpush

@if(isset($product) && $availableStock <= 0)
<div class="waitlist-box">
    <h3>Out of Stock — Join Waitlist</h3>
    <p>Get notified when this item is back in stock.</p>
    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <form method="POST" action="{{ route('shop.waitlist', $business->store_slug) }}">
    @csrf
    <input type="hidden" name="product_id" value="{{ $product->id }}">
    <div class="form-grid-2">
        <div class="form-field"><label>Your Name *</label><input type="text" name="customer_name" class="form-control" required></div>
        <div class="form-field"><label>Phone</label><input type="tel" name="customer_phone" class="form-control" placeholder="07..."></div>
    </div>
    <div class="form-field"><label>Email</label><input type="email" name="customer_email" class="form-control"></div>
    <button type="submit" class="btn btn-dark">Notify Me When Available</button>
    </form>
</div>
@endif
@endsection
