<?php

return [
    // Who is emailed when a tenant writes. Comma-separated in .env (SUPPORT_ADMIN_EMAILS);
    // when empty, every super admin's own email is used.
    'admin_emails' => array_values(array_filter(array_map('trim', explode(',', (string) env('SUPPORT_ADMIN_EMAILS', ''))))),

    // At most one alert email per conversation in this many minutes, however many messages arrive.
    'alert_cooldown_minutes' => (int) env('SUPPORT_ALERT_COOLDOWN', 10),
];
