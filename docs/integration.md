# Registration integration

The attendance app can pull **paid students** from the sibling
[student-registration](../../student-registration) app and enrol them here in
one click, instead of retyping names and enrollments by hand.

- **Provider:** student-registration exposes a read-only API of children whose
  family has paid.
- **Consumer:** this app reads that list, shows who has paid but isn't enrolled
  here yet, and enrols a selection — creating the `students` row and the mapped
  `enrollments`.

## The flow

1. A teacher opens **Enrol from Registration** in the nav.
2. The app calls the registration API, gets the paid children, and hides any
   whose `student_number` is already in the local `students` table.
3. Each remaining child gets a **class dropdown per subject** (Buddhism, Sinhala).
   Each dropdown is **pre-filled** with the local class whose name matches what
   registration recorded for that child — or left on “Don’t enrol” when
   registration has no class (its “Did not attend last year” value) or the name
   doesn’t match a local class.
4. The teacher adjusts the dropdowns as needed and clicks **Enrol**. For each
   student, every subject with a class chosen becomes an `enrollments` row, and
   the `students` row is created. A student left on “Don’t enrol” for every
   subject is skipped and stays in the list.

This means you can enrol a paid student even when registration didn’t record a
class for them — you just pick the class here.

## The API (registration side)

```
GET /api/integration/paid-students
Authorization: Bearer <token>
```

Returns only children whose parent has a payment with a `paid_date`, and only
these fields — no parent or contact details:

```json
{ "data": [
  { "student_number": "4321", "first_name": "Amara", "last_name": "Perera",
    "date_of_birth": "2016-01-01", "day_school_name": "Demo PS", "day_school_year": "3",
    "dhamma_class": "Class 1 (A)", "sinhala_class": "Class 2 (C)" }
] }
```

Defined in `routes/api.php` → `Api\PaidStudentController`, guarded by
`VerifyApiToken`.

## Security

- **Token-gated.** `VerifyApiToken` requires `Authorization: Bearer <token>`
  matching `INTEGRATION_API_TOKEN`, compared in constant time. It **fails
  closed**: no token configured, no token sent, or a mismatch → `401`. No
  unauthenticated caller can read `student_number` or names.
- **Use HTTPS in production** so the token and PII aren't sent in clear text.
- **Read-only and minimal.** The endpoint only returns the fields above (name,
  date of birth, day school, and the two subject classes — no parent or contact
  details) and is lightly throttled (`60/min`).
- **The consumer never trusts the browser.** When enrolling, the attendance app
  re-fetches the authoritative paid list and only enrols `student_number`s that
  are actually in it, and only into the two mapped subjects. A forged form can't
  enrol an unpaid/unknown child, inject a fake name, or enrol into another
  subject; the only thing taken from the form is the chosen `class_id` (validated
  to exist).

## Class & subject mapping

The teacher always picks the class, but the dropdowns are **pre-filled** from
registration to save typing. The pre-fill maps each registration field to a
local subject, and the recorded class value to a local `ClassModel` by name:

| Registration field | Attendance subject | Config key |
|--------------------|--------------------|------------|
| `dhamma_class`     | `Buddhism`         | `integration.subject_for_dhamma` |
| `sinhala_class`    | `Sinhala`          | `integration.subject_for_sinhala` |

- `Did not attend last year` (registration's sentinel) → dropdown defaults to
  **“Don’t enrol”**; pick a class manually if you do want to enrol them.
- If the recorded class name doesn't match any local class, the dropdown also
  defaults to “Don’t enrol” — you just choose the right local class.
- If a configured **subject** doesn't exist locally, the page says so and omits
  that column (create the Subject row to enable it).

Registration's class list (the authoritative enum) is: `Class 1 (A)`,
`Class 1 (B)`, `Class 2 (C)`, `Class 3 (D)`, `Class 4 (E)`. Keeping the
attendance `classes` table's names in step with this list makes the dropdowns
pre-fill correctly, but isn't required — you can always pick manually. If your
subjects aren't named `Buddhism`/`Sinhala`, override the two config keys via
`INTEGRATION_DHAMMA_SUBJECT` / `INTEGRATION_SINHALA_SUBJECT`.

## Configuration

**Registration** (`.env`):

```
INTEGRATION_API_TOKEN=<paste a shared secret>
```

**Attendance** (`.env`):

```
REGISTRATION_API_URL=https://registration.your-domain   # HTTPS in prod
REGISTRATION_API_TOKEN=<the same shared secret>
```

Generate the token once and paste it into both:

```bash
openssl rand -hex 32
```

## Networking

- **Production (shared server).** Both apps are separate installs on the same
  host. Point `REGISTRATION_API_URL` at registration's public HTTPS URL. That's
  all — the token + HTTPS secure the link.
- **Local dev (Docker).** The two stacks run as separate Docker projects on
  separate networks. The attendance `app` container reaches registration over
  the **host's published port** (`8090`) rather than by sharing a network —
  sharing one would clash, because both projects name their PHP service `app`,
  which would make registration's nginx (`fastcgi_pass app:9000`) sometimes hit
  the attendance container. The attendance `app` service has an
  `extra_hosts: host.docker.internal:host-gateway` mapping (in
  `docker-compose.yml`) so it can resolve the host. Then:

  1. Start the registration stack: `cd ../student-registration && make up`
     (it publishes on host `8090`).
  2. Start attendance normally: `make up`.
  3. In the attendance `.env`:

     ```
     REGISTRATION_API_URL=http://host.docker.internal:8090
     REGISTRATION_API_TOKEN=secret      # must equal INTEGRATION_API_TOKEN in registration/.env
     ```

  After editing `.env`, run `docker compose exec app php artisan config:clear`.

  The feature tests don't need any of this — they stub the API with
  `Http::fake()`.

## Why it might show nothing

- **Wrong token variable name.** Registration reads `INTEGRATION_API_TOKEN`
  (not `REGISTRATION_API_TOKEN`, which is the attendance-side name). If it's
  misnamed or empty in registration's `.env`, the API fails closed and every
  call is a 401.
- **Stale config cache.** After any `.env` change run
  `php artisan config:clear` in the affected app.
- **No paid students yet.** The list only shows children whose parent has a
  payment with a `paid_date` in the registration database — an empty list with
  no error means there simply aren't any.
- **Quick check** from inside the attendance app container:
  `curl -H "Authorization: Bearer <token>" http://host.docker.internal:8090/api/integration/paid-students`
  — `401` is a token problem, `200 {"data":[…]}` means it's working.
