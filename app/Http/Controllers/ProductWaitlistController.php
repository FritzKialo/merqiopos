<?php
namespace App\Http\Controllers;
use App\Models\ProductWaitlist;
use App\Models\Product;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductWaitlistController extends Controller {
    private function businessId() { return Auth::user()->currentBusiness()->id; }

    public function index(Request $request) {
        $businessId = $this->businessId();
        $query = ProductWaitlist::where('business_id', $businessId)->with('product');

        if ($request->product_id) {
            $query->where('product_id', $request->product_id);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }

        $entries = $query->latest()->paginate(30);
        $products = Product::where('business_id', $businessId)->where('status', 'active')->orderBy('name')->get(['id','name']);

        return view('inventory.waitlist.index', compact('entries', 'products'));
    }

    public function notify(ProductWaitlist $entry) {
        abort_if($entry->business_id !== $this->businessId(), 403);

        // The entry used to be marked "notified" before anything was sent, and
        // every failure was swallowed — so a customer with no phone, or a
        // business with SMS not set up, still showed as notified and never
        // appeared on the waiting list again.
        $business = Auth::user()->currentBusiness();
        $problem = $this->sendBackInStock($entry, $business,
            "Hi {$entry->customer_name}, great news! {$entry->product->name} is back in stock at {$business->name}. Visit us to get yours!");

        if ($problem) {
            return back()->with('error', "{$entry->customer_name} was not notified: {$problem}");
        }

        $entry->update(['status' => 'notified', 'notified_at' => now()]);

        return back()->with('success', "Notified {$entry->customer_name}.");
    }

    public function notifyAll(Request $request) {
        $request->validate(['product_id' => 'required|integer']);
        $businessId = $this->businessId();
        $entries = ProductWaitlist::where('business_id', $businessId)
            ->where('product_id', $request->product_id)
            ->where('status', 'waiting')
            ->with('product')
            ->get();

        $business = Auth::user()->currentBusiness();
        $count = 0;
        $failed = 0;
        foreach ($entries as $entry) {
            $problem = $this->sendBackInStock($entry, $business,
                "Hi {$entry->customer_name}, {$entry->product->name} is back in stock at {$business->name}!");
            if ($problem) {
                $failed++;
                continue;
            }
            $entry->update(['status' => 'notified', 'notified_at' => now()]);
            $count++;
        }

        if ($failed) {
            return back()->with($count ? 'success' : 'error',
                "Notified {$count} customer(s); {$failed} could not be reached (no phone number, or SMS is not set up or failed) and stay on the waiting list.");
        }

        return back()->with('success', "Notified {$count} customer(s).");
    }

    /** Returns null when the SMS was accepted, otherwise the reason it was not sent. */
    private function sendBackInStock(ProductWaitlist $entry, $business, string $message): ?string {
        if (! $entry->customer_phone) {
            return 'no phone number on the waitlist entry.';
        }
        try {
            $sms = \App\Services\SmsService::forBusiness($business);
            if (! $sms->isConfigured()) {
                return 'SMS is not set up (Settings → SMS).';
            }
            return $sms->send($entry->customer_phone, $message) ? null : 'the SMS provider did not accept the message.';
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Waitlist SMS failed', ['entry' => $entry->id, 'error' => $e->getMessage()]);
            return 'the SMS could not be sent.';
        }
    }

    public function destroy(ProductWaitlist $entry) {
        abort_if($entry->business_id !== $this->businessId(), 403);
        $entry->delete();
        return back()->with('success', 'Entry removed.');
    }

    public function publicRegister(Request $request, string $slug) {
        $request->validate([
            'customer_name' => 'required|string|max:200',
            'customer_phone' => 'nullable|string|max:30',
            'customer_email' => 'nullable|email|max:200',
            'product_id' => 'required|integer',
        ]);

        $business = Business::where('store_slug', $slug)->where('store_public', true)->firstOrFail();
        $product = Product::where('id', $request->product_id)
            ->where('business_id', $business->id)
            ->firstOrFail();

        // Must have at least one contact method
        if (!$request->customer_phone && !$request->customer_email) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Please provide a phone number or email address.'], 422);
            }
            return back()->withErrors(['contact' => 'Please provide a phone number or email address.']);
        }

        // One active waitlist spot per contact method per product — this
        // table has no ip_address column (unlike ProductReview), and IP
        // wouldn't be the right check here anyway: the actual duplicate
        // that matters is the same phone/email being notified twice for
        // the same item, not two different people sharing a network.
        $alreadyWaiting = ProductWaitlist::where('product_id', $product->id)
            ->where('status', 'waiting')
            ->where(function ($q) use ($request) {
                if ($request->customer_phone) $q->orWhere('customer_phone', $request->customer_phone);
                if ($request->customer_email) $q->orWhere('customer_email', $request->customer_email);
            })
            ->exists();

        if ($alreadyWaiting) {
            $message = "You're already on the waitlist for this product.";
            if ($request->wantsJson()) {
                return response()->json(['error' => $message], 422);
            }
            return back()->withErrors(['contact' => $message]);
        }

        ProductWaitlist::create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'customer_name' => $request->customer_name,
            'customer_email' => $request->customer_email,
            'customer_phone' => $request->customer_phone,
            'quantity_wanted' => 1,
            'status' => 'waiting',
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => "You've been added to the waitlist. We'll notify you when {$product->name} is back in stock."]);
        }

        return back()->with('success', "You've been added to the waitlist! We'll notify you when {$product->name} is back in stock.");
    }
}
