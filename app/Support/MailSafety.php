<?php

namespace App\Support;

use Illuminate\Mail\Mailables\Address;

/**
 * A customer-facing email is sent "From" the platform's own verified sending
 * address, with "Reply-To" the business's own address so customer replies reach
 * the shop directly. ImunifyEmail (the host's outbound spam filter) treats a
 * From/Reply-To domain mismatch as suspicious — reported by hosting support
 * after every invoice/quote/receipt for a business with a Gmail-style address
 * was silently quarantined. Reply-To only stays on when it's the platform's
 * own domain; otherwise it's dropped and the business's contact details (already
 * shown in the email body, e.g. business-footer.blade.php) are how the
 * customer reaches them.
 */
class MailSafety
{
    /** @return Address[] */
    public static function replyTo(?string $email, ?string $name): array
    {
        $from = config('mail.from.address', 'noreply@merqiopos.com');

        return self::domainMatches($email, $from) ? [new Address($email, $name)] : [];
    }

    // Same check, but against a specific From address rather than always the platform's own —
    // for CampaignMailer (Symfony Mime, not Illuminate's Address class), where a verified
    // business sends from their own domain, not the platform's.
    public static function domainMatches(?string $email, string $fromAddress): bool
    {
        return $email && str_ends_with(strtolower(trim($email)), '@' . strtolower(self::domainOf($fromAddress)));
    }

    private static function domainOf(string $address): string
    {
        return trim(strrchr($address, '@'), '@');
    }
}
