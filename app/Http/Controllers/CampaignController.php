<?php
namespace App\Http\Controllers;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CampaignController extends Controller {
    private function businessId() { return Auth::user()->currentBusiness()->id; }

    private function resolveRecipients(string $segmentType, ?array $config, int $businessId, ?string $channel = null): \Illuminate\Support\Collection {
        $query = Customer::where('business_id', $businessId);

        // Only people the campaign can actually reach. Everyone else used to be
        // included and then counted as "delivered" without anything being sent.
        // Customers who unsubscribed from emails are never emailed.
        if ($channel === 'email') {
            $query->whereNotNull('email')->where('email', '!=', '')->whereNull('newsletter_unsubscribed_at');
        } elseif (in_array($channel, ['sms', 'whatsapp'], true)) {
            $query->whereNotNull('phone')->where('phone', '!=', '');
        }
        switch ($segmentType) {
            case 'by_tag':
                $tagIds = $config['tag_ids'] ?? [];
                if ($tagIds) $query->whereHas('tags', fn($q) => $q->whereIn('customer_tags.id', $tagIds));
                break;
            case 'by_tier':
                $tier = $config['tier_name'] ?? '';
                if ($tier) $query->where('loyalty_tier', $tier);
                break;
            case 'inactive':
                $days = $config['inactive_days'] ?? 30;
                $query->whereDoesntHave('sales', fn($q) => $q->where('created_at', '>=', now()->subDays($days)));
                break;
        }
        return $query->get();
    }

    public function index() {
        $campaigns = Campaign::where('business_id', $this->businessId())->latest()->paginate(20);
        return view('campaigns.index', compact('campaigns'));
    }

    public function create() {
        $tags = DB::table('customer_tags')->where('business_id', $this->businessId())->get();
        return view('campaigns.create', compact('tags'));
    }

    public function store(Request $request) {
        $request->validate(['name'=>'required|string|max:200','channel'=>'required|in:sms,email,whatsapp','segment_type'=>'required','message_template'=>'required|string|max:1000']);
        $businessId = $this->businessId();
        $config = [];
        if ($request->segment_type === 'by_tag') $config['tag_ids'] = $request->tag_ids ?? [];
        if ($request->segment_type === 'by_tier') $config['tier_name'] = $request->tier_name;
        if ($request->segment_type === 'inactive') $config['inactive_days'] = $request->inactive_days ?? 30;

        $recipients = $this->resolveRecipients($request->segment_type, $config, $businessId, $request->channel);

        $campaign = Campaign::create([
            'business_id' => $businessId,
            'name' => $request->name,
            'channel' => $request->channel,
            'segment_type' => $request->segment_type,
            'segment_config' => $config,
            'message_template' => $request->message_template,
            'status' => 'draft',
            'recipient_count' => $recipients->count(),
        ]);

        foreach ($recipients as $customer) {
            CampaignRecipient::create([
                'campaign_id' => $campaign->id,
                'customer_id' => $customer->id,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'status' => 'pending',
            ]);
        }

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Campaign created with ' . $recipients->count() . ' recipients.');
    }

    public function show(Campaign $campaign) {
        abort_if($campaign->business_id !== $this->businessId(), 403);
        $campaign->load('recipients.customer');
        return view('campaigns.show', compact('campaign'));
    }

    public function send(Campaign $campaign) {
        // Without this, any business could send SMS/email to another
        // business's customers under their own campaign template.
        abort_if($campaign->business_id !== $this->businessId(), 403);
        // Claimed in one atomic step: two clicks (or two tabs) used to both read
        // 'draft' and send the whole campaign twice.
        $claimed = Campaign::where('id', $campaign->id)->where('status', 'draft')->update(['status' => 'sending']);
        if (! $claimed) return back()->with('error', 'Campaign already sent.');
        $business = Auth::user()->currentBusiness();
        $sent = 0; $failed = 0;
        foreach ($campaign->recipients()->where('status', 'pending')->with('customer')->get() as $recipient) {
            $message = str_replace(
                ['{name}', '{business_name}'],
                [$recipient->customer->name ?? 'Customer', $business->name],
                $campaign->message_template
            );
            try {
                $delivered = false;
                $reason = null;

                if ($campaign->channel === 'sms') {
                    if (! $recipient->phone) {
                        $reason = 'No phone number.';
                    } else {
                        $delivered = app(\App\Services\SmsService::class)->send($recipient->phone, $message);
                        if (! $delivered) $reason = 'The SMS provider did not accept the message (check SMS settings and credit).';
                    }
                } elseif ($campaign->channel === 'whatsapp') {
                    if (! $recipient->phone) {
                        $reason = 'No phone number.';
                    } else {
                        $wa = new \App\Services\WhatsAppService($business);
                        $delivered = $wa->send($recipient->phone, $message);
                        if (! $delivered) $reason = $wa->getLastError() ?: 'WhatsApp did not accept the message (check WhatsApp settings).';
                    }
                } elseif ($campaign->channel === 'email') {
                    if (! $recipient->email) {
                        $reason = 'No email address.';
                    } elseif ($recipient->customer?->newsletter_unsubscribed_at) {
                        $reason = 'Customer has unsubscribed from emails.';
                    } else {
                        // Every marketing email carries a one-click unsubscribe link.
                        $unsubscribe = \Illuminate\Support\Facades\URL::signedRoute('portal.newsletters.unsubscribe', ['customer' => $recipient->customer_id]);
                        // Previously Mail::raw() with no from()/replyTo() at all,
                        // so every business's campaigns arrived as the shared
                        // "Merqio POS <no-reply@merqiopos.com>" platform address
                        // — indistinguishable from any other tenant's campaign
                        // and unrecognizable to the customer as coming from this
                        // business. See app/Services/CampaignMailer.php.
                        // Same branded layout as customer newsletters. A bare
                        // plain-text message with a long link is what mail
                        // filters treat as junk.
                        $html = view('emails.customer-newsletter', [
                            'subjectLine'    => $campaign->name,
                            'body'           => $message,
                            'customerName'   => $recipient->customer->name ?? 'Customer',
                            'businessName'   => $business->name,
                            'unsubscribeUrl' => $unsubscribe,
                            'showGreeting'   => false,
                        ])->render();
                        app(\App\Services\CampaignMailer::class)
                            ->send($business, $recipient->email, $campaign->name,
                                $message . "\n\n--\nDon't want these emails? Unsubscribe: " . $unsubscribe, $html, $unsubscribe);
                        $delivered = true;
                    }
                } else {
                    $reason = 'Unknown channel.';
                }

                if ($delivered) {
                    $recipient->update(['status' => 'sent', 'sent_at' => now()]);
                    $sent++;
                } else {
                    $recipient->update(['status' => 'failed', 'error_message' => mb_substr((string) $reason, 0, 250)]);
                    $failed++;
                }
            } catch (\Exception $e) {
                $recipient->update(['status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 250)]);
                $failed++;
            }
        }
        $campaign->update(['status' => 'sent', 'sent_count' => $sent, 'failed_count' => $failed, 'sent_at' => now()]);
        return back()->with('success', "Campaign sent. $sent delivered, $failed failed.");
    }

    public function destroy(Campaign $campaign) {
        abort_if($campaign->business_id !== $this->businessId(), 403);
        $campaign->delete();
        return redirect()->route('campaigns.index')->with('success', 'Deleted.');
    }

    public function preview(Request $request) {
        $businessId = $this->businessId();
        $count = $this->resolveRecipients($request->segment_type ?? 'all', $request->all(), $businessId, $request->channel)->count();
        return response()->json(['count' => $count]);
    }
}
