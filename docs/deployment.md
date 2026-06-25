# Deployment (shared server)

Production runs on a **shared server, not Docker**. Deploys happen by uploading
the project files on top of the existing install. That changes the risks: there
are **no long-running processes**, and **uploaded files are live immediately**.
Work through this list in order.

> Docker (`docker-compose.yml`, the `Makefile`) is **dev-only**. None of it
> runs in production.

## Before the first deploy

1. **PHP version.** Confirm the host runs **PHP >= 8.2** (Laravel 12 requires
   it). If it's older, you cannot deploy — get it upgraded first.
2. **Document root = `public/`.** Point the domain/subdomain docroot at the
   project's `public/` directory. If the server serves the project root
   instead, your `.env` and source become web-readable. (On cPanel-style hosts
   without a configurable docroot, use the standard "move public to web root +
   adjust paths" approach.)
3. **Production `.env` (lives on the server — never overwrite it on deploy):**
   - `APP_ENV=production`, `APP_DEBUG=false`
   - a real `APP_KEY` (`php artisan key:generate` once)
   - `APP_URL=https://your-domain` and `SESSION_SECURE_COOKIE=true` (HTTPS on)
   - real MySQL creds + DB name (prod is MySQL, not the dev sqlite file)
   - `MAIL_*` for the real mailer (the login-link email must actually send)
   - `custom.*` vars — `TOKEN_EXPIRY_HOURS`, `MANAGEMENT_TEAM_NAME`
     (see `config/custom.php`)
   - `QUEUE_CONNECTION` — see step 6.

## What to upload (and what NOT to)

4. **Never upload `bootstrap/cache/*.php`.** A stale `config.php` overrides
   `.env` entirely and can pin the wrong (even production) database — this is
   the single most dangerous file to ship. Exclude `bootstrap/cache/` from the
   upload, or delete those files on the server after uploading.
5. **Build and upload front-end assets.** `public/build/` is gitignored and the
   server has no Node. The layout loads the JS bundle with `@vite`, so without
   the build every page throws "Unable to locate file in Vite manifest". Build
   locally and upload the result:
   ```bash
   npm ci && npm run build      # produces public/build/ (manifest + assets)
   ```
   Also upload `vendor/` (run `composer install --no-dev --optimize-autoloader`
   locally) unless the host has Composer + SSH.

## Email / queue (important — easy to miss)

6. **The login-link email must send synchronously.** `LoginLinkMail` is sent
   inline (it is *not* queued), so as long as `MAIL_*` is configured it sends
   during the request. If you ever switch a mailable to `ShouldQueue`, shared
   hosting has no worker daemon, so either set `QUEUE_CONNECTION=sync` or add a
   cron job:
   ```
   * * * * * cd /path/to/app && php artisan queue:work --stop-when-empty >> /dev/null 2>&1
   ```
   Otherwise teachers would request a link and never receive it.

## Every deploy

7. **Run after uploading files:**
   ```bash
   php artisan optimize:clear     # drop any stale compiled caches FIRST
   php artisan migrate --force    # apply new migrations to the prod MySQL DB
   php artisan config:cache       # optional: rebuild caches from the prod .env
   php artisan route:cache
   ```
   If you skip `optimize:clear`, a stale cache can silently override `.env`.
8. **Fix writable paths.** Uploading can reset permissions. The web user must
   be able to write:
   ```bash
   chmod -R 775 storage bootstrap/cache
   ```
   (and ensure correct group ownership for the web server user).

## After deploy — smoke test

- Load `/login` over **HTTPS** (no 500s — a 500 here usually means the Vite
  build wasn't uploaded).
- Request a login link for a known teacher and confirm the email arrives.
- Click the link and confirm it lands on the attendance selection page.
- Mark a student present, submit, and confirm it shows in the summary.
- Check the log isn't filling with errors: `storage/logs/laravel.log`.

## Seeding students

The class roster lives in `production_seeding.sql` (gitignored — it contains
real student names). Load it into the prod DB once after the first migrate:
```bash
mysql -u <user> -p <database> < production_seeding.sql
```
See [operations.md](operations.md) for enrollment and reporting queries.

## Backups

Take a database backup before running any destructive query in
[operations.md](operations.md), and keep a regular automated backup of the prod
DB.

## Related

- [operations.md](operations.md) — admin SQL/PHP snippets (create a teacher,
  reports, enrollments).
- [security.md](security.md) — security review, fixes, and follow-ups.
