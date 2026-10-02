<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\OnlineOrder;
use App\Models\OnlineOrderItem;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductVariant;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoreController extends Controller
{
    private function getBusiness(string $slug): Business
    {
        $business = Business::where('store_slug', $slug)->where('store_public', true)->firstOrFail();
        return $business;
    }

    /**
     * Price tiers are a per-CUSTOMER relationship a business assigns
     * (e.g. "this wholesale customer always pays tier-2 pricing") — not
     * something an anonymous shop visitor can pick for themselves. The
     * only way it can correctly apply here is if the visitor is also
     * signed into their customer portal account (a separate login/guard
     * entirely — see CustomerPortalAuth) for THIS SAME business, and that
     * account has a tier assigned. Returns null (standard pricing) for
     * every anonymous visitor, and for a portal customer logged in under
     * a DIFFERENT business than this shop's.
     */
    private function resolveCustomerTierId(Business $business): ?int
    {
        $customer = Auth::guard('customer')->user();
        if ($customer && $customer->business_id === $business->id) {
            return $customer->price_tier_id;
        }
        return null;
    }

    public function index(string $slug, Request $request)
    {
        $business = $this->getBusiness($slug);

        // Visits that arrived by scanning the shop's QR code (its link ends ?src=qr).
        if ($request->query('src') === 'qr') {
            try {
                \App\Models\Business::where('id', $business->id)->increment('shop_qr_scans');
            } catch (\Throwable $e) {
                // the counter must never stop a shopper reaching the shop
            }
        }

        $query = Product::where('business_id', $business->id)
            ->where('stock_qty', '>', 0)
            ->where('hide_in_shop', false)
            ->whereNull('deleted_at');

        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        // Featured products first, then alphabetical within each group.
        $products = $query->orderByDesc('is_featured')->orderBy('name')->paginate(24)->withQueryString();

        // Only categories that actually have a purchasable product right
        // now — an empty filter chip that shows zero results is worse than
        // not offering it at all. Must mirror every filter the product
        // query above applies (stock, soft-delete, AND hide_in_shop) — a
        // category whose only in-stock products are all hidden from the
        // shop was still showing here, a dead-end chip that always led to
        // "No products available" once clicked.
        $categories = Category::where('business_id', $business->id)
            ->whereHas('products', fn ($q) => $q->where('stock_qty', '>', 0)->where('hide_in_shop', false)->whereNull('deleted_at'))
            ->orderBy('name')
            ->get();

        // Bundles span categories by nature, so they're only shown on the
        // unfiltered "All" view — otherwise picking a category would leave
        // it unclear whether a bundle belongs there. Only bundles every
        // component of which is currently fulfillable are offered, same
        // "don't show what can't actually be bought" rule as products.
        $bundles = collect();
        if (!$request->filled('category')) {
            $bundles = ProductBundle::where('business_id', $business->id)
                ->where('is_active', true)
                ->with('items.product')
                ->get()
                ->filter(fn ($bundle) => $this->bundleMaxFulfillable($bundle) > 0)
                ->values();
        }

        $cart     = session('cart_' . $slug, []);
        $cartCount = array_sum(array_column($cart, 'qty'));
        $tierId    = $this->resolveCustomerTierId($business);
        return view('shop.index', compact('business', 'products', 'categories', 'bundles', 'cart', 'cartCount', 'tierId'));
    }

    /**
     * How many of this bundle can actually be sold right now, given each
     * component's own live stock — the limiting component caps it (e.g. a
     * bundle needing 2 of an item with only 5 left in stock can only be
     * sold 2 times, not 5).
     */
    private function bundleMaxFulfillable(ProductBundle $bundle): int
    {
        $max = null;
        foreach ($bundle->items as $item) {
            $available = $item->variant_id
                ? (ProductVariant::find($item->variant_id)?->stock_qty ?? 0)
                : (Product::find($item->product_id)?->stock_qty ?? 0);
            $possible = $item->quantity > 0 ? (int) floor($available / $item->quantity) : 0;
            $max = $max === null ? $possible : min($max, $possible);
        }
        return (int) ($max ?? 0);
    }

    public function product(string $slug, Product $product)
    {
        $business = $this->getBusiness($slug);
        abort_if($product->business_id !== $business->id, 404);
        abort_if($product->hide_in_shop, 404);
        $variants = $product->has_variants ? $product->activeVariants()->get() : collect();
        $cart     = session('cart_' . $slug, []);
        $cartCount = array_sum(array_column($cart, 'qty'));

        // Approved reviews only — pending/rejected stay invisible to
        // shoppers until a staff member acts on them in Product Reviews.
        $reviews = \App\Models\ProductReview::where('product_id', $product->id)
            ->where('business_id', $business->id)
            ->approved()
            ->latest()
            ->get();
        $reviewCount = $reviews->count();
        $avgRating   = $reviewCount ? round($reviews->avg('rating'), 1) : null;
        $tierId      = $this->resolveCustomerTierId($business);

        return view('shop.product', compact(
            'business', 'product', 'variants', 'cartCount', 'reviews', 'reviewCount', 'avgRating', 'tierId'
        ));
    }

    public function addToCart(string $slug, Request $request)
    {
        $business = $this->getBusiness($slug);
        $request->validate(['product_id' => 'required|integer', 'qty' => 'nullable|integer|min:1', 'variant_id' => 'nullable|integer']);

        // hide_in_shop is checked on the product PAGE (product() aborts 404),
        // but this endpoint is hit directly by its own POST — nothing here
        // stopped someone who already knows/guesses a product_id from adding
        // a hidden product straight to their cart and completing checkout,
        // bypassing "hide from shop" entirely.
        $product = Product::where('id', $request->product_id)
            ->where('business_id', $business->id)
            ->where('hide_in_shop', false)
            ->firstOrFail();
        $variant = $request->variant_id
            ? ProductVariant::where('id', $request->variant_id)->where('product_id', $product->id)->first()
            : null;

        $key  = $product->id . ($variant ? '_v' . $variant->id : '');
        $cart = session('cart_' . $slug, []);

        // Only ever enforced at checkout before now — a customer could add
        // 500 of something with 5 in stock, see "500" sitting in their cart
        // the whole time, and only find out it's wrong on the final step.
        // Capping here doesn't replace the checkout re-check (stock can
        // still drop between now and then), it just gives honest feedback
        // immediately instead of at the worst possible moment.
        $available = $variant?->stock_qty ?? $product->stock_qty;
        if ($available <= 0) {
            return back()->with('error', $product->name . ' is out of stock.');
        }

        $requestedQty = (int) ($request->qty ?? 1);
        $existingQty  = $cart[$key]['qty'] ?? 0;
        $newQty       = min($existingQty + $requestedQty, $available);

        if (isset($cart[$key])) {
            $cart[$key]['qty'] = $newQty;
        } else {
            // Price tiers only ever apply to a product's own base price
            // (Product::priceForTier()) — variants have their own separate
            // price override mechanism entirely, with no tier awareness in
            // the schema, so a variant's own effectivePrice() is left as
            // the final word whenever one was selected.
            $tierId = $this->resolveCustomerTierId($business);
            $cart[$key] = [
                'product_id'   => $product->id,
                'variant_id'   => $variant?->id,
                'name'         => $product->name . ($variant ? ' — ' . $variant->name : ''),
                'price'        => (float) ($variant?->effectivePrice() ?? $product->priceForTier($tierId)),
                'qty'          => $newQty,
            ];
        }

        session(['cart_' . $slug => $cart]);

        $message = $newQty < ($existingQty + $requestedQty)
            ? "Only {$available} of \"{$product->name}\" available — added what's in stock."
            : $product->name . ' added to cart.';

        return back()->with('success', $message);
    }

    /**
     * Add a product bundle to the cart. Kept as its own endpoint/method
     * rather than folded into addToCart() — a bundle has no single
     * stock_qty of its own (availability depends on every component), and
     * mixing that branch into the plain-product path would make both
     * harder to follow for no real benefit, since the shop grid already
     * posts to one or the other depending on which kind of card it is.
     */
    public function addBundleToCart(string $slug, Request $request)
    {
        $business = $this->getBusiness($slug);
        $request->validate(['bundle_id' => 'required|integer', 'qty' => 'nullable|integer|min:1']);

        $bundle = ProductBundle::where('id', $request->bundle_id)
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->with('items')
            ->firstOrFail();

        $maxFulfillable = $this->bundleMaxFulfillable($bundle);
        if ($maxFulfillable <= 0) {
            return back()->with('error', $bundle->name . ' is currently unavailable (one or more of its items is out of stock).');
        }

        $key  = 'bundle_' . $bundle->id;
        $cart = session('cart_' . $slug, []);

        $requestedQty = (int) ($request->qty ?? 1);
        $existingQty  = $cart[$key]['qty'] ?? 0;
        $newQty       = min($existingQty + $requestedQty, $maxFulfillable);

        $cart[$key] = [
            'product_id' => null,
            'variant_id' => null,
            'bundle_id'  => $bundle->id,
            // Matches how it's labeled everywhere else it shows up (order
            // confirmation, receipt, invoice) — without this a bundle in
            // the cart looked identical to a regular product, no way to
            // tell them apart.
            'name'       => $bundle->name . ' (Bundle)',
            'price'      => (float) $bundle->price,
            'qty'        => $newQty,
        ];
        session(['cart_' . $slug => $cart]);

        $message = $newQty < ($existingQty + $requestedQty)
            ? "Only {$maxFulfillable} of \"{$bundle->name}\" available — added what's in stock."
            : $bundle->name . ' added to cart.';

        return back()->with('success', $message);
    }

    /**
     * Remove one line item from the cart. Previously there was no way for a
     * customer to undo a mistaken "Add to Cart" click short of clearing
     * their whole session/cookies — every existing shop template rendered
     * a static quantity/name with no removal control at all.
     */
    public function removeFromCart(string $slug, string $key)
    {
        $this->getBusiness($slug);
        $cart = session('cart_' . $slug, []);
        unset($cart[$key]);
        session(['cart_' . $slug => $cart]);
        return back()->with('success', 'Item removed from cart.');
    }

    /**
     * Update a line item's quantity in place. Re-checks live stock — the
     * cart is session-only, so nothing else validates this before checkout.
     */
    public function updateCartQty(string $slug, Request $request)
    {
        $business = $this->getBusiness($slug);
        $request->validate(['key' => 'required|string', 'qty' => 'required|integer|min:1']);

        $cart = session('cart_' . $slug, []);
        if (isset($cart[$request->key])) {
            $item = $cart[$request->key];
            if (!empty($item['bundle_id'])) {
                $bundle    = ProductBundle::with('items')->find($item['bundle_id']);
                $available = $bundle ? $this->bundleMaxFulfillable($bundle) : 0;
            } else {
                $available = !empty($item['variant_id'])
                    ? (ProductVariant::find($item['variant_id'])?->stock_qty ?? 0)
                    : (Product::where('id', $item['product_id'])->where('business_id', $business->id)->value('stock_qty') ?? 0);
            }

            $cart[$request->key]['qty'] = max(1, min((int) $request->qty, max(1, (int) $available)));
            session(['cart_' . $slug => $cart]);
        }

        return back()->with('success', 'Cart updated.');
    }

    /**
     * A cart item's price is captured once, at the moment it's added (see
     * addToCart()) — it was never re-checked again after that. That's a
     * real pricing-integrity gap, not just a staleness nuisance: price
     * tiers are only supposed to apply to a portal customer authenticated
     * for THIS business (see resolveCustomerTierId()), but nothing stopped
     * someone from logging in, adding a tier-priced item to their cart,
     * then logging out (or the reverse — logging in after adding at
     * standard price) and checking out with the wrong price still frozen
     * in session. Re-pricing here against the CURRENT auth state closes
     * that gap the same way revalidateSessionCoupon() already closes the
     * equivalent one for coupons. Bundles and variants are untouched —
     * bundle price never depends on tier, and a variant's own
     * effectivePrice() already has no tier awareness to go stale.
     */
    private function refreshCartPricing(array $cart, Business $business): array
    {
        $tierId = $this->resolveCustomerTierId($business);

        foreach ($cart as $key => &$item) {
            if (!empty($item['bundle_id']) || !empty($item['variant_id'])) {
                continue;
            }
            $product = Product::where('id', $item['product_id'])
                ->where('business_id', $business->id)
                ->first();
            if ($product) {
                $item['price'] = (float) $product->priceForTier($tierId);
            }
        }
        unset($item);

        return $cart;
    }

    public function cart(string $slug)
    {
        $business  = $this->getBusiness($slug);
        $cart      = $this->refreshCartPricing(session('cart_' . $slug, []), $business);
        session(['cart_' . $slug => $cart]);
        $cartCount = array_sum(array_column($cart, 'qty'));
        $subtotal  = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $cart));
        $deliveryZones = json_decode($business->delivery_zones ?? '[]', true) ?: [];

        // A coupon applied earlier can stop qualifying without the customer
        // ever touching it directly — removing an item, or the coupon
        // itself expiring/hitting its usage limit mid-session. Previously
        // the cart kept showing it as applied (stale discount, stale total)
        // right up until checkout, which silently dropped it with no
        // explanation — the customer saw one total on this page and got
        // charged a different, higher one with no idea why. Re-validating
        // here means the cart page itself is always the source of truth.
        $couponInvalidMessage = null;
        $appliedCoupon = $this->revalidateSessionCoupon($business, $slug, $subtotal, $couponInvalidMessage);

        return view('shop.cart', compact('business', 'cart', 'subtotal', 'cartCount', 'deliveryZones', 'appliedCoupon', 'couponInvalidMessage'));
    }

    /**
     * Re-checks the session-stored coupon against the CURRENT subtotal —
     * not the subtotal at the moment it was applied — and clears it from
     * session if it no longer qualifies (min order amount, expiry, usage
     * limit, or the coupon having been deleted/deactivated since). Shared
     * by cart() (for display) and checkout() (for the actual charge), so
     * both always agree on whether a coupon is genuinely still valid.
     *
     * @param-out string|null $invalidMessage set when a previously-applied
     *   coupon was just cleared, so the caller can tell the customer why.
     */
    private function revalidateSessionCoupon(Business $business, string $slug, float $subtotal, ?string &$invalidMessage): ?array
    {
        $sessionCoupon = session('coupon_' . $slug);
        if (!$sessionCoupon || empty($sessionCoupon['code'])) {
            return null;
        }

        $coupon = Coupon::where('business_id', $business->id)
            ->where('code', $sessionCoupon['code'])
            ->first();

        if (!$coupon || !$coupon->isValid($subtotal)) {
            session()->forget('coupon_' . $slug);
            $invalidMessage = $coupon
                ? 'Your coupon "' . $coupon->code . '" no longer applies to this order (minimum order amount, expiry, or usage limit) and was removed.'
                : 'Your coupon is no longer available and was removed.';
            return null;
        }

        return ['code' => $coupon->code, 'discount' => $coupon->calculateDiscount($subtotal)];
    }

    /**
     * Coupons already exist as a full feature — creation, validity rules
     * (active/date-window/usage-limit/min-order), and application — but only
     * ever for the in-person POS. Nothing anywhere let an online shop
     * customer use one. This mirrors CouponController::validateCoupon()'s
     * logic but scoped through the shop's own slug (never a client-supplied
     * business_id) since shop visitors aren't authenticated staff.
     */
    public function applyCoupon(string $slug, Request $request)
    {
        $business = $this->getBusiness($slug);
        $request->validate(['code' => 'required|string|max:50']);

        $cart     = session('cart_' . $slug, []);
        $subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $cart));

        $coupon = Coupon::where('business_id', $business->id)
            ->where('code', strtoupper($request->code))
            ->first();

        if (!$coupon) {
            return response()->json(['valid' => false, 'message' => 'Coupon code not found.']);
        }

        if (!$coupon->isValid($subtotal)) {
            $msg = 'This coupon is not valid.';
            if ($coupon->expires_at && $coupon->expires_at->isPast()) $msg = 'This coupon has expired.';
            elseif ($coupon->max_uses !== null && $coupon->effectiveUses() >= $coupon->max_uses) $msg = 'This coupon has reached its usage limit.';
            elseif ($subtotal < $coupon->min_order_amount) $msg = 'Minimum order of KSh ' . number_format($coupon->min_order_amount, 2) . ' required.';
            return response()->json(['valid' => false, 'message' => $msg]);
        }

        $discount = $coupon->calculateDiscount($subtotal);
        session(['coupon_' . $slug => ['code' => $coupon->code, 'discount' => $discount]]);

        return response()->json([
            'valid'    => true,
            'discount' => $discount,
            'message'  => 'Coupon applied! You save KSh ' . number_format($discount, 2),
            'code'     => $coupon->code,
        ]);
    }

    public function removeCoupon(string $slug)
    {
        session()->forget('coupon_' . $slug);
        return back()->with('success', 'Coupon removed.');
    }

    public function checkout(string $slug, Request $request)
    {
        $business = $this->getBusiness($slug);
        $cart     = session('cart_' . $slug, []);

        if (empty($cart)) {
            return back()->with('error', 'Your cart is empty.');
        }

        // See refreshCartPricing() doc comment — re-price against the
        // CURRENT auth state right before charging, not just when the cart
        // page happens to be viewed (a customer can go straight from
        // product page to checkout without ever loading /cart).
        $cart = $this->refreshCartPricing($cart, $business);
        session(['cart_' . $slug => $cart]);

        $request->validate([
            'customer_name'     => 'required|string|max:255',
            'customer_phone'    => 'required|string|max:30',
            'customer_email'    => 'nullable|email|max:255',
            'delivery_address'  => 'nullable|string|max:500',
            'delivery_zone'     => 'nullable|string|max:255',
            'payment_method'    => 'nullable|in:mpesa,cash',
            'notes'             => 'nullable|string|max:1000',
        ]);

        // Nothing anywhere in this flow previously checked stock availability
        // — someone could order 1,000 of an item with 5 in stock and the
        // order would go through. Re-check against live stock right before
        // creating the order (cart contents can be stale by the time someone
        // checks out).
        foreach ($cart as $item) {
            if (!empty($item['bundle_id'])) {
                $bundle = ProductBundle::where('id', $item['bundle_id'])
                    ->where('business_id', $business->id)
                    ->where('is_active', true)
                    ->with('items')
                    ->first();
                if (!$bundle) {
                    return back()->with('error', "Sorry, \"{$item['name']}\" is no longer available.");
                }
                $maxFulfillable = $this->bundleMaxFulfillable($bundle);
                if ($item['qty'] > $maxFulfillable) {
                    return back()->with('error', "Sorry, only {$maxFulfillable} of \"{$item['name']}\" available.");
                }
                continue;
            }

            $product = Product::find($item['product_id']);
            // A product hidden AFTER it was added to a cart (a session can
            // outlive that change) must not be purchasable just because it
            // made it into the cart before the setting flipped.
            if (!$product || $product->hide_in_shop) {
                return back()->with('error', "Sorry, \"{$item['name']}\" is no longer available.");
            }
            if (!empty($item['variant_id'])) {
                $variant = ProductVariant::find($item['variant_id']);
                $available = $variant?->stock_qty ?? 0;
            } else {
                $available = $product->stock_qty;
            }
            if ($item['qty'] > $available) {
                return back()->with('error', "Sorry, only {$available} of \"{$item['name']}\" left in stock.");
            }
        }

        // Block an accidental resubmission — a double-tap on "Place Order",
        // or a slow network response tempting a retry — from creating a
        // second real order (with its own stock decrement and, for M-Pesa,
        // its own STK push to the same phone). Deliberately narrow: same
        // phone within the last 2 minutes only, regardless of cart
        // contents. Unlike the reviews/waitlist duplicate checks, this
        // must NOT block a genuine second order later — a real customer
        // can and does order twice in the same hour — so this only ever
        // catches near-simultaneous repeats, not distinct orders.
        $recentDuplicate = OnlineOrder::where('business_id', $business->id)
            ->where('customer_phone', $request->customer_phone)
            ->where('created_at', '>=', now()->subMinutes(2))
            ->exists();
        if ($recentDuplicate) {
            return back()->with('error', 'It looks like you just placed this order — check your orders before submitting again.');
        }

        $subtotal  = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $cart));
        $reference = 'ORD-' . strtoupper(Str::random(8));

        // Delivery fee only applies when the customer actually wants
        // delivery (i.e. gave an address) — a pickup order pays nothing
        // extra. If the business has configured named zones, the fee comes
        // from whichever zone the customer picked; otherwise it falls back
        // to the business's single flat rate when an address was given.
        // Previously this whole feature (businesses.delivery_fee /
        // delivery_zones, already configurable in Settings) was never read
        // anywhere in checkout — every online order charged KSh 0 delivery
        // regardless of what the business had configured.
        //
        // This was gated entirely on delivery_address being non-empty —
        // wrong for the zone-based cart form, where the address field is
        // just optional exact-location detail and a customer can pick a
        // real (paid) zone while leaving it blank, silently zeroing the fee
        // even though they clearly asked for delivery to a specific zone.
        $deliveryFee = 0;
        $zones = json_decode($business->delivery_zones ?? '[]', true) ?: [];
        if (!empty($zones)) {
            if ($request->delivery_zone) {
                $match = collect($zones)->firstWhere('name', $request->delivery_zone);
                // delivery_zone is a plain form field, trivially tampered
                // with via devtools — a name that doesn't match any of the
                // business's real configured zones previously fell through
                // to a silent $0 fee rather than being rejected, letting a
                // customer request real delivery (with a real address) and
                // pay nothing for it.
                if (!$match) {
                    return back()->with('error', 'Please select a valid delivery option.')->withInput();
                }
                $deliveryFee = (float) ($match['fee'] ?? 0);
            }
        } elseif (!empty($request->delivery_address)) {
            $deliveryFee = (float) ($business->delivery_fee ?? 0);
        }

        // Re-validate the coupon fresh at submit time rather than trusting
        // the discount amount stashed in session when it was applied —
        // same reasoning as the live stock re-check above: min-order
        // amount, expiry, and usage-limit can all have changed since then.
        // If it's no longer valid, REJECT the checkout outright rather than
        // silently proceeding without it — the cart page just showed the
        // customer a total that included this discount, and charging them
        // a different, higher amount with no explanation is exactly the
        // gap this whole re-validation exists to close. Usage itself
        // (used_count / CouponUsage) is only recorded once the order is
        // actually PAID (see markOnlineOrderPaid()), not here — an order
        // that stays pending forever or gets cancelled shouldn't
        // permanently burn a limited-use coupon, matching how stock itself
        // is also only decremented on payment, not on order creation.
        $couponCode      = null;
        $couponDiscount  = 0;
        $hadCouponInCart = !empty(session('coupon_' . $slug)['code']);
        $couponInvalidMessage = null;
        $validCoupon = $this->revalidateSessionCoupon($business, $slug, $subtotal, $couponInvalidMessage);

        if ($hadCouponInCart && !$validCoupon) {
            return back()->with('error', $couponInvalidMessage . ' Please review your cart and try again.')->withInput();
        }

        if ($validCoupon) {
            $couponCode     = $validCoupon['code'];
            $couponDiscount = $validCoupon['discount'];
        }

        $paymentMethod = $request->payment_method === 'cash' ? 'cash' : 'mpesa';
        $total         = max(0, $subtotal - $couponDiscount) + $deliveryFee;

        $order = OnlineOrder::create([
            'business_id'            => $business->id,
            'customer_name'          => $request->customer_name,
            'customer_phone'         => $request->customer_phone,
            'customer_email'         => $request->customer_email,
            'delivery_address'       => $request->delivery_address,
            'subtotal'               => $subtotal,
            'delivery_fee'           => $deliveryFee,
            'coupon_code'            => $couponCode,
            'coupon_discount_amount' => $couponDiscount,
            'total'                  => $total,
            'payment_method'         => $paymentMethod,
            'notes'                  => $request->notes,
            'reference'              => $reference,
            'status'                 => 'pending',
        ]);

        session()->forget('coupon_' . $slug);

        foreach ($cart as $item) {
            if (!empty($item['bundle_id'])) {
                $bundle = ProductBundle::with('items')->find($item['bundle_id']);
                if (!$bundle) continue; // already caught by the re-check above

                // One summary line at the bundle's price, tagged with its
                // first component's product_id (online_order_items.product_id
                // is required — there's no such thing as a line item with no
                // product at all) purely so the order/invoice display shows
                // "Bundle Name x1" instead of a confusing $0 component list.
                // Flagged is_bundle_summary so it's correctly skipped when
                // stock actually moves below — see markOnlineOrderPaid() and
                // the SaleService fix this mirrors (a real, previously-live
                // bug: decrementing this display line too double-counted the
                // bundle's first component on every sale).
                OnlineOrderItem::create([
                    'online_order_id'   => $order->id,
                    'product_id'        => $bundle->items->first()->product_id,
                    'variant_id'        => null,
                    'product_name'      => $bundle->name . ' (Bundle)',
                    'quantity'          => $item['qty'],
                    'unit_price'        => $item['price'],
                    'total'             => $item['price'] * $item['qty'],
                    'is_bundle_summary' => true,
                ]);

                foreach ($bundle->items as $bItem) {
                    OnlineOrderItem::create([
                        'online_order_id' => $order->id,
                        'product_id'      => $bItem->product_id,
                        'variant_id'      => $bItem->variant_id,
                        'product_name'    => $bItem->product->name ?? 'Bundle item',
                        'quantity'        => $bItem->quantity * $item['qty'],
                        'unit_price'      => 0,
                        'total'           => 0,
                    ]);
                }
                continue;
            }

            $product = Product::find($item['product_id']);
            OnlineOrderItem::create([
                'online_order_id' => $order->id,
                'product_id'      => $item['product_id'],
                'variant_id'      => $item['variant_id'] ?? null,
                'product_name'    => $item['name'],
                'quantity'        => $item['qty'],
                'unit_price'      => $item['price'],
                'total'           => $item['price'] * $item['qty'],
            ]);
        }

        // Trigger M-Pesa STK push — only when the customer actually chose to
        // pay now (not Cash on Delivery) and the business has credentials.
        if ($paymentMethod === 'mpesa' && $business->hasMpesaConfigured()) {
            try {
                $mpesa    = new \App\Services\MpesaService($business->mpesaCredentials());
                $phone    = $request->customer_phone;
                // Charge the full total INCLUDING delivery fee — this was
                // previously always $subtotal, so a delivery order would
                // have charged less than the order actually totalled.
                // Route this callback to the shop's own handler (which knows
                // how to look up an OnlineOrder), not the platform-wide
                // default that only recognizes POS/MpesaTransaction payments.
                $response = $mpesa->stkPush($phone, $total, $reference, 'Online order ' . $reference, route('shop.mpesa.callback'));
                // stkPush() returns Daraja's raw response — it has no
                // 'success' key at all (that check always evaluated false,
                // meaning mpesa_checkout_id was NEVER saved here, ever —
                // so the webhook below could never match this order back
                // to update it). stkPush() only returns at all when Daraja
                // actually accepted the push (it throws otherwise), so a
                // successful call here always means CheckoutRequestID is
                // real and should be saved.
                $order->update(['mpesa_checkout_id' => $response['CheckoutRequestID'] ?? null]);
            } catch (\Throwable $e) {
                // Bumped from warning() — invisible in production, whose
                // LOG_LEVEL only records error and above (see ReceiptService.php).
                \Illuminate\Support\Facades\Log::error('Shop STK push failed', ['order' => $reference, 'error' => $e->getMessage()]);
            }
        }

        session()->forget('cart_' . $slug);
        return redirect()->route('shop.order', [$slug, $reference]);
    }

    public function orderConfirmation(string $slug, string $reference)
    {
        $business = $this->getBusiness($slug);
        $order    = OnlineOrder::where('reference', $reference)->where('business_id', $business->id)->firstOrFail();

        // Same gap as the POS's "Waiting for payment…" screen, same fix:
        // Safaricom's callback is not guaranteed to ever arrive (confirmed
        // happening on this exact install — see MpesaController::status()),
        // so a customer stuck on "Awaiting Payment Confirmation" who clicks
        // "Refresh Status" was only ever re-reading our own DB, which a
        // missing callback would never have updated. Actively ask Safaricom
        // directly instead, once the order's had a moment to actually
        // process (querying immediately after the STK push returns its own
        // "still processing" error from Daraja).
        if ($order->status === 'pending'
            && $order->mpesa_checkout_id
            && $order->created_at->diffInSeconds(now()) >= 8) {
            $this->reconcileOrderViaStkQuery($order);
            $order->refresh();
        }

        $order->load('items');
        return view('shop.confirmation', compact('business', 'order'));
    }

    /**
     * Mirrors mpesaCallback()'s success branch exactly, so an order
     * resolved this way ends up in the identical state one resolved by a
     * normal callback would. Only ever moves an order forward on a
     * confirmed Daraja success — a definitive failure/cancellation is
     * deliberately left as-is (still 'pending') rather than guessed into
     * some other status; there's no established "payment failed" state for
     * an online order, and auto-cancelling a customer's order because one
     * payment attempt didn't go through is a real product decision, not
     * something to make unilaterally as a side effect of a status check.
     */
    private function reconcileOrderViaStkQuery(OnlineOrder $order): void
    {
        try {
            $business = $order->business;
            if (!$business || !$business->hasMpesaConfigured()) return;

            $mpesa  = new \App\Services\MpesaService($business->mpesaCredentials());
            $result = $mpesa->queryStkStatus($order->mpesa_checkout_id);

            if ($result['stillProcessing']) {
                return;
            }

            if ($order->fresh()->status !== 'pending') {
                // A callback arrived while this query was in flight.
                return;
            }

            if ($result['resultCode'] === 0) {
                $this->markOnlineOrderPaid($order);
            }
            // Non-zero ResultCode (cancelled/failed/timeout): left pending,
            // see the method doc comment above.
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Online order STK query reconciliation failed', [
                'order' => $order->reference,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mark an order paid and process it — shared by both the webhook
     * (mpesaCallback) and the active-query fallback (reconcileOrderViaStkQuery)
     * above, so an order resolved either way ends up in the identical state.
     */
    private function markOnlineOrderPaid(OnlineOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $order->update(['status' => 'paid', 'payment_confirmed_at' => now()]);
            // Decrement stock — the variant's, not the parent product's,
            // when the line item was a specific variant (same rule as
            // the POS: SaleService::createSale decrements the variant).
            // Skip the bundle summary line (display-only) — its own
            // components already get their own real decrement via their
            // own rows below; decrementing the summary line too would
            // double-count the bundle's first component, the same bug
            // just fixed in SaleService.
            foreach ($order->items as $item) {
                if ($item->is_bundle_summary) {
                    continue;
                }
                \App\Models\ProductBatch::consume((int) $item->product_id, $item->variant_id ?: null, (float) $item->quantity);
                if ($item->variant_id) {
                    ProductVariant::where('id', $item->variant_id)->decrement('stock_qty', $item->quantity);
                } else {
                    Product::where('id', $item->product_id)->decrement('stock_qty', $item->quantity);
                }
            }

            $this->recordCouponUsage($order);

            // Record it as a real Sale so it shows up in the Sales
            // list, dashboard revenue, and every report.
            (new SaleService())->createSaleFromOnlineOrder($order->fresh());
        });
    }

    /**
     * Only recorded once an order is actually PAID, not at checkout — same
     * timing as the stock decrement above, so a pending order that's later
     * cancelled or just abandoned never permanently burns a limited-use
     * coupon.
     */
    private function recordCouponUsage(OnlineOrder $order): void
    {
        if (!$order->coupon_code) return;

        $coupon = Coupon::where('business_id', $order->business_id)
            ->where('code', $order->coupon_code)
            ->first();
        if (!$coupon) return;

        $coupon->increment('used_count');
        CouponUsage::create([
            'coupon_id'        => $coupon->id,
            'business_id'      => $order->business_id,
            'online_order_id'  => $order->id,
            'discount_applied' => $order->coupon_discount_amount,
            'used_at'          => now(),
        ]);
    }

    /**
     * Generate a scannable M-Pesa QR code for this order's total — an
     * alternative to waiting on the STK push already sent, useful when the
     * customer is checking out on a desktop/laptop (an STK prompt on their
     * phone still works, but scanning is often faster and doesn't depend on
     * that push actually arriving). A QR-completed payment settles through
     * the ordinary C2B webhook, matched by the order reference — see
     * MpesaController::c2bConfirm().
     */
    public function mpesaQr(string $slug, string $reference)
    {
        $business = $this->getBusiness($slug);
        $order    = OnlineOrder::where('reference', $reference)->where('business_id', $business->id)->firstOrFail();

        if ($order->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'This order is no longer awaiting payment.'], 422);
        }

        if (! $business->hasMpesaConfigured()) {
            return response()->json(['success' => false, 'message' => 'M-Pesa is not set up for this shop.'], 422);
        }

        try {
            $mpesa = new \App\Services\MpesaService($business->mpesaCredentials());
            $qr    = $mpesa->generateQrCode($order->total, $order->reference, $business->name);

            return response()->json(['success' => true, 'qr_code' => $qr, 'amount' => $order->total]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Shop QR generation failed', ['order' => $reference, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Could not generate QR code right now.'], 400);
        }
    }

    public function mpesaCallback(Request $request)
    {
        $body     = $request->json()->all();
        $stk      = $body['Body']['stkCallback'] ?? [];
        $checkoutId = $stk['CheckoutRequestID'] ?? null;
        $resultCode = $stk['ResultCode'] ?? null;

        if ($checkoutId && $resultCode === 0) {
            $order = OnlineOrder::where('mpesa_checkout_id', $checkoutId)->first();
            // Guard against a duplicate webhook delivery — Safaricom does
            // not guarantee exactly-once delivery, and without this check
            // a resend would double-decrement stock and create a second
            // Sale for the same order every time it re-fires.
            if ($order && $order->status === 'pending') {
                $this->markOnlineOrderPaid($order);
            }
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }
}
