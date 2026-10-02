<?php

namespace Tests\Unit;

use App\Support\MailSafety;
use Tests\TestCase;

/**
 * Covers the fix for ImunifyEmail quarantining every invoice/quote/receipt email whose
 * Reply-To (the business's own address) didn't share a domain with the From address
 * (the platform's) — see app/Mail/InvoiceEmail.php etc. and CampaignMailer.
 */
class MailSafetyTest extends TestCase
{
    /** @test */
    public function reply_to_is_dropped_when_the_domain_does_not_match_the_platforms_from_address(): void
    {
        config(['mail.from.address' => 'noreply@merqiopos.com']);

        $this->assertSame([], MailSafety::replyTo('shop@gmail.com', 'A Shop'));
    }

    /** @test */
    public function reply_to_is_kept_when_the_domain_matches_the_platforms_from_address(): void
    {
        config(['mail.from.address' => 'noreply@merqiopos.com']);

        $addresses = MailSafety::replyTo('owner@merqiopos.com', 'Owner');

        $this->assertCount(1, $addresses);
        $this->assertSame('owner@merqiopos.com', $addresses[0]->address);
        $this->assertSame('Owner', $addresses[0]->name);
    }

    /** @test */
    public function reply_to_is_dropped_for_a_null_email(): void
    {
        config(['mail.from.address' => 'noreply@merqiopos.com']);

        $this->assertSame([], MailSafety::replyTo(null, 'Nobody'));
    }

    /** @test */
    public function domain_matches_is_checked_against_the_given_from_address_not_only_the_platforms(): void
    {
        // CampaignMailer sends from a verified business's OWN domain, not the platform's —
        // domainMatches() takes that From address explicitly rather than always the config default.
        $this->assertTrue(MailSafety::domainMatches('shop@theirdomain.co.ke', 'no-reply@theirdomain.co.ke'));
        $this->assertFalse(MailSafety::domainMatches('shop@gmail.com', 'no-reply@theirdomain.co.ke'));
    }

    /** @test */
    public function domain_matching_is_case_insensitive_and_trims_whitespace(): void
    {
        $this->assertTrue(MailSafety::domainMatches(' Owner@MERQIOPOS.com ', 'noreply@merqiopos.com'));
    }
}
