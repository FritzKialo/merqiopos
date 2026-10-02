<?php
namespace App\Http\Controllers;
use App\Models\ProductReview;
use App\Models\Business;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductReviewController extends Controller {
    private function businessId() { return Auth::user()->currentBusiness()->id; }

    public function index(Request $request) {
        $reviews = ProductReview::where('business_id', Auth::user()->currentBusiness()->id)
            ->with('product')->latest()->paginate(20);
        return view('product-reviews.index', compact('reviews'));
    }

    public function store(Request $request, string $slug) {
        $business = Business::where('store_slug', $slug)->firstOrFail();
        $request->validate([
            'reviewer_name' => 'required|string|max:200',
            'reviewer_email' => 'nullable|email|max:200',
            'rating' => 'required|integer|min:1|max:5',
            'review_body' => 'nullable|string|max:1000',
            'product_id' => 'required|integer',
        ]);

        // 'exists:products,id' alone only checked the product exists
        // SOMEWHERE in the whole table — not that it belongs to THIS shop.
        // A crafted product_id from a different business would create a
        // review tagged with this shop's business_id but another
        // business's product, which then surfaces in this shop's own
        // review-moderation queue (product-reviews.index), leaking that
        // other business's product name/price to staff who have no
        // legitimate reason to see it.
        $product = Product::where('id', $request->product_id)
            ->where('business_id', $business->id)
            ->firstOrFail();

        // One review per device per product — rate limiting (see the route)
        // stops a burst of submissions, this stops the same person quietly
        // submitting several over time (e.g. once a day) for the same item.
        // Not identity-proof (a different device/network bypasses it) but a
        // real, free deterrent against the common case.
        $alreadyReviewed = ProductReview::where('product_id', $request->product_id)
            ->where('ip_address', $request->ip())
            ->exists();

        if ($alreadyReviewed) {
            return back()->withErrors(['review' => "You've already reviewed this product."])->withInput();
        }

        ProductReview::create([
            'business_id' => $business->id,
            'product_id' => $request->product_id,
            'reviewer_name' => $request->reviewer_name,
            'reviewer_email' => $request->reviewer_email,
            'rating' => $request->rating,
            'review_body' => $request->review_body,
            'status' => 'pending',
            'ip_address' => $request->ip(),
        ]);
        // A distinct flash key from the plain 'success' used elsewhere on
        // this same product page (e.g. the out-of-stock waitlist form) —
        // both sections can appear together, and sharing one key would
        // show this message twice (once per section that checks it).
        return back()->with('review_success', 'Thank you for your review! It will appear once approved.');
    }

    public function approve(ProductReview $review) {
        abort_if($review->business_id !== $this->businessId(), 403);
        $review->update(['status' => 'approved']);
        return back()->with('success', 'Review approved.');
    }

    public function reject(ProductReview $review) {
        abort_if($review->business_id !== $this->businessId(), 403);
        $review->update(['status' => 'rejected']);
        return back()->with('success', 'Review rejected.');
    }

    public function destroy(ProductReview $review) {
        abort_if($review->business_id !== $this->businessId(), 403);
        $review->delete();
        return back()->with('success', 'Review deleted.');
    }
}
