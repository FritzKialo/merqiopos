<?php

namespace App\Http\Middleware;

use App\Support\DevLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Records what each store does, and what fails, for the developer logs.
 *
 * It runs in terminate(): after the response has already gone to the browser, so
 * it adds nothing to how fast a page feels. Form submissions (POST/PUT/PATCH/
 * DELETE) are always recorded; page views only when they failed, plus a small
 * random sample so the timeline is not empty. Nothing the user typed is stored.
 */
class LogActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('dev_started', microtime(true));
        DevLog::requestId($request);

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            if (! config('security.dev_logging', true) || $this->skip($request)) return;

            $status = $response->getStatusCode();
            $write  = ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);
            [$outcome, $message] = $this->outcome($request, $response, $status);

            if (! $write && $outcome === 'ok' && (mt_rand() / mt_getrandmax()) > (float) config('security.activity_sample_gets', 0.05)) {
                return;
            }
            // Public pages nobody is signed in to (the marketing site, the shop) are not "store activity".
            if (! Auth::guard('web')->check() && $outcome === 'ok') return;

            $ctx = DevLog::context($request, $write || $outcome !== 'ok');

            DevLog::activity($ctx + [
                'kind'        => 'request',
                'status'      => $status,
                'outcome'     => $outcome,
                'message'     => $message ? Str::limit($message, 250, '…') : null,
                'duration_ms' => (int) round((microtime(true) - (float) $request->attributes->get('dev_started', microtime(true))) * 1000),
            ]);
        } catch (Throwable $e) {
            // never break a request over logging
        }
    }

    private function skip(Request $request): bool
    {
        $path = trim($request->path(), '/');
        foreach ((array) config('security.activity_skip', []) as $pattern) {
            if (Str::is($pattern, $path)) return true;
        }
        return false;
    }

    /** [outcome, message] — what actually happened, even when the status is a friendly 302. */
    private function outcome(Request $request, Response $response, int $status): array
    {
        if ($status >= 500)            return ['error', 'Server error (' . $status . ')'];
        if (in_array($status, [401, 403], true)) return ['denied', 'Not allowed (' . $status . ')'];
        if ($status === 419)           return ['failed', 'Session expired (419)'];
        if ($status === 429)           return ['failed', 'Too many requests (429)'];
        if ($status === 422)           return ['validation', 'Rejected input (422)'];
        if ($status >= 400 && $status !== 404) return ['failed', 'HTTP ' . $status];

        // A rejected form or a refused action answers with a friendly redirect,
        // so the real outcome is in what was flashed to the session.
        if ($response->isRedirection() && $request->hasSession()) {
            $session = $request->session();
            $errors  = $session->get('errors');
            if ($errors instanceof ViewErrorBag && $errors->any()) {
                return ['validation', (string) $errors->first()];
            }
            $flash = $session->get('error');
            if (is_string($flash) && $flash !== '') {
                $denied = preg_match('/do not have access|not allowed|unauthori[sz]ed|forbidden|permission/i', $flash);
                return [$denied ? 'denied' : 'failed', $flash];
            }
        }

        return ['ok', null];
    }
}
