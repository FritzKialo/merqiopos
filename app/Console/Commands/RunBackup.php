<?php

namespace App\Console\Commands;

use App\Mail\BackupCompleted;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use ZipArchive;

/**
 * Daily backup of the database + every tenant's uploaded files (logos,
 * product images, receipt attachments, etc.) — kept on-server for 14 days
 * AND emailed off-site so a server-level disaster doesn't take the only
 * copy with it. Deliberately excludes .env: it holds real M-Pesa/SMTP/DB
 * credentials and the app key, and those have no business leaving the
 * server via email even to ourselves — restore those manually from the
 * server itself if it's ever truly lost, not from an emailed backup.
 */
class RunBackup extends Command
{
    protected $signature = 'sme:backup';
    protected $description = 'Back up the database and uploaded files, keep 14 days on-server, and email a copy off-site.';

    private const RETENTION_DAYS = 14;
    // A single email realistically can't carry more than this — most SMTP
    // relays and inboxes reject well before 25MB once base64 attachment
    // overhead (~33%) is added. Below this, still attempt to email; above
    // it, the on-server backup still completes, just not emailed — logged
    // clearly either way so a growing database doesn't fail silently.
    private const MAX_EMAIL_BYTES = 15 * 1024 * 1024;

    public function handle(): int
    {
        $timestamp = now()->format('Y-m-d_His');
        $backupDir = storage_path('app/backups');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $this->info("Starting backup {$timestamp}...");

        $dbFile = $this->backupDatabase($backupDir, $timestamp);
        if (!$dbFile) {
            return self::FAILURE;
        }

        $filesFile = $this->backupUploadedFiles($backupDir, $timestamp);

        $this->emailOffSite($dbFile, $filesFile);
        $this->cleanupOldBackups($backupDir);

        $this->info('Backup complete.');
        return self::SUCCESS;
    }

    /**
     * Dumps the database via mysqldump and gzips it. Credentials are
     * written to a short-lived, restrictively-permissioned option file
     * rather than passed on the command line, where they'd briefly be
     * visible to anyone else on the server running `ps aux`.
     *
     * Shells out via Laravel's Process facade (Symfony Process, backed by
     * proc_open) rather than PHP's exec(). Some hosts — including
     * production's, as of 2026-09-25 — disable the whole exec/shell_exec/
     * system/passthru family as a hardening measure but leave proc_open
     * available; every backup since that change was silently failing with
     * a fatal "Call to undefined function exec()" instead of the clean
     * failure this method is actually designed to produce.
     */
    private function backupDatabase(string $backupDir, string $timestamp): ?string
    {
        $connection = config('database.default');
        $db = config("database.connections.{$connection}");

        $sqlFile = "{$backupDir}/db_{$timestamp}.sql";
        $gzFile  = "{$sqlFile}.gz";
        $optionFile = tempnam(sys_get_temp_dir(), 'sme_backup_');

        file_put_contents($optionFile, sprintf(
            "[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n",
            $db['host'] ?? '127.0.0.1',
            $db['port'] ?? 3306,
            $db['username'] ?? '',
            $db['password'] ?? ''
        ));
        chmod($optionFile, 0600);

        $mysqldump = env('MYSQLDUMP_PATH', 'mysqldump');
        $command = sprintf(
            '%s --defaults-extra-file=%s --single-transaction --routines --triggers %s > %s',
            escapeshellcmd($mysqldump),
            escapeshellarg($optionFile),
            escapeshellarg($db['database'] ?? ''),
            escapeshellarg($sqlFile)
        );

        $result = Process::timeout(600)->run($command);
        @unlink($optionFile);

        if ($result->failed() || !file_exists($sqlFile) || filesize($sqlFile) === 0) {
            @unlink($sqlFile);
            $message = 'Database backup failed: ' . $result->errorOutput();
            $this->error($message);
            Log::error('sme:backup — database dump failed', [
                'output'    => $result->errorOutput(),
                'exit_code' => $result->exitCode(),
            ]);
            return null;
        }

        $gzResult = Process::timeout(300)->run('gzip -f ' . escapeshellarg($sqlFile));
        if ($gzResult->failed()) {
            Log::warning('sme:backup — gzip failed, keeping uncompressed dump', [
                'output' => $gzResult->errorOutput(),
            ]);
            $this->info('Database backed up (uncompressed): ' . basename($sqlFile) . ' (' . $this->humanSize(filesize($sqlFile)) . ')');
            return $sqlFile;
        }

        $this->info('Database backed up: ' . basename($gzFile) . ' (' . $this->humanSize(filesize($gzFile)) . ')');
        return $gzFile;
    }

