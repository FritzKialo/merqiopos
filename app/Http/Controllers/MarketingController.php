<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class MarketingController extends Controller
{
    public function home()
    {
        return view('marketing.home');
    }

    public function pricing()
    {
        return view('marketing.pricing');
    }

    public function contact()
    {
        return view('marketing.contact');
    }

    public function privacy()
    {
        return view('marketing.privacy');
    }

    public function terms()
    {
        return view('marketing.terms');
    }

    public function disclaimer()
    {
        return view('marketing.disclaimer');
    }

    // Getting-started guide / user manual — public (no login required), linked
    // from both the marketing navbar and footer. Covers account creation
    // through every major feature; see resources/views/marketing/guide.blade.php.
    public function guide()
    {
        return view('marketing.guide');
    }

    public function sendContact(Request $request)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:100',
            'email'   => 'required|email|max:150',
            'phone'   => 'nullable|string|max:30',
            'subject' => 'required|string|in:general,pricing,technical,demo,other',
            'message' => 'required|string|min:10|max:2000',
        ]);

        // No DB table backs this form (it's email + log only), so a cache
        // flag stands in for the DB-existence checks used on the other
        // public forms — same intent: block a double-tap of "Send" from
        // emailing support twice, not a genuine second enquiry later.
        $dupeKey = 'contact_dupe_' . md5(strtolower($validated['email']));
        if (Cache::has($dupeKey)) {
            return redirect()->route('contact')
                ->with('error', "You've just sent a message — we've got it, no need to send it again.");
        }
        Cache::put($dupeKey, true, now()->addMinutes(2));

        \Log::info('Contact form submission', $validated);

        try {
            \Mail::to('support@merqiopos.com')->send(new \App\Mail\ContactEnquiry($validated));
        } catch (\Exception $e) {
            \Log::error('Contact form email failed to send', ['error' => $e->getMessage()]);
            return redirect()->route('contact')
                ->with('error', 'Sorry, something went wrong sending your message. Please try again or reach us directly on WhatsApp.');
        }

        return redirect()->route('contact')
            ->with('success', 'Thanks ' . $validated['name'] . '! We\'ve received your message and will get back to you within one business day.');
    }
}
