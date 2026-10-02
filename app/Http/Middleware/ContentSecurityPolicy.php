<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Content-Security-Policy for every web page.
 *
 * CSP_MODE (.env):
 *   off      — send nothing
 *   report   — Content-Security-Policy-Report-Only: the browser logs violations
 *              in its console but blocks nothing (the default; used to tune the
 *              policy against the real pages before enforcing it)
 *   enforce  — Content-Security-Policy: the browser blocks violations
 *
 * The pages use a lot of inline <script> and style="" attributes, so 'unsafe-inline'
 * is still allowed for scripts and styles. What the policy does buy:
 *   - scripts only from this site (and Cloudflare's own analytics/challenge script),
 *     so an injected <script src="https://evil…"> or a data:/blob: script is blocked
 *   - no plugins/objects, no <base> hijacking, no framing by other sites
 *   - data can only be sent to this site (connect-src), which blocks the usual
 *     "steal the page's data with fetch()" payloads
 */
class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $mode = config('security.csp_mode', 'report');
        if ($mode === 'off' || $response->headers->has('Content-Security-Policy')) {
            return $response;
        }

        $header = $mode === 'enforce' ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only';
        $response->headers->set($header, $this->policy());

        return $response;
    }

    private function policy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://static.cloudflareinsights.com https://challenges.cloudflare.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net",
            "font-src 'self' data: https://fonts.gstatic.com https://fonts.bunny.net",
            "img-src 'self' data: blob: https:",
            "media-src 'self' data: blob:",
            "connect-src 'self' https://cloudflareinsights.com",
            "worker-src 'self'",
            "manifest-src 'self'",
            "frame-src 'self' https://challenges.cloudflare.com",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);
    }
}
