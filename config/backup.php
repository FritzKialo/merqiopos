<?php

return [

    // Where the daily backup (see App\Console\Commands\RunBackup) emails
    // its off-site copy. Leave unset in .env to skip emailing entirely —
    // the on-server backup under storage/app/backups still runs either way.
    'notify_email' => env('BACKUP_NOTIFY_EMAIL'),

];
