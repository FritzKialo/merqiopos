# Backup & restore runbook

Tested 2026-09-25: the nightly database backup was downloaded, restored into a scratch
database, rolled forward with `php artisan migrate`, and read back through the app's own
models. It works. This page is what was learned.

## What is backed up, and where

| What | File | Where | Kept |
|---|---|---|---|
| Whole database (130 tables, routines, triggers) | `db_YYYY-MM-DD_HHMMSS.sql.gz` | `~/smemanagers/storage/app/backups/` | 14 days |
| Uploaded files: logos, product images (`storage/app/public`) and attached documents (`storage/app/private/attachments`) | `files_YYYY-MM-DD_HHMMSS.zip` | same folder | 14 days |

Runs every day at 22:00 UTC (01:00 EAT) and is also emailed off-site when small enough
(under about 15 MB). Import files (`storage/app/private/imports`) are temporary and skipped.

**NOT backed up — keep these somewhere safe yourself:**

* `.env` (database password, mail password, M-Pesa/Daraja keys) — deliberately excluded.
* **`APP_KEY` in `.env`. Without it the encrypted values in the restored database
  (M-Pesa security credentials, API tokens, stored secrets) cannot be read.** Copy the
  `APP_KEY=` line into a password manager today.

## Restore the database

1. Get the newest `db_*.sql.gz` (cPanel File Manager, or download it over FTP).
2. Create an empty database (utf8mb4) and a user with rights to it, or empty the existing one.
3. Decompress and import:

   ```bash
   gunzip -k db_2026-09-24_220016.sql.gz
   mysql -u USER -p DATABASE < db_2026-09-24_220016.sql
   ```

4. **If the import stops on line 1 with `Unknown command '\-'`:** the dump starts with a
   `/*M!999999\- enable the sandbox mode */` line written by a newer MariaDB. An older client
   cannot read it. Skip that one line:

   ```bash
   sed '1d' db_2026-09-24_220016.sql > dump_fixed.sql
   mysql -u USER -p DATABASE < dump_fixed.sql
   ```

5. Point the app at it (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` in `.env`), then bring it
   up to date, since the backup can be a day old:

   ```bash
   cd ~/smemanagers && php artisan migrate --force
   ```

6. Sanity checks: log in; open Sales and Customers; compare the newest sale date with
   the backup time. Sales made after the backup are lost — ask shops to re-enter them
   from their paper or M-Pesa records.

## Restore the uploaded files

```bash
cd ~/smemanagers
unzip -o files_2026-09-24_220016.zip -d storage/app/
```

The archive holds `public/...` and `private/attachments/...`, so they land in the right
folders. Then `php artisan storage:link` if the public link is missing.

## Test it again

Do a restore test every few months or after a big change: restore into a scratch database,
run `php artisan migrate`, check row counts and a few known records, then delete the scratch
database and the downloaded files (they contain real customer data).
