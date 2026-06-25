# Student Attendance

A small Laravel 12 app for the Dhamma and Sinhala School of Canberra. Teachers
log in with a one-time email link, mark which students are present for a chosen
subject + class, and record book distribution. An attendance summary rolls the
day up per subject and class.

## Use cases

> When you add a feature, add a bullet here in the same PR.

- **Passwordless teacher login.** A teacher enters their email and receives a
  one-time magic link; clicking it starts a session that expires after
  `TOKEN_EXPIRY_HOURS` (default 4).
- **Mark attendance.** Pick a subject and class, then tap each present student;
  the page shows who was already marked present today and toggling re-saves.
- **Record book distribution.** Same select-and-tap flow, tracked once per year
  per subject + class.
- **Attendance summary.** Per-subject, per-class counts of students present
  today, drilling down to the named list.

Only people seeded into the `users` table can request a login link. Students
appear on a form only if they are enrolled in that `(subject, class)`.

## Stack

- Laravel 12, PHP 8.2, MySQL (production). Local dev uses a sqlite file by
  default; tests and Dusk run against MySQL for prod parity.
- Bootstrap 5 + a small Vite-bundled JS module (`resources/js/studentSelector.js`)
  for the tap-to-toggle behaviour.

## Getting started (Docker dev stack)

There is no PHP/Composer on the host — all PHP tooling runs in the `app`
container. Node runs on the host. Run `make` to list every target.

```bash
make build         # build the images (once)
make install       # composer install (in container) + npm install (host)
make db-setup      # create + migrate attendance_test and attendance_dusk
make up            # start app + db + nginx
```

Seed some demo data to click around:

```bash
make artisan ARGS="db:seed --class=DemoDataSeeder"
```

That loads a couple of subjects, a few classes each, students, and today's
attendance — idempotent, so it's safe to re-run. See
[docs/operations.md](docs/operations.md#seed-demo--sample-data) for details, or
load the real roster from `production_seeding.sql`.

## Testing

```bash
make test          # PHPUnit Unit + Feature (against attendance_test)
make js-test       # Vitest (resources/js modules)
make test-dusk     # Dusk browser tests (against attendance_dusk + Selenium)
make lint          # Laravel Pint (style check); make lint-fix to apply
make coverage      # PHPUnit HTML coverage; make js-coverage for JS
```

CI (`.github/workflows/laravel.yml`) runs PHPUnit, Pint, and Vitest on every
push / PR to `main`.

> **Test-database safety.** `phpunit.xml` pins `DB_DATABASE=attendance_test`
> with `force="true"`, and `tests/TestCase.php` refuses to run migrations
> against any database that isn't an allow-listed test DB. This stops the suite
> from ever wiping the dev or production database. See
> [docs/security.md](docs/security.md).

## Documentation

- [docs/deployment.md](docs/deployment.md) — shared-server deploy checklist
  (production is **not** Docker).
- [docs/operations.md](docs/operations.md) — admin SQL/PHP (create a teacher,
  enrollments, reports).
- [docs/security.md](docs/security.md) — security review, fixes, and follow-ups.
- [docs/setup-history.md](docs/setup-history.md) — original scaffolding log and
  scratch queries.
