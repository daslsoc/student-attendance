# Registration integration (auto class allocation + sync)

Students are **enrolled automatically** from the sibling
[student-registration](../../student-registration) app. When a parent pays,
registration allocates each child to a class from their day-school year; this
app syncs those allocations and creates the students + enrollments. No manual
re-typing.

- **Registration is the source of truth** for allocations. Teachers move a child
  by editing the allocation there (admin screen); attendance follows on the next
  sync.
- This app is a **read-only consumer**: it pulls, it never writes back.

## The allocation rule

Lives in registration's `config/integration.php` (edit without code):

| `day_school_year` | Class |
|---|---|
| Pre School, Kindergarten, Grade 1 | `Class A` |
| Grade 2 | `Class B` |
| Grade 3, Grade 4 | `Class C` |
| Grade 5, Grade 6 | `Class D` |
| Grade 7–12 | `Class E` |

Each child gets the same class for **both** Buddhism and Sinhala at payment; an
admin can diverge them afterwards. So the attendance `classes` table must have
rows named **`Class A` … `Class E`** for allocations to resolve.

## The flow

```
REGISTRATION (at payment)
  • allocate each child → children.allocated_dhamma_class / _sinhala_class
  • email the parent their allocated class
  • children.updated_at bumps (the "what changed" clock)

ATTENDANCE (cron, or "Sync now" on the Registration Sync page)
  • GET /api/integration/changes?since=<last_synced_at>
  • for each child in `students`: upsert the students row, then reconcile each
    subject's enrollment to the allocated class (moving them if it changed)
  • for each student_number in `removed`: delete their enrollments (off the
    rosters); the student row + attendance history are kept
  • store the new last_synced_at
```

## The API (registration side)

```
GET /api/integration/changes?since=<ISO timestamp, optional>
Authorization: Bearer <token>

→ { "last_changed_at": "2026-06-28 10:00:00", "count": 42,
    "students": [ { "student_number": "4321", "first_name": "Amara",
      "last_name": "Perera", "allocated_dhamma_class": "Class C",
      "allocated_sinhala_class": "Class C" }, … ],
    "removed": [ "9981", "9982" ] }
```

- `since` filters to children with `updated_at >= since`, so the consumer only
  pulls deltas. Omit it for a full sync.
- `last_changed_at` is the high-water mark to store and pass back next time. It
  spans all known students, so a removal advances it too.
- `students` are the **active** (paid) roster — upsert them. `removed` are
  student numbers **no longer paid** (e.g. a reverted payment) — unenroll them.
  Deletes are idempotent, so a never-enrolled number is harmless.
- Only the fields needed to enrol are returned — **no** parent, contact, or
  date-of-birth data.

## Security

- **Token-gated, fails closed.** `VerifyApiToken` requires
  `Authorization: Bearer <token>` matching `INTEGRATION_API_TOKEN`, compared in
  constant time; no/blank/mismatched token → `401`.
- **HTTPS in production** so the token and student data aren't sent in clear.
- **Read-only and minimal**, lightly throttled (`60/min`).

## Syncing it (no daemons required)

Registration's emails and this sync both work on shared hosting without a
long-running worker:

- **Cron** (the routine path) — schedule the command every few minutes:

  ```
  */5 * * * * cd /path/to/student-attendance && php artisan registration:sync >> storage/logs/sync.log 2>&1
  ```

- **Manual** — the **Registration Sync** page (in the nav) shows the last sync
  time and a **Sync now** button (`php artisan registration:sync` under the
  hood).

The sync is idempotent and only transfers data when something changed, so
running it often is cheap.

## Moving a class — what happens to history

Reconciliation only edits **enrollment** membership; it never touches the
`attendances` table. Past attendance rows keep the class they were taken in, so
**no history is lost**. The per-subject **Full Year Report** shows a student's
full year continuously across any move; per-class screens attribute each session
to the class it happened in. A `null` allocation is left alone (it means "not
allocated", not "un-enrol").

**Removal** works the same way: a student in the `removed` list has their
**enrollments** deleted (so they drop off the class rosters), but their
`students` row and `attendances` history are kept — deleting the student would
cascade and destroy that history. If they pay again later, the next sync simply
re-enrols them.

## Configuration

**Registration** (`.env`): `INTEGRATION_API_TOKEN=<shared secret>`

**Attendance** (`.env`):

```
REGISTRATION_API_URL=https://registration.your-domain   # HTTPS in prod
REGISTRATION_API_TOKEN=<the same shared secret>
# Override only if your subjects aren't named "Buddhism" / "Sinhala":
# INTEGRATION_DHAMMA_SUBJECT=Buddhism
# INTEGRATION_SINHALA_SUBJECT=Sinhala
```

Generate the token once (`openssl rand -hex 32`) and paste into both.

## Local dev networking

The two Docker stacks run separately. The attendance `app` container reaches
registration over its **published host port** (`8090`) via a
`host.docker.internal:host-gateway` mapping (in `docker-compose.yml`) — not a
shared network (both projects name their PHP service `app`, which would clash).
So: start registration (`make up`, publishes 8090), start attendance
(`make up`), and set `REGISTRATION_API_URL=http://host.docker.internal:8090`.
Tests stub the API with `Http::fake()` and need none of this.

## One-time rollout

Do this once, in order (the export step carries over manual exceptions the rule
can't reproduce):

1. **Registration:** deploy, run `php artisan migrate` (adds the allocated
   columns), confirm prod `QUEUE_CONNECTION=sync`.
2. **Attendance:** deploy, run `php artisan migrate` (adds the sync-state table),
   and **rename the class rows to `Class A`…`Class E`** (the names the rule
   produces). For example:
   ```sql
   UPDATE classes SET name = 'Class A' WHERE name = '<old name>';
   -- …repeat for B–E
   ```
3. **Backfill registration from the real current classes** — in attendance:
   ```
   php artisan integration:export-allocations > allocations.sql
   ```
   Review `allocations.sql` (one `UPDATE children …` per student, keyed by
   `student_number`, using the actual enrolled class incl. exceptions), then
   apply it to the **registration** database.
4. **Dry-run before the first real sync** — preview exactly what the first run
   would do, writing nothing:
   ```
   php artisan registration:sync --dry-run
   ```
   It runs the full reconcile inside a transaction it rolls back, then reports
   `would enrol` / `would move` counts and any "class/subject not set up here"
   warnings. If the backfill in step 3 was complete, you should see
   **`would enrol 0, would move 0`** — that zero-change result is the proof the
   first real sync is a no-op. Investigate any unexpected moves (usually a class
   name mismatch or an exception the backfill missed) before running for real.
5. *(optional)* For any paid child the export didn't cover (no attendance
   enrollment yet), their allocation stays `NULL` until their next payment or an
   admin sets it on the registration allocations screen.
6. **Run it for real** — drop `--dry-run`, then wire the cron line above. From
   here it's automatic: each payment allocates + emails, and `registration:sync`
   (cron or the **Sync now** button) enrols here.
