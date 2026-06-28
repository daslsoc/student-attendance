# Production rollout runbook — registration → attendance auto-enrolment

A step-by-step, copy-paste runbook for switching on the automatic class
allocation + sync between **student-registration** (producer) and
**student-attendance** (consumer) in production.

Production is a **shared server** (no Docker, no long-running workers), so every
command here is a plain `php artisan` / `mysql` you run on the host, in each
app's directory. Adjust paths/credentials to your server.

> Conventions below: `REG_DB` = the registration database, `ATT_DB` = the
> attendance database. Run SQL with e.g. `mysql -u<user> -p <db>` (or your host's
> phpMyAdmin). **Take a full backup of both databases before you start.**

```bash
mysqldump REG_DB > reg_backup_$(date +%F).sql
mysqldump ATT_DB > att_backup_$(date +%F).sql
```

---

## 0. Pre-flight

- [ ] Backups of both databases taken (above).
- [ ] A quiet window (no parents mid-payment, ideally).
- [ ] You can run `php artisan` and `mysql` on the host for both apps.

---

## 1. Fix the shared session cookie (do this regardless)

Both apps derive their session cookie name from `APP_NAME`, which is identical,
so they share one cookie on the same domain and log each other out. Set a
**unique** cookie name per app.

In **registration** `.env`:
```
SESSION_COOKIE=registration_session
```
In **attendance** `.env`:
```
SESSION_COOKIE=attendance_session
```
Then in **each** app:
```bash
php artisan config:cache
```
Everyone is logged out once (the old shared cookie is stale); they log back in
normally.

---

## 2. Deploy code + migrate

In **registration**:
```bash
# deploy the new code, then:
php artisan migrate            # adds children.allocated_dhamma_class / _sinhala_class
php artisan config:cache
# confirm queued mail sends inline (no worker on shared hosting):
grep QUEUE_CONNECTION .env     # must be: QUEUE_CONNECTION=sync
```

In **attendance**:
```bash
# deploy the new code, then:
php artisan migrate            # adds the integration_sync_state table
npm run build                  # if the nav/asset changes need rebuilding
php artisan config:cache
```

---

## 3. Rename the attendance classes to the rule's names

The allocation rule produces `Class A`…`Class E`. Your class rows must match or
allocations won't resolve. Confirm the current names first:

```sql
SELECT id, name FROM classes ORDER BY id;     -- ATT_DB
```

Then rename (this mapping was confirmed against the current data — re-check if
your class names differ):

```sql
-- ATT_DB
UPDATE classes SET name = 'Class A' WHERE name = 'Class 1 (A)';
UPDATE classes SET name = 'Class B' WHERE name = 'Class 1 (B)';
UPDATE classes SET name = 'Class C' WHERE name = 'Class 2 (C)';
UPDATE classes SET name = 'Class D' WHERE name = 'Class 3 (D)';
UPDATE classes SET name = 'Class E' WHERE name = 'Class 4 (E)';
SELECT id, name FROM classes ORDER BY id;     -- verify A–E
```

---

## 4. ⚠️ Merge duplicate student numbers (MUST do before any sync)

The whole sync keys on `student_number`. Attendance and registration assigned
numbers independently, and a handful of children ended up **stored twice in
attendance** — once under each number — with their attendance history split
across both rows. **Registration's number is canonical.** Left as-is, the sync
would compound the problem (more duplicates). Fix it with `integration:merge-students`
before step 7.

### 4a. Find the duplicates

A child stored under two numbers shows up as the same name twice in attendance:

```bash
mysql -N ATT_DB -e "
  SELECT LOWER(CONCAT(first_name,' ',last_name)) nm, GROUP_CONCAT(student_number) nums
  FROM students GROUP BY nm HAVING COUNT(*) > 1"
```
For each, the **keeper** is the number registration uses for that child:
```bash
mysql -N REG_DB -e "SELECT student_number, first_name, last_name FROM children
  WHERE CONCAT(first_name,' ',last_name) IN ('Senaree Piyasenage', ...)"
```

### 4b. Known duplicates in the current data

If the data hasn't changed since the last export, these are the exact pairs
(`OLD attendance number` → `NEW registration number`, keep NEW):

