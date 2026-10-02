<?php

return [
    // off | report | enforce (default: enforce; set CSP_MODE=report in .env to fall back to log-only) — see App\Http\Middleware\ContentSecurityPolicy
    'csp_mode' => env('CSP_MODE', 'enforce'),

    // ── Developer logs (App\Support\DevLog) ──────────────────────────────────
    // Kill switch: DEV_LOGGING=false in .env stops all recording immediately.
    'dev_logging' => (bool) env('DEV_LOGGING', true),

    // Days to keep activity and error logs; older rows are deleted by logs:prune.
    'log_retention_days' => (int) env('LOG_RETENTION_DAYS', 60),

    // Share of successful page views (not form submissions) that are recorded.
    'activity_sample_gets' => (float) env('ACTIVITY_SAMPLE_GETS', 0.05),

    // ── Error-spike alerts (App\Console\Commands\CheckErrorSpikes) ───────────
    // A single fingerprint recurring this many times within the window below sends one
    // email; it alerts again only once the count climbs by another threshold's worth,
    // so one noisy bug doesn't send an email every 15 minutes forever. Same recipient
    // fallback as the support chat alert: SUPPORT_ADMIN_EMAILS, else every super admin.
    'error_alert_threshold'      => (int) env('ERROR_ALERT_THRESHOLD', 5),
    'error_alert_window_minutes' => (int) env('ERROR_ALERT_WINDOW_MINUTES', 15),

    // Paths never recorded: pollers and internals that would only add noise.
    'activity_skip' => [
        'up', 'sw.js', 'offline', 'notifications*', 'dashboard/chart-data', 'dashboard/kpi-snapshot',
        'api/products/search', 'api/products/lookup', 'language/*', 'admin/logs*', 'activity-log*',
        // Sign-ins and sign-outs are recorded by their own events (with the person and, for a
        // failure, the masked email); the form request behind them would only duplicate that.
        'login', 'logout',
        // Support chat polling (every few seconds while the bubble is open) would drown the log.
        'support/thread', 'support/unread', 'admin/support/unread',
    ],
];
