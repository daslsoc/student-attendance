# Security Notes

Summary of the security review of the attendance app and the hardening applied.
Scope: the web app code (controllers, models, routes, middleware), not
infrastructure/hosting.

## What was checked and is OK

- **SQL injection** — none found. Every database access goes through Eloquent
  or the query builder with bound parameters. The one raw expression
  (`DashboardController::summary`'s
  `COUNT(DISTINCT subject_id, class_id, student_number)`) is a constant string
  with no user input interpolated; the surrounding `where`/`groupBy` use bound
  columns. No `whereRaw`, `DB::select`, or string-concatenated queries from
  request data anywhere in `app/`.
- **CSRF** — all state-changing forms post through the `web` middleware group
  and include `@csrf` (login, attendance submit, book-distribution submit).
- **Mass assignment** — every model declares an explicit `$fillable`. Controller
  writes build the attribute array from individually-read request fields, never
  `$request->all()`, so user input can't reach `login_token` /
  `login_token_expires_at`.
- **Authorization** — every attendance / book-distribution / summary route is
  behind the `EnsureTeacherAuthenticated` middleware, which checks the session
  flag and the token-expiry timestamp. Only people seeded into `users` can
  obtain a session (via the magic-link flow).

## Fixes applied in this change

- **Dev/production database wipe (critical).** `phpunit.xml` used `<env>` tags,
  which PHPUnit 11 writes only to `$_ENV`; Laravel reads `$_SERVER` first, so a
  test DB override would be ignored and `RefreshDatabase` could `migrate:fresh`
  the dev database (the sqlite file) — or a real one if a stale
  `bootstrap/cache/config.php` were present. Fixes:
  - `phpunit.xml` now uses `<server ... force="true">` and pins
    `DB_DATABASE=attendance_test`.
  - `tests/TestCase.php` adds a code guard that aborts the run unless the
    connected database is `attendance_test` / `attendance_dusk` — this holds
    even if a cached config is present.
- **Security response headers.** New `App\Http\Middleware\SecurityHeaders`
  (appended to the `web` group) sets `X-Content-Type-Options: nosniff`,
  `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`,
  `X-Permitted-Cross-Domain-Policies: none`, and HSTS over HTTPS. Covered by
  `tests/Feature/SecurityHeadersTest.php`.
- **Login-link throttling.** `POST /login` is now `throttle:5,1` so the endpoint
  can't be used to spam a teacher's inbox or to probe which emails are
  registered.
- **PII trimmed from logs.** `AuthController` previously logged the teacher's
  name on every link request and login; it now logs the user id only.
- **Production env guidance.** `.env.example` documents `APP_DEBUG=false`,
  `SESSION_SECURE_COOKIE=true`, and the `custom.*` vars for production.

## Recommended follow-ups (not changed here)

Deliberately left out of this change to avoid altering behaviour without owner
sign-off:

1. **Make the magic-link token single-use.** `loginUsingToken` does not clear
   `login_token` after a successful login, so the same link works repeatedly
   until it expires (up to `TOKEN_EXPIRY_HOURS`). Nulling the token on use would
   make each link one-shot. (Owner chose to document rather than change this for
   now — it makes links single-use, a behaviour change for anyone re-clicking an
   email.)
2. **Keep student PII out of the application log.** Every model's `booted()`
   hook logs the full row (`$model->toArray()`) on create/update/delete, so
   student first/last names land in `storage/logs/laravel.log` on every
   attendance submit. Consider logging identifiers only, or routing this audit
   trail to a dedicated, access-controlled channel.
3. **Move validation into FormRequest classes** so rules live next to
   authorization and can be reused.
4. **Add a Content-Security-Policy.** The layout loads Bootstrap/jQuery from
   jsDelivr/code.jquery.com, so a CSP needs an allow-list, e.g.
   `script-src 'self' https://cdn.jsdelivr.net https://code.jquery.com`. Start
   in `Content-Security-Policy-Report-Only` mode.
5. **Verify `APP_DEBUG=false` and `SESSION_SECURE_COOKIE=true`** in the live
   `.env` (the example documents this; confirm the deployed value).
