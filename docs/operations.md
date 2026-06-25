# Operations

Working SQL/PHP snippets for day-to-day administration of the attendance
database.

> **Run queries through a known-safe path.** Use `make mysql DB=attendance_db`
> (dev) to open a client. The destructive queries below should only ever run
> against production after a backup.

Schema at a glance:

- `users` — teachers (login by magic link; `login_token`, `login_token_expires_at`).
- `students` — roster, primary key is the string `student_number`.
- `subjects`, `classes` — the two axes a session is selected by.
- `enrollments` — which `student_number` is in which `(subject_id, class_id)`.
- `attendances` — one row per student marked present, per `date` + subject +
  class + teacher.
- `book_distributions` — one row per student given books, per year + subject +
  class + teacher.

## Seed demo / sample data

`DemoDataSeeder` loads a couple of subjects, a few classes each, eight students
enrolled across them, and **today's** attendance for some of them — enough to
click around the forms and the summary page. It is **idempotent** (every row is
`firstOrCreate`d, so re-running never duplicates) and is **not** wired into
`DatabaseSeeder`, so a bare `db:seed` won't run it — you opt in by name.

```bash
# Docker dev stack (PHP runs in the app container):
docker compose exec app php artisan db:seed --class=DemoDataSeeder

# ...or via the Makefile shortcut if you're already using make:
make artisan ARGS="db:seed --class=DemoDataSeeder"

# Outside Docker (PHP on the host):
php artisan db:seed --class=DemoDataSeeder
```

Notes:

- It seeds whatever database the app is configured to use. In the Docker stack
  the `app` container connects to **MySQL `attendance_db`** (the `db` service) —
  the same DB the running site reads — so the data shows up on the site
  immediately. (The host `.env` points at the dev sqlite file, which is a
  *different* database; run the seeder where the app actually runs.)
- It creates a **Demo Teacher** (`demo.teacher@example.com`) to own the
  attendance rows. Log in as that teacher via the magic-link flow to see the
  data. Locally, if `MAIL_MAILER=log`, the login link is written to
  `storage/logs/laravel.log` instead of being emailed.
- The attendance is deliberately partial (e.g. 2 of 3 present) so the summary
  page looks realistic.
- Safe to re-run any time to top up a freshly migrated database.

To start completely fresh first (dev only — this **drops every table**, never
run it against production):

```bash
docker compose exec app php artisan migrate:fresh
docker compose exec app php artisan db:seed --class=DemoDataSeeder
```

## Create a teacher (so they can log in)

```php
php artisan tinker
\App\Models\User::create([
    'name' => 'Teacher Name',
    'email' => 'teacher@example.com',
    'password' => bcrypt('a-strong-password'), // unused by the magic-link flow but required by the column
]);
```

Only people in `users` can request a login link.

## Enrollments (who appears on a subject/class form)

A student only shows on the attendance / book forms if they are enrolled in
that `(subject, class)`:

```sql
INSERT INTO enrollments (student_number, subject_id, class_id, created_at, updated_at)
VALUES ('1', 2, 3, NOW(), NOW());
```

List a class roster:

```sql
SELECT s.student_number, s.first_name, s.last_name
FROM enrollments e
JOIN students s ON s.student_number = e.student_number
WHERE e.subject_id = ? AND e.class_id = ?
ORDER BY s.first_name;
```

## Attendance reports

Who was present today, for a subject + class:

```sql
SELECT s.student_number, s.first_name, s.last_name
FROM attendances a
JOIN students s ON s.student_number = a.student_number
WHERE a.subject_id = ? AND a.class_id = ? AND a.date = CURDATE()
ORDER BY s.first_name;
```

Daily counts per subject + class (this is what the summary page shows):

```sql
SELECT subject_id, class_id,
       COUNT(DISTINCT student_number) AS present
FROM attendances
WHERE date = CURDATE()
GROUP BY subject_id, class_id;
```

A student's attendance count over a term:

```sql
SELECT s.first_name, s.last_name, COUNT(*) AS days_present
FROM attendances a
JOIN students s ON s.student_number = a.student_number
WHERE a.date BETWEEN ? AND ?
GROUP BY a.student_number
ORDER BY days_present DESC;
```

## Book distribution report

Students given books this year for a subject + class:

```sql
SELECT s.student_number, s.first_name, s.last_name
FROM book_distributions b
JOIN students s ON s.student_number = b.student_number
WHERE b.subject_id = ? AND b.class_id = ? AND YEAR(b.created_at) = YEAR(CURDATE())
ORDER BY s.first_name;
```

## Maintenance

Clear any stale login tokens (optional housekeeping — tokens also expire on
their own after `TOKEN_EXPIRY_HOURS`):

```sql
UPDATE users SET login_token = NULL, login_token_expires_at = NULL;
```

## Post-deploy

```bash
php artisan optimize
```

> If you ever see tests or the app reading a stale/wrong database or config,
> clear the compiled caches: `php artisan optimize:clear` (or delete
> `bootstrap/cache/{config,routes-v7,events,packages,services}.php`). A stale
> `bootstrap/cache/config.php` overrides `.env` entirely. See
> [security.md](security.md) and the test-database guard rail in `phpunit.xml`.