    /**
     * Zips storage/app/public — every tenant's logos, product images,
     * receipt/attachment uploads. Uses PHP's built-in ZipArchive, not an
     * external tool, since it's bundled with PHP and needs no extra
     * dependency or server configuration.
     */
    private function backupUploadedFiles(string $backupDir, string $timestamp): ?string
    {
        // Two places hold uploads: storage/app/public (logos, product images)
        // and storage/app/private/attachments (documents attached to invoices,
        // expenses and other records). Only the first used to be backed up, so
        // every attached document was missing from every backup. Import files
        // (storage/app/private/imports) are temporary and deliberately skipped.
        $sources = [
            'public'              => storage_path('app/public'),
            'private/attachments' => storage_path('app/private/attachments'),
        ];
        $sources = array_filter($sources, fn ($dir) => is_dir($dir) && count(glob("{$dir}/*")) > 0);

        if (! $sources) {
            $this->info('No uploaded files to back up (no public files and no attachments).');
            return null;
        }

        $zipFile = "{$backupDir}/files_{$timestamp}.zip";
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error('Could not create files archive.');
            Log::error('sme:backup — failed to open ZipArchive', ['file' => $zipFile]);
            return null;
        }

        foreach ($sources as $prefix => $sourceDir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isDir()) continue;
                // Zip entry names should always use forward slashes regardless
                // of the OS running this command — PHP's path functions return
                // native separators, which on Windows would otherwise leak
                // backslashes into the archive.
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($sourceDir) + 1));
                $zip->addFile($file->getPathname(), $prefix . '/' . $relative);
            }
        }
        $zip->close();

        $this->info('Files backed up: ' . basename($zipFile) . ' (' . $this->humanSize(filesize($zipFile)) . ')');
        return $zipFile;
    }

    /**
     * Emails both files off-site when a recipient is configured and the
     * combined size is realistically deliverable. Never lets an email
     * failure undo the on-server backup that already succeeded above —
     * this is a best-effort extra copy, not the backup itself.
     */
    private function emailOffSite(string $dbFile, ?string $filesFile): void
    {
        $to = config('backup.notify_email');
        if (!$to) {
            $this->warn('BACKUP_NOTIFY_EMAIL is not set — skipping off-site email. On-server backup is still saved.');
            return;
        }

        $attachments = array_filter([$dbFile, $filesFile]);
        $totalSize = array_sum(array_map('filesize', $attachments));

        if ($totalSize > self::MAX_EMAIL_BYTES) {
            $this->warn(
                'Backup is too large to email (' . $this->humanSize($totalSize) . ') — '
                . 'on-server copy is saved; retrieve it via SSH/FTP from storage/app/backups.'
            );
            Log::warning('sme:backup — skipped email, too large', ['size' => $totalSize]);
            return;
        }

        try {
            Mail::to($to)->send(new BackupCompleted($attachments, now()));
            $this->info("Backup emailed to {$to}.");
        } catch (\Throwable $e) {
            $this->warn('Failed to email backup (on-server copy is still saved): ' . $e->getMessage());
            Log::error('sme:backup — email failed', ['error' => $e->getMessage()]);
        }
    }

    private function cleanupOldBackups(string $backupDir): void
    {
        $cutoff = now()->subDays(self::RETENTION_DAYS)->timestamp;
        foreach (glob("{$backupDir}/*") as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                unlink($file);
            }
        }
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }
}
