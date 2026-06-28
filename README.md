# Student Attendance

A small Laravel 12 app for the Dhamma and Sinhala School of Canberra. Teachers
log in with a one-time email link, mark which students are present for a chosen
subject + class, and record book distribution. Daily and full-year reports roll
the data up per subject and class, and paid students enrol automatically from
the sibling student-registration app.

## Use cases

> When you add a feature, add a bullet here in the same PR.

- **Passwordless teacher login.** A teacher enters their email and receives a
  one-time magic link; clicking it starts a session that expires after
  `TOKEN_EXPIRY_HOURS` (default 4). Requesting it again re-sends the *same* link
  (so an earlier email still works), and the mail goes out after the response so
  the page returns immediately.
- **Mark attendance.** Pick a subject and class, then tap each present student;
  a sticky bar shows a live present / total count, with a name search and
  select-all / clear for phones. The page shows who was already marked present
  today and toggling re-saves.
- **Record book distribution.** Same select-and-tap flow (with the same count,
  search, and shortcuts), tracked once per year per subject + class.
- **Back-fill attendance.** An editable, searchable grid for a subject + class:
  tick the dates each student attended and Save in one go — for sessions that
  were missed.
- **Reports.** *Today's Report* gives per-subject, per-class counts of students
  present today, drilling down to the named list; *Full Year Report* is a
  searchable, sortable grid of every student against every date the subject met,
  with per-student totals.
- **Auto-enrolment from registration.** Paid students and their class
  allocations sync automatically from the sibling student-registration app
  (cron or a Sync now button); a Registration Sync page shows when it last ran.
  Students who are no longer paid (e.g. a reverted payment) arrive in the
  feed's `removed` list and are **unenrolled** here — taken off the class
  rosters while their student record and attendance history are kept.
  See [docs/integration.md](docs/integration.md).

Only people seeded into the `users` table can request a login link. Students
appear on a form only if they are enrolled in that `(subject, class)`.

## Stack

- Laravel 12, PHP 8.2, MySQL (production). Local dev uses a sqlite file by
  default; tests and Dusk run against MySQL for prod parity.
- Bootstrap 5 + a small Vite-bundled JS module (`resources/js/studentSelector.js`)
  for the tap-to-toggle behaviour; the Edit Attendance and Full Year Report
  grids use DataTables (search / sort / paginate), loaded from a CDN.

## Getting started (Docker dev stack)

There is no PHP/Composer on the host — all PHP tooling runs in the `app`
container. Node runs on the host. Run `make` to list every target.

```bash
make build         # build the images (once)
make install       # composer install (in container) + npm install (host)
make db-setup      # create + migrate attendance_test and attendance_dusk
make up            # start app + db + nginx
```

The app is then served at **http://localhost:8089**.

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
- [docs/integration.md](docs/integration.md) — auto-syncing paid students and
  their class allocations from the student-registration app.
- [docs/rollout-runbook.md](docs/rollout-runbook.md) — copy-paste production
  rollout steps for switching the integration on (incl. student-number
  reconciliation).
- [docs/setup-history.md](docs/setup-history.md) — original scaffolding log and
  scratch queries.
