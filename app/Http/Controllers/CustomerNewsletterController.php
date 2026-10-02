<?php

namespace App\Http\Controllers;

use App\Jobs\SendCustomerNewsletterEmailJob;
use App\Models\Customer;
use App\Models\CustomerNewsletter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Marketing/announcement emails a store (or, for multi-store orgs, every
 * store at once) sends to its own customers — distinct from Memo, which is
 * staff-only and in-app-only. Delivery here is queued (see
 * SendCustomerNewsletterEmailJob) since a customer list can be large enough
 * that sending synchronously inside the request would time out.
 */
class CustomerNewsletterController extends Controller
{
    public function index()
    {
        $user     = Auth::user();
        $business = $user->currentBusiness();
        $org      = $user->organization ?? $business?->organization;

        $newsletters = CustomerNewsletter::where('business_id', $business->id)
            ->with('sender')
            ->latest()
            ->paginate(15);

        $multiStore = $org && $org->businesses()->count() > 1;

        return view('settings.newsletters', [
            'newsletters' => $newsletters,
            'multiStore'  => $multiStore,
        ]);
    }

    public function store(Request $request)
    {
        $user     = Auth::user();
        $business = $user->currentBusiness();
        $org      = $user->organization ?? $business?->organization;

        if (!$business) {
            return back()->with('error', 'No business context found.');
        }

        $data = $request->validate([
            'title'   => 'required|string|max:150',
            'message' => 'required|string|max:5000',
            'scope'   => 'required|in:store,org',
        ]);

        if ($data['scope'] === 'org' && (!$org || $org->businesses()->count() <= 1)) {
            return back()->with('error', 'Organization-wide newsletters need more than one store — this org only has one.')->withInput();
        }

        $businessIds = $data['scope'] === 'org'
            ? $org->businesses()->pluck('id')
            : collect([$business->id]);

        $customers = Customer::whereIn('business_id', $businessIds)
            ->whereNotNull('email')
            ->whereNull('newsletter_unsubscribed_at')
            ->get(['id']);

        if ($customers->isEmpty()) {
            return back()->with('error', 'No subscribed customers with an email address matched that audience — nothing was sent.')->withInput();
        }

        $newsletter = CustomerNewsletter::create([
            'business_id'     => $business->id,
            'sender_id'       => $user->id,
            'scope'           => $data['scope'],
            'title'           => $data['title'],
            'message'         => $data['message'],
            'recipient_count' => $customers->count(),
        ]);

        foreach ($customers as $customer) {
            SendCustomerNewsletterEmailJob::dispatch($newsletter->id, $customer->id);
        }

        $count = $customers->count();

        return redirect()->route('settings.newsletters.index')
            ->with('success', "Newsletter queued for {$count} customer(s). Delivery happens in the background — refresh this page shortly to see any email failures.");
    }

    // Only the send-history record is removed; emails already delivered aren't recalled.
    public function destroy(CustomerNewsletter $newsletter)
    {
        $business = Auth::user()->currentBusiness();
        abort_unless($business && $newsletter->business_id === $business->id, 404);

        $newsletter->delete();

        return back()->with('success', 'Newsletter record removed.');
    }

    /**
     * Public, signed, no auth required — reached from the unsubscribe link
     * in the email itself. A customer who never logs into the portal must
     * still be able to opt out with one click.
     */
    public function unsubscribeConfirm(Customer $customer, Request $request)
    {
        return view('portal.unsubscribe-confirm', [
            'businessName' => $customer->business?->name ?? config('app.name'),
            // The same signed URL is used for the POST, so it cannot be tampered with.
            'confirmUrl'   => $request->fullUrl(),
            'alreadyDone'  => (bool) $customer->newsletter_unsubscribed_at,
        ]);
    }

    public function unsubscribe(Customer $customer)
    {
        $customer->update(['newsletter_unsubscribed_at' => $customer->newsletter_unsubscribed_at ?? now()]);

        return view('portal.unsubscribed', ['businessName' => $customer->business?->name ?? config('app.name')]);
    }
}
