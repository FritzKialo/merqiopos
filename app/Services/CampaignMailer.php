<?php

namespace App\Services;

use App\Models\Business;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Crypto\DkimSigner;
use Symfony\Component\Mime\Email;

/**
 * Sends one campaign email, branded as the sending business rather than the
 * shared platform address — see the "Why should a campaign come from
 * Merqio POS" conversation this was built for.
 *
 * Every business gets the display-name + reply-to treatment (works
 * immediately, no setup, keeps the platform's already-authenticated
 * sending domain so deliverability isn't at risk). A business that has
 * verified their own domain (Business::hasVerifiedSendingDomain()) instead
 * sends genuinely as their own address, DKIM-signed in PHP with the
 * keypair generated for them in DomainSettingsController — no ESP, no
 * per-domain mail-server config, works on plain shared hosting because the
 * signature is added to the message before Laravel's existing SMTP
 * transport delivers it.
 */
class CampaignMailer
{
    public function send(Business $business, string $to, string $subject, string $textBody, ?string $htmlBody = null, ?string $unsubscribeUrl = null): void
    {
        $verified = $business->hasVerifiedSendingDomain();

        $email = (new Email())
            // The business name as sender only when the message really comes from
            // the business's own verified domain. With the platform's address, a
            // business-name sender looks like impersonation and the hosting spam
            // filter quarantined every such message.
            ->from(new Address($business->campaignFromAddress(), $verified ? $business->name : config('mail.from.name')))
            ->to($to)
            ->subject($subject)
            ->text($textBody);

        // replyTo(null) is an error, and a business is not required to have an email.
        // It's also only set when it matches the From address's own domain — a mismatch
        // (e.g. sending from the platform's address but replying to a Gmail address) is
        // exactly what got these messages quarantined as spam by the host's filter.
        $from = $business->campaignFromAddress();
        if (\App\Support\MailSafety::domainMatches($business->email, $from)) {
            $email->replyTo($business->email);
        }

        // The standard header mail apps (Gmail, Apple Mail) turn into their own
        // "Unsubscribe" button, and which bulk-mail filters look for.
        if ($unsubscribeUrl) {
            $email->getHeaders()->addTextHeader('List-Unsubscribe', '<' . $unsubscribeUrl . '>');
        }

        if ($htmlBody !== null) {
            $email->html($htmlBody);
        }

        if ($verified) {
            $signer = new DkimSigner(
                $business->dkim_private_key,
                $business->sending_domain,
                $business->dkim_selector
            );
            $email = $signer->sign($email);
        }

        // Reuse Laravel's already-configured SMTP transport/credentials
        // rather than duplicating them into a second DSN — this only
        // changes the message we hand it, not how or where it connects.
        Mail::mailer(config('mail.default'))->getSymfonyTransport()->send($email);
    }
}
