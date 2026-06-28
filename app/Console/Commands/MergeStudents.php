<?php

namespace App\Console\Commands;

use App\Models\Student;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Merge duplicate student records that exist under two student numbers, keeping
 * the canonical number (registration's) and absorbing the other.
 *
 * The attendance and registration systems assigned student numbers
 * independently, so some children ended up with two records here — one under
 * each number — with their history split across both. Registration is the
 * source of truth for the number, so for each `OLD:NEW` pair this repoints every
 * record (enrollments, attendances, archived attendances, book distributions)
 * from OLD onto NEW, drops rows that would duplicate one already on NEW, and
 * deletes the OLD student row.
 *
 *   php artisan integration:merge-students --merge=228:230 --merge=231:21 --dry-run
 *
 * Safety: it refuses to merge two records whose names don't match (that would be
 * a number *collision* between different children, not a duplicate), and runs
 * each pair in its own transaction.
 */
class MergeStudents extends Command
{
    protected $signature = 'integration:merge-students
        {--merge=* : OLD:NEW pair(s); keeps NEW (registration\'s number), absorbs and deletes OLD}
        {--dry-run : Report what would change without writing anything}';

    protected $description = 'Merge duplicate student records onto the canonical (registration) student number.';

    /** Tables that reference student_number, with the columns that make a row a duplicate. */
    private array $tables = [
        'enrollments' => ['subject_id', 'class_id'],
        'attendances' => ['subject_id', 'class_id', 'date'],
        'book_distributions' => ['subject_id', 'class_id'],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $pairs = $this->option('merge');

        if (empty($pairs)) {
            $this->error('Provide at least one --merge=OLD:NEW pair.');

            return self::FAILURE;
        }

        // Archived yearly tables (e.g. "attendances.2025") are manual backups
        // with no foreign key, so sweep them too or their rows would be orphaned.
        $archives = $this->archiveTables();

        $hadError = false;

        foreach ($pairs as $pair) {
            if (! str_contains($pair, ':')) {
                $this->error("Skipping \"{$pair}\": expected OLD:NEW.");
                $hadError = true;

                continue;
            }

            [$old, $new] = array_map('trim', explode(':', $pair, 2));

            if ($old === $new || $old === '' || $new === '') {
                $this->error("Skipping \"{$pair}\": OLD and NEW must differ and be non-empty.");
                $hadError = true;

                continue;
            }

            if (! $this->mergeOne($old, $new, $archives, $dryRun)) {
                $hadError = true;
            }
        }

        return $hadError ? self::FAILURE : self::SUCCESS;
    }

    private function mergeOne(string $old, string $new, array $archives, bool $dryRun): bool
    {
        $oldStudent = Student::find($old);
        $newStudent = Student::find($new);

        if (! $oldStudent && ! $newStudent) {
            $this->warn("#{$old} -> #{$new}: neither record exists; nothing to do.");

            return true;
        }

        if (! $oldStudent) {
            $this->info("#{$old} -> #{$new}: already merged (no #{$old}); nothing to do.");

            return true;
        }

        // Both exist: must be the same child. Different names = a number
        // collision between different people — refuse, don't corrupt identity.
        if ($newStudent && ! $this->sameName($oldStudent, $newStudent)) {
            $this->error("#{$old} -> #{$new}: names differ (\"{$oldStudent->first_name} {$oldStudent->last_name}\" vs \"{$newStudent->first_name} {$newStudent->last_name}\") — refusing. Resolve this collision by hand.");

            return false;
        }

        $label = "{$oldStudent->first_name} {$oldStudent->last_name}";

        try {
            DB::transaction(function () use ($old, $new, $oldStudent, $newStudent, $archives, $dryRun, $label) {
                // No NEW record yet: this is a plain renumber. Create NEW as a
                // copy so the repoint below has a valid target, then OLD is
                // removed at the end like any merge.
                if (! $newStudent) {
                    if ($dryRun) {
                        $this->line("  would create #{$new} ({$label}) and renumber #{$old} onto it");
                    } else {
                        Student::create([
                            'student_number' => $new,
                            'first_name' => $oldStudent->first_name,
                            'last_name' => $oldStudent->last_name,
                        ]);
                    }
                }

                $summary = [];

                foreach ($this->tables as $table => $dupKey) {
                    [$moved, $dropped] = $this->repoint($table, $dupKey, $old, $new, $dryRun);
                    $summary[] = "{$table}: +{$moved}".($dropped ? " (-{$dropped} dup)" : '');
                }

                foreach ($archives as $table) {
                    [$moved, $dropped] = $this->repoint($table, ['subject_id', 'class_id', 'date'], $old, $new, $dryRun);
                    if ($moved || $dropped) {
                        $summary[] = "{$table}: +{$moved}".($dropped ? " (-{$dropped} dup)" : '');
                    }
                }

                if (! $dryRun) {
                    Student::where('student_number', $old)->delete();
                }

                $verb = $dryRun ? 'WOULD MERGE' : 'merged';
                $this->info("{$verb} #{$old} -> #{$new} ({$label}): ".implode(', ', $summary).'; removed #'.$old);
            });
        } catch (\Throwable $e) {
            $this->error("#{$old} -> #{$new}: failed, rolled back — {$e->getMessage()}");

            return false;
        }

        return true;
    }

    /**
     * Move rows from OLD to NEW in one table. A row whose duplicate-key columns
     * already exist on NEW is dropped (it's redundant); the rest are repointed.
     *
     * @param  array<int,string>  $dupKey
     * @return array{0:int,1:int} [moved, dropped]
     */
    private function repoint(string $table, array $dupKey, string $old, string $new, bool $dryRun): array
    {
        $moved = 0;
        $dropped = 0;

        foreach ($this->q($table)->where('student_number', $old)->get() as $row) {
            $duplicate = $this->q($table)
                ->where('student_number', $new)
                ->where(collect($dupKey)->mapWithKeys(fn ($c) => [$c => $row->$c])->all())
                ->exists();

            if ($duplicate) {
                $dropped++;
                if (! $dryRun) {
                    $this->q($table)->where('id', $row->id)->delete();
                }
            } else {
                $moved++;
                if (! $dryRun) {
                    $this->q($table)->where('id', $row->id)->update(['student_number' => $new]);
                }
            }
        }

        return [$moved, $dropped];
    }

    /** A query builder for a table whose name may contain a dot (archive tables). */
    private function q(string $name)
    {
        return DB::table(str_contains($name, '.') ? DB::raw('`'.$name.'`') : $name);
    }

    private function sameName(Student $a, Student $b): bool
    {
        $norm = fn (Student $s) => strtolower(trim(preg_replace('/\s+/', ' ', "{$s->first_name} {$s->last_name}")));

        return $norm($a) === $norm($b);
    }

    /** @return array<int,string> archived attendance tables like "attendances.2025" */
    private function archiveTables(): array
    {
        $rows = DB::select(
            "SELECT TABLE_NAME AS name FROM information_schema.tables
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME REGEXP '^attendances\\\\.[0-9]{4}$'"
        );

        return array_map(fn ($r) => $r->name, $rows);
    }
}
