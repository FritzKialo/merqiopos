<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Lets a business verify their own domain for campaign email, so a
 * promotional blast arrives as "promo@theirbusiness.com" instead of the
 * shared platform address (no-reply@merqiopos.com) — see CampaignController.
 *
 * We generate an RSA keypair per business and hand them the public half to
 * publish as a DKIM TXT record, plus an SPF include pointing back at our
 * own already-authenticated sending domain. Verification is just reading
 * those two DNS records back and checking they match what we generated —
 * no external service, no per-domain mail-server configuration, works on
 * plain shared hosting because signing happens in PHP at send time
 * (Symfony Mime's DkimSigner) rather than relying on the MTA.
 */
class DomainSettingsController extends Controller
{
    // The domain campaign mail is ultimately delivered through — used both
    // as the SPF include target we tell tenants to add, and as the DKIM
    // selector namespace so multiple tenants' records never collide.
    private const PLATFORM_DOMAIN = 'merqiopos.com';
    private const DKIM_SELECTOR   = 'merqio';

    private function business()
    {
        return Auth::user()->currentBusiness();
    }

    public function show()
    {
        $business = $this->business();

        $dnsRecords = null;
        if ($business->sending_domain && $business->dkim_public_key) {
            $dnsRecords = $this->buildDnsRecords($business->sending_domain, $business->dkim_public_key);
        }

        return view('settings.email-domain', compact('business', 'dnsRecords'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            // Bare domain only — no scheme, no path, no @ (that's an email,
            // not a domain). Matches how the field is described in the view.
            'sending_domain' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/i'],
        ], [
            'sending_domain.regex' => 'Enter a plain domain name, e.g. yourbusiness.co.ke — no https:// and no email address.',
        ]);

        $domain = strtolower($data['sending_domain']);
        $business = $this->business();

        $keyPair = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if (!$keyPair) {
            return back()->with('error', 'Could not generate a signing key on this server. Please try again or contact support.');
        }
        openssl_pkey_export($keyPair, $privateKeyPem);
        $publicKeyPem = openssl_pkey_get_details($keyPair)['key'];

        $business->sending_domain    = $domain;
        $business->dkim_selector     = self::DKIM_SELECTOR;
        $business->dkim_private_key  = $privateKeyPem;
        $business->dkim_public_key   = $publicKeyPem;
        $business->domain_verified_at = null; // (re)starting verification — a
        // changed domain must be re-verified, never inherits the old domain's
        // verified status.
        $business->save();

        return redirect()->route('settings.email-domain')
            ->with('success', 'Domain saved. Add the two DNS records below, then click "Verify" — DNS changes can take a few minutes to a few hours to propagate.');
    }

    public function verify()
    {
        $business = $this->business();

        if (!$business->sending_domain || !$business->dkim_public_key) {
            return back()->with('error', 'Add a domain first.');
        }

        $records = $this->buildDnsRecords($business->sending_domain, $business->dkim_public_key);
        $problems = [];

        $dkimHost = $records['dkim']['host'];
        $dkimFound = $this->lookupTxt($dkimHost);
        $expectedP = $records['dkim']['p_value'];
        $dkimOk = collect($dkimFound)->contains(function ($txt) use ($expectedP) {
            // TXT records are sometimes split into multiple quoted chunks by
            // DNS providers — normalize whitespace before comparing.
            return str_contains(preg_replace('/\s+/', '', $txt), preg_replace('/\s+/', '', $expectedP));
        });
        if (!$dkimOk) {
            $problems[] = "DKIM record not found (or doesn't match yet) at {$dkimHost}";
        }

        $spfFound = $this->lookupTxt($business->sending_domain);
        $spfInclude = 'include:' . self::PLATFORM_DOMAIN;
        $spfOk = collect($spfFound)->contains(fn($txt) => str_starts_with(trim($txt), 'v=spf1') && str_contains($txt, $spfInclude));
        if (!$spfOk) {
            $problems[] = "SPF record at {$business->sending_domain} doesn't include \"{$spfInclude}\" yet";
        }

        if ($problems) {
            return back()->with('error', "Not verified yet — " . implode('; ', $problems) . '.');
        }

        $business->domain_verified_at = now();
        $business->save();

        return redirect()->route('settings.email-domain')
            ->with('success', "Verified! Campaign email will now send as no-reply@{$business->sending_domain}.");
    }

    public function destroy()
    {
        $business = $this->business();
        $business->sending_domain     = null;
        $business->dkim_selector      = null;
        $business->dkim_private_key   = null;
        $business->dkim_public_key    = null;
        $business->domain_verified_at = null;
        $business->save();

        return redirect()->route('settings.email-domain')
            ->with('success', 'Custom domain removed. Campaigns will send from the shared Merqio address again.');
    }

    /**
     * @return array{dkim: array{host:string, value:string, p_value:string}, spf: array{host:string, value:string}}
     */
    private function buildDnsRecords(string $domain, string $publicKeyPem): array
    {
        $pValue = trim(str_replace(
            ["-----BEGIN PUBLIC KEY-----", "-----END PUBLIC KEY-----", "\r", "\n"],
            '',
            $publicKeyPem
        ));

        $dkimHost = self::DKIM_SELECTOR . '._domainkey.' . $domain;

        return [
            'dkim' => [
                'host'    => $dkimHost,
                'value'   => "v=DKIM1; k=rsa; p={$pValue}",
                'p_value' => $pValue,
            ],
            'spf' => [
                'host'  => $domain,
                'value' => 'v=spf1 include:' . self::PLATFORM_DOMAIN . ' ~all',
            ],
        ];
    }

    /** @return string[] every TXT record's raw string value at $host */
    private function lookupTxt(string $host): array
    {
        $records = @dns_get_record($host, DNS_TXT) ?: [];
        return array_map(fn($r) => $r['txt'] ?? '', $records);
    }
}
