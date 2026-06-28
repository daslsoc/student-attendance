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

## 4. ⚠️ Reconcile drifted student numbers (MUST do before any sync)

The whole sync keys on `student_number`. The two systems assigned numbers
independently and a handful have **drifted**, which would otherwise cause
duplicate students and identity overwrites. **Resolve these before step 7.**

### 4a. Detect the conflicts

Dump name+number from each database and compare (run on the host):

```bash
mysql -N REG_DB -e "SELECT student_number, CONCAT(first_name,' ',last_name) \
  FROM children WHERE student_number IS NOT NULL ORDER BY student_number" > reg_nums.tsv
mysql -N ATT_DB -e "SELECT student_number, CONCAT(first_name,' ',last_name) \
  FROM students WHERE student_number IS NOT NULL ORDER BY student_number" > att_nums.tsv

# COLLISIONS: same number, different child
awk -F'\t' 'NR==FNR{r[$1]=tolower($2);next}{a=tolower($2)} ($1 in r)&&r[$1]!=a{print "COLLISION #"$1": ATT="$2" / REG(num)="r[$1]}' reg_nums.tsv att_nums.tsv

# DUPLICATES: same child (name), different number
awk -F'\t' 'NR==FNR{n[tolower($2)]=$1;next}{a=tolower($2)} (a in n)&&n[a]!=$1{print "DUP "$2": ATT#"$1" vs REG#"n[a]}' reg_nums.tsv att_nums.tsv
```

### 4b. Known conflicts in the current data

If the data hasn't changed since the last export, these are the exact cases:

| Child | Attendance # | Registration # | Type |
|---|---|---|---|
| Malitha Munasinghe | 74 | 75 | drift; #74 also collides (REG#74 = Bhanuka Matara Liyanage) |
| Jinuli Senanayake | 84 | 86 | drift |
| Kimaya Weerakoon | 102 | 104 | drift |
| Senaree Piyasenage | 228 | 230 | drift |
| Thehas Waduge | 231 | 21 | drift; #231 also collides (REG#231 = Methmika Devja Aponsu) |

### 4c. Fix

**Decision: attendance numbers are canonical** (they carry historical attendance
records). Make registration match attendance for each drifted child, e.g.:

```sql
-- REG_DB — set registration's number to attendance's, per child, BY IDENTITY.
-- Verify each child's id first; do NOT blindly trust numbers.
UPDATE children SET student_number = '102' WHERE first_name='Kimaya'  AND last_name='Weerakoon';
UPDATE children SET student_number = '228' WHERE first_name='Senaree' AND last_name='Piyasenage';
UPDATE children SET student_number = '84'  WHERE first_name='Jinuli'  AND last_name='Senanayake';
-- The two that collide need the colliding child renumbered FIRST to free the
-- target number, or you'll create a duplicate number inside registration:
--   Thehas needs #231, but REG#231 is Methmika  -> give Methmika a new free number first.
--   Malitha needs #74,  but REG#74  is Bhanuka   -> give Bhanuka a new free number first.
```

Re-run the **4a** detection until both lists are empty. Only then proceed.

> If you'd rather not hand-reconcile, the safe alternative is to leave the
> drifted/colliding children **unpaid-or-unallocated** so they're excluded from
> the sync, and enrol them manually — but aligning the numbers once is cleaner.

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
