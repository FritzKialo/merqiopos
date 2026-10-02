<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\ErrorLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

/**
 * Developer logs — what stores do, and what fails.
 *
 * Two rules everything here follows:
 *   1. It must NEVER break the request it is watching. Every public method is
 *      wrapped so a logging problem (a full disk, a missing table on a server
 *      that has not run the migration yet) is swallowed.
 *   2. It never stores what a person typed. No request bodies, no query string,
 *      no headers, no cookies — only the page, the action, who and where, the
 *      outcome, and the error text. Failed logins keep a masked email
 *      ("n***@gmail.com") and the IP, which is what spotting a break-in needs.
 */
class DevLog
{
    /** A short id shown on the error page and stored with the log entry. */
    public static function requestId(?Request $request = null): string
    {
        $request = $request ?? (app()->bound('request') ? request() : null);
        if (! $request) {
            return Str::lower(Str::random(10));
        }
        if (! $request->attributes->has('dev_request_id')) {
            $request->attributes->set('dev_request_id', Str::lower(Str::random(10)));
        }
        return $request->attributes->get('dev_request_id');
    }

    /** Who and where, from the current request. */
    public static function context(?Request $request = null, bool $lookupStore = true): array
    {
        $request = $request ?? (app()->bound('request') ? request() : null);
        $user    = Auth::guard('web')->user();
        $ctx = [
            'business_id' => null, 'organization_id' => null, 'user_id' => $user?->id, 'role' => $user?->role,
            'method' => $request?->method(), 'path' => null, 'route_name' => null,
        ];

        // A real web request has a matched route (or we are not on the command line at
        // all); a bare artisan command has neither.
        $isWeb = $request && (! app()->runningInConsole() || $request->route() !== null);

        if ($isWeb) {
            $ctx['path']       = Str::limit('/' . ltrim($request->path(), '/'), 250, '');
            $ctx['route_name'] = $request->route()?->getName();
            try {
                $businessId = $request->hasSession() ? $request->session()->get('active_business_id') : null;
                if (! $businessId && $user && $lookupStore) {
                    $businessId = $user->currentBusiness()?->id;
                }
                $ctx['business_id']     = $businessId;
                $ctx['organization_id'] = $user?->organization_id;
            } catch (Throwable $e) {
                // context is best-effort
            }
        } elseif (app()->runningInConsole()) {
            $ctx['method'] = 'CLI';
            $ctx['path']   = Str::limit('artisan ' . implode(' ', array_slice($_SERVER['argv'] ?? [], 1, 2)), 250, '');
        }

        return $ctx;
    }

    // ── Errors ──────────────────────────────────────────────────────────────────

    public static function captureException(Throwable $e): void
    {
        try {
            if (! config('security.dev_logging', true) || self::isDemo()) return;

            $ctx = self::context();

            $message  = self::safeMessage($e);
            $file     = self::shortPath($e->getFile());
            $line     = (int) $e->getLine();
            // Numbers and quoted values differ between two hits of the same bug
            // (ids, names, amounts), so they are blanked before fingerprinting.
            $shape    = preg_replace(['/\d+/', "/'[^']*'/", '/"[^"]*"/'], ['#', '?', '?'], $message);
            $fp       = sha1(get_class($e) . '|' . $file . '|' . $line . '|' . $shape);
            $now      = now();

            // The same bug in the same store on the same day is one row with a counter.
            $existing = ErrorLog::where('fingerprint', $fp)
                ->where('business_id', $ctx['business_id'])
                ->where('last_seen_at', '>=', $now->copy()->subDay())
                ->orderByDesc('id')->first();

            if ($existing) {
                $existing->update([
                    'count'        => $existing->count + 1,
                    'last_seen_at' => $now,
                    'resolved_at'  => null,
                    'user_id'      => $ctx['user_id'] ?? $existing->user_id,
                    'request_id'   => self::requestId(),
                ]);
                return;
            }

            ErrorLog::create($ctx + [
                'fingerprint'   => $fp,
                'status_code'   => method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500,
                'exception'     => Str::limit(get_class($e), 185, ''),
                'message'       => $message,
                'file'          => $file,
                'line'          => $line,
                'trace'         => self::trace($e),
                'request_id'    => self::requestId(),
                'count'         => 1,
                'first_seen_at' => $now,
                'last_seen_at'  => $now,
            ]);
        } catch (Throwable $inner) {
            // Never let logging an error raise another one.
        }
    }

    // ── Activity ────────────────────────────────────────────────────────────────

    public static function activity(array $data): void
    {
        try {
            if (! config('security.dev_logging', true) || self::isDemo()) return;

            ActivityLog::create($data + [
                'kind'       => 'request',
                'outcome'    => 'ok',
                'ip'         => app()->bound('request') ? request()->ip() : null,
                'agent'      => app()->bound('request') ? Str::limit((string) request()->userAgent(), 155, '') : null,
                'request_id' => self::requestId(),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // best-effort
        }
    }

    /** "nainterr@gmail.com" → "n***@gmail.com" */
    // Demo sandboxes (DemoGuard sets the flag) are not real customers: keep them out of the logs.
    private static function isDemo(): bool
    {
        return app()->bound('request') && (bool) request()->attributes->get('is_demo');
    }

    public static function maskEmail(?string $email): ?string
    {
        if (! $email || ! str_contains($email, '@')) return $email ? '***' : null;
        [$local, $domain] = explode('@', $email, 2);
        return Str::lower(Str::substr($local, 0, 1) . '***@' . $domain);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────

    /**
     * Database errors carry the real data in their text: "Duplicate entry
     * 'jane@example.com' for key…", or the whole SQL statement with its values
     * after "(Connection: …". Both are cut so customer details never reach a log.
     */
    private static function safeMessage(Throwable $e): string
    {
        $m = (string) $e->getMessage();
        $m = preg_replace('/\s*\(Connection:.*$/s', '', $m);
        $m = preg_replace("/Duplicate entry '.*?' for key/s", "Duplicate entry '…' for key", $m);
        $m = preg_replace("/Data truncated for column '([^']*)' at row \d+/", "Data truncated for column '$1'", $m);
        return Str::limit($m, 1000, '…');
    }

    private static function shortPath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $base = str_replace('\\', '/', base_path()) . '/';
        return Str::limit(str_starts_with($path, $base) ? substr($path, strlen($base)) : $path, 250, '');
    }

    /** The first frames of the stack trace, without arguments (which could hold data). */
    private static function trace(Throwable $e): string
    {
        $lines = [];
        foreach (array_slice($e->getTrace(), 0, 12) as $frame) {
            if (! isset($frame['file'])) continue;
            $lines[] = self::shortPath($frame['file']) . ':' . ($frame['line'] ?? '?') . ' ' . ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '');
        }
        return implode("\n", $lines);
    }
}
