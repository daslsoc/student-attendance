<?php

namespace App\Console\Commands;

use App\Models\ClassModel;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rebuild attendance rows that were wrongly deleted, from the application log.
 *
 * Every attendance deletion is logged by Attendance::booted() as a line like:
 *
 *   [2026-07-18 14:23:01] production.INFO: Attendance deleted {"id":1234,
 *     "date":"2026-05-03","subject_id":1,"class_id":2,"student_number":"1023",
 *     "teacher_id":5}
 *
 * so a deleted row can be reconstructed exactly. This command parses those
 * lines from a laravel.log file and re-inserts the rows, skipping any that
 * already exist (idempotent — safe to run twice).
 *
 * Because the log records EVERY delete — including legitimate un-ticks — narrow
 * it to the incident:
 *   --since / --until   bound to when the bad save(s) happened.
 *   --min-cluster=N     only rebuild rows deleted as part of a burst of >= N
 *                       deletions in the same second. The Edit-grid wipe deletes
 *                       many rows at once; a normal un-tick deletes one. This is
 *                       the key filter that separates the accident from intended
 *                       removals, so prefer it over a wide time window.
 *
 * Preview first, always:
 *   php artisan attendance:recover-from-log storage/logs/laravel.log \
 *       --since="2026-07-18 14:00" --min-cluster=3 --dry-run
 *
 * then drop --dry-run to write.
 */
class RecoverAttendanceFromLog extends Command
{
    protected $signature = 'attendance:recover-from-log
        {log : Path to the laravel.log file to read}
        {--since= : Only consider deletions at/after this time (e.g. "2026-07-18" or "2026-07-18 14:00")}
        {--until= : Only consider deletions at/before this time}
        {--min-cluster=1 : Only rebuild rows deleted in a same-second burst of at least this many}
        {--dry-run : Report what would be rebuilt without writing anything}';

    protected $description = 'Rebuild wrongly-deleted attendance rows from the "Attendance deleted" lines in a log file.';

