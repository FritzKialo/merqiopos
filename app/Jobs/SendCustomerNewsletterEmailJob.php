<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\CustomerNewsletter;
use App\Models\CustomerNotification;
use App\Services\CampaignMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class SendCustomerNewsletterEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Failures (bad SMTP, invalid address) are caught and logged inside
    // handle() itself rather than left to bubble up, so this never actually
    // needs a queue-level retry — it always completes "successfully" from
    // the worker's point of view even when the send inside it failed.
    public $tries = 1;

    public function __construct(
        public int $customerNewsletterId,
        public int $customerId,
    ) {}

    public function handle(CampaignMailer $mailer): void
    {
        $customer = Customer::find($this->customerId);
        if (!$customer || !$customer->email || $customer->newsletter_unsubscribed_at) {
            return;
        }

        $newsletter = CustomerNewsletter::find($this->customerNewsletterId);
        if (!$newsletter) {
            return;
        }

        // Branded (and, if verified, DKIM-signed) as the CUSTOMER's own
        // business, not the sending store — same reasoning as Memo's
        // org-wide notifications: a customer at a different branch should
        // see this as coming from their own branch, not whichever branch
        // the sender happened to be in.
        $business = $customer->business;
        if (!$business) {
            return;
        }

        CustomerNotification::send(
            $customer->business_id,
            $customer->id,
            'newsletter',
            $newsletter->title,
            $newsletter->message,
            null,
            'newspaper'
        );

        try {
            $unsubscribeUrl = URL::signedRoute('portal.newsletters.unsubscribe', ['customer' => $customer->id]);

            // CampaignMailer already solves "send as this business, not as
            // the shared merqiopos.com address" (proper from/reply-to, plus
            // DKIM-signed custom-domain sending once verified) for the
            // existing Campaigns feature — reused here instead of
            // duplicating that logic for a second sender path.
            $html = view('emails.customer-newsletter', [
                'subjectLine'    => $newsletter->title,
                'body'           => $newsletter->message,
                'customerName'   => $customer->name,
                'businessName'   => $business->name,
                'unsubscribeUrl' => $unsubscribeUrl,
            ])->render();

            $mailer->send($business, $customer->email, $newsletter->title,
                $newsletter->message . "\n\n--\nUnsubscribe: " . $unsubscribeUrl, $html, $unsubscribeUrl);
        } catch (\Throwable $e) {
            $newsletter->increment('email_failed_count');
            // error, not warning: production only records error and above, so a
            // failed newsletter email used to leave no trace at all.
            Log::error('Customer newsletter email failed', [
                'customer_id'    => $customer->id,
                'newsletter_id'  => $newsletter->id,
                'error'          => $e->getMessage(),
            ]);
        }
    }
}