| Child | OLD (attendance) | NEW (registration, keep) |
|---|---|---|
| Malitha Munasinghe | 74 | 75 |
| Jinuli Senanayake | 84 | 86 |
| Kimaya Weerakoon | 102 | 104 |
| Senaree Piyasenage | 228 | 230 |
| Thehas Waduge | 231 | 21 |

### 4c. Merge

Preview first, then run. The command repoints enrollments, attendances (incl.
archived `attendances.YYYY`), and book distributions from OLD onto NEW, drops
rows that would duplicate one already on NEW, and deletes the OLD record — all in
a transaction. It **refuses** any pair whose two records have different names (a
real number collision, not a duplicate), so it can't merge two different kids.

In **attendance**:
```bash
php artisan integration:merge-students \
  --merge=74:75 --merge=84:86 --merge=102:104 --merge=228:230 --merge=231:21 --dry-run

# review the per-pair "WOULD MERGE …" summary, then drop --dry-run:
php artisan integration:merge-students \
  --merge=74:75 --merge=84:86 --merge=102:104 --merge=228:230 --merge=231:21
```
Re-run **4a** until it returns nothing. After this, freeing the OLD numbers also
clears the earlier "collision" worry: e.g. registration's own `#74`
(Bhanuka Matara Liyanage) and `#231` (Methmika Devja Aponsu) now sync into the
vacated attendance numbers without clashing.

---

## 5. Backfill registration's allocations from real current enrolments

This carries over manual exceptions the rule can't reproduce (kids whose
Buddhism and Sinhala classes differ, or who were placed off-rule).

In **attendance**:
```bash
php artisan integration:export-allocations > allocations.sql
```
Review `allocations.sql` (one `UPDATE children …` per student, keyed by
`student_number`), then apply it to **registration**:
```bash
mysql REG_DB < allocations.sql
```

---

## 6. Allocate paid children who still have no class

Already-paid children who predate the integration won't have an allocation.
Allocate them from the rule (their day-school year → class). Preview first:

In **registration**:
```bash
php artisan integration:allocate-missing --dry-run    # lists what it would do
php artisan integration:allocate-missing              # writes it
```
This is idempotent and never overrides an admin's manual allocation. Children
whose year isn't in the rule are reported and left for an admin to set on the
allocations screen.

---

## 7. Dry-run the sync and verify

In **attendance**:
```bash
php artisan registration:sync --dry-run
```
Read the output:
- **`would move 0`** and **no warnings** — expected. Existing enrolments are
  already correct; nothing gets shuffled.
- **`would enrol N`** — the newly-allocated paid children (from step 6) that
  aren't enrolled yet. Expected to be > 0 on first rollout.
- Any **warning** ("class/subject not set up here") means a name mismatch —
  fix it (usually step 3) before the real run.

---

## 8. Run it for real, then schedule cron

In **attendance**:
```bash
php artisan registration:sync
```
Confirm the **Registration Sync** page shows a fresh "last synced" time and a
sensible count. Then add the cron entry (every few minutes):
```
*/5 * * * * cd /path/to/student-attendance && php artisan registration:sync >> storage/logs/sync.log 2>&1
```
From here it's automatic: each payment allocates + emails the parent, and cron
(or the **Sync now** button) enrols them here.

---

## Rollback

Nothing here is destructive to attendance history (the sync only edits
enrolment membership, never the `attendances` table). If something looks wrong:

- Stop the cron entry.
- Restore from the step-0 backups if needed:
  ```bash
  mysql REG_DB < reg_backup_YYYY-MM-DD.sql
  mysql ATT_DB < att_backup_YYYY-MM-DD.sql
  ```
- The sync is idempotent, so once the data is right, re-running converges.

## Verified in a dev rehearsal

This runbook was rehearsed against a copy of production data: after steps 3–6,
`registration:sync --dry-run` reported `would move 0` with no warnings, and the
real run enrolled exactly the newly-allocated paid children (no duplicates, no
moves) — **once the step-4 number drift was reconciled.** Skipping step 4 in the
rehearsal produced duplicate students, which is why it's a hard prerequisite.
