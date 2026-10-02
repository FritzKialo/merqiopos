<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\PlatformNewsletterMail;
use App\Models\AppNotification;
use App\Models\Newsletter;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NewsletterController extends Controller
{
    public function index()
    {
        $newsletters = Newsletter::with('sender')->latest()->paginate(15);

        return view('admin.newsletters.index', compact('newsletters'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'           => 'required|string|max:150',
            'message'         => 'required|string|max:5000',
            'audience_status' => 'nullable|in:active,trial,suspended',
            'audience_plan'   => 'nullable|in:solo,growth,enterprise',
        ]);

        $orgQuery = Organization::query();

        if (!empty($data['audience_status'])) {
            $orgQuery->where('status', $data['audience_status']);
        }
        if (!empty($data['audience_plan'])) {
            $orgQuery->where('subscription_plan', $data['audience_plan']);
        }

        $organizations = $orgQuery->get(['id']);

        $owners = User::whereIn('organization_id', $organizations->pluck('id'))
            ->where('role', 'owner')
            ->get();

        if ($owners->isEmpty()) {
            return back()->with('error', 'No organizations matched that audience — nothing was sent.')->withInput();
        }

        $emailFailures = 0;

        foreach ($owners as $owner) {
            $business = $owner->currentBusiness();

            if ($business) {
                AppNotification::send(
                    $business->id,
                    $owner->id,
                    'newsletter',
                    $data['title'],
                    $data['message'],
                    null,
                    'newspaper'
                );
            }

            if ($owner->email) {
                try {
                    Mail::to($owner->email)->send(
                        new PlatformNewsletterMail($data['title'], $data['message'], $owner->name)
                    );
                } catch (\Throwable $e) {
                    $emailFailures++;
                    Log::warning('Platform newsletter email failed', [
                        'owner_id' => $owner->id,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }
        }

        Newsletter::create([
            'sender_id'          => Auth::id(),
            'title'              => $data['title'],
            'message'            => $data['message'],
            'audience_status'    => $data['audience_status'] ?? null,
            'audience_plan'      => $data['audience_plan'] ?? null,
            'recipient_count'    => $owners->count(),
            'email_failed_count' => $emailFailures,
        ]);

        $count = $owners->count();
        $msg = "Newsletter sent to {$count} organization owner(s).";
        if ($emailFailures > 0) {
            $msg .= " {$emailFailures} email(s) failed to send (in-app notification still delivered) — check logs.";
        }

        return redirect()->route('admin.newsletters.index')->with('success', $msg);
    }
}