    public function handle(): int
    {
        $path = $this->argument('log');
        if (! is_readable($path)) {
            $this->error("Cannot read log file: {$path}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $minCluster = max(1, (int) $this->option('min-cluster'));
        $since = $this->option('since') ? Carbon::parse($this->option('since')) : null;
        $until = $this->option('until') ? Carbon::parse($this->option('until')) : null;

        [$entries, $perSecond, $parsed] = $this->readDeletions($path, $since, $until);
        [$candidates, $droppedSmallCluster] = $this->candidatesFrom($entries, $perSecond, $minCluster);

        $this->info(sprintf(
            'Parsed %d deleted-row log entries; %d in window; %d after the min-cluster=%d filter; %d unique rows to consider.',
            $parsed, count($entries), count($entries) - $droppedSmallCluster, $minCluster, count($candidates)
        ));

        if (empty($candidates)) {
            $this->warn('Nothing to rebuild with these filters.');

            return self::SUCCESS;
        }

        [$toInsert, $skippedExisting, $skippedMissingRef] = $this->rowsToInsert($candidates);

        $this->table(
            ['Would rebuild', 'Already present (skipped)', 'Missing student/subject/class (skipped)'],
            [[count($toInsert), $skippedExisting, $skippedMissingRef]],
        );

        if (empty($toInsert)) {
            $this->info('Every candidate row already exists — nothing to insert.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('Dry run — no rows written. Re-run without --dry-run to rebuild them.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($toInsert) {
            foreach (array_chunk($toInsert, 500) as $chunk) {
                DB::table('attendances')->insert($chunk);
            }
        });

        $this->info(sprintf('Rebuilt %d attendance row(s).', count($toInsert)));

        return self::SUCCESS;
    }

    /**
     * Pass 1: pull every "Attendance deleted" entry inside the time window,
     * tallying how many happened in each exact second (the burst detector).
     *
     * @return array{0: list<array{second: string, data: array}>, 1: array<string, int>, 2: int}
     */
    private function readDeletions(string $path, ?Carbon $since, ?Carbon $until): array
    {
        $entries = [];
        $perSecond = [];
        $parsed = 0;

        $handle = fopen($path, 'r');
        while (($line = fgets($handle)) !== false) {
            $entry = $this->parseLine($line);
            if ($entry === null) {
                continue;
            }

            $parsed++;
            if (! $this->inWindow($entry['when'], $since, $until)) {
                continue;
            }

            $second = $entry['second'];
            $perSecond[$second] = ($perSecond[$second] ?? 0) + 1;
            $entries[] = ['second' => $second, 'data' => $entry['data']];
        }
        fclose($handle);

        return [$entries, $perSecond, $parsed];
    }

    /**
     * One log line -> its deletion entry, or null when the line is not a
     * well-formed "Attendance deleted" record.
     *
     * @return array{second: string, when: Carbon, data: array}|null
     */
    private function parseLine(string $line): ?array
    {
        if (! str_contains($line, 'Attendance deleted')) {
            return null;
        }
        if (! preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*Attendance deleted (\{.*\})\s*$/', $line, $m)) {
            return null;
        }

        $data = json_decode($m[2], true);
        if (! is_array($data) || ! isset($data['date'], $data['subject_id'], $data['class_id'], $data['student_number'])) {
            return null;
        }

        return ['second' => $m[1], 'when' => Carbon::createFromFormat('Y-m-d H:i:s', $m[1]), 'data' => $data];
    }

    private function inWindow(Carbon $when, ?Carbon $since, ?Carbon $until): bool
    {
        return ! ($since && $when->lt($since)) && ! ($until && $when->gt($until));
    }

    /**
     * Pass 2: keep entries from a big-enough burst, de-duplicated to one per
     * (subject, class, student, date) — a row may have been deleted more than
     * once over the log's life; we only need to rebuild it once.
     *
     * @return array{0: array<string, array>, 1: int}
     */
    private function candidatesFrom(array $entries, array $perSecond, int $minCluster): array
    {
        $candidates = [];
        $droppedSmallCluster = 0;
        foreach ($entries as $e) {
            if ($perSecond[$e['second']] < $minCluster) {
                $droppedSmallCluster++;

                continue;
            }
            $d = $e['data'];
            $date = Carbon::parse($d['date'])->toDateString();
            $key = $d['subject_id'].'|'.$d['class_id'].'|'.$d['student_number'].'|'.$date;
            $candidates[$key] ??= [
                'date' => $date,
                'subject_id' => (int) $d['subject_id'],
                'class_id' => (int) $d['class_id'],
                'student_number' => (string) $d['student_number'],
                'teacher_id' => $d['teacher_id'] ?? null,
            ];
        }

        return [$candidates, $droppedSmallCluster];
    }

    /**
     * Candidates -> rows to insert, skipping rows that already exist and rows
     * whose student / subject / class / teacher no longer exists (teacher_id
     * is a NOT-NULL FK, so a stale one would fail the whole insert batch).
     *
     * @return array{0: list<array>, 1: int, 2: int}
     */
    private function rowsToInsert(array $candidates): array
    {
        $refs = [
            'students' => Student::pluck('student_number')->flip(),
            'subjects' => Subject::pluck('id')->flip(),
            'classes' => ClassModel::pluck('id')->flip(),
            'teachers' => User::pluck('id')->flip(),
        ];

        $toInsert = [];
        $skippedExisting = 0;
        $skippedMissingRef = 0;
        $now = now();

        foreach ($candidates as $c) {
            if (! $this->refsExist($c, $refs)) {
                $skippedMissingRef++;

                continue;
            }
            if ($this->alreadyPresent($c)) {
                $skippedExisting++;

                continue;
            }

            $toInsert[] = $c + ['created_at' => $now, 'updated_at' => $now];
        }

        return [$toInsert, $skippedExisting, $skippedMissingRef];
    }

    private function refsExist(array $c, array $refs): bool
    {
        return isset($refs['students'][$c['student_number']], $refs['subjects'][$c['subject_id']], $refs['classes'][$c['class_id']])
            && $c['teacher_id'] !== null
            && isset($refs['teachers'][$c['teacher_id']]);
    }

    private function alreadyPresent(array $c): bool
    {
        return DB::table('attendances')
            ->where('subject_id', $c['subject_id'])
            ->where('class_id', $c['class_id'])
            ->where('student_number', $c['student_number'])
            ->whereDate('date', $c['date'])
            ->exists();
    }
}
