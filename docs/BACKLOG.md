# Backlog

## Code health — deferred Sonar refactors

These stay open on the local SonarQube dashboard (http://localhost:9000,
project `student-attendance`) on purpose: they are real, but each is a
behaviour-sensitive restructure of a one-off operational command, so it waits
for a reason to touch that command rather than being split for the metric.

- `app/Console/Commands/MergeStudents.php` — `mergeOne()` has cognitive
  complexity 31 (limit 15). It walks every attendance / enrolment / archive
  table for the old student number with a dry-run branch on each; splitting it
  per table is the fix, pinned by `MergeStudentsTest`.
- `app/Console/Commands/RecoverAttendanceFromLog.php` — `handle()` has
  cognitive complexity 31. The log-line parsing and the per-record decision
  (insert / already present / skip) belong in two helpers.
