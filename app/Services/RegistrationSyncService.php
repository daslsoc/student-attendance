<?php

namespace App\Services;

use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\IntegrationSyncState;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;

/**
 * Pulls class allocations from student-registration and reconciles the local
 * students + enrollments to match. Registration is the source of truth: for
 * each changed child, the student row is upserted and each subject's enrollment
 * is moved to the allocated class (dropping any other enrollment for that
 * subject). Idempotent — running it repeatedly converges to the same state.
 */
class RegistrationSyncService
{
    public function __construct(private RegistrationClient $client) {}

    /**
     * Pull allocations and reconcile. With $dryRun the exact same work runs
     * inside a transaction that is rolled back at the end: the returned
     * enrolled/moved/warnings counts are what a real run *would* do, but nothing
     * is persisted and the sync state is left untouched.
     *
     * @return array{received:int, enrolled:int, moved:int, warnings:array<int,string>, last_synced_at: mixed, dry_run: bool}
     */
    public function sync(bool $dryRun = false): array
    {
        $state = IntegrationSyncState::current();
        $since = optional($state->last_synced_at)->toDateTimeString();

        $payload = $this->client->changes($since);

        $dhammaSubject = Subject::where('name', config('integration.subject_for_dhamma'))->first();
        $sinhalaSubject = Subject::where('name', config('integration.subject_for_sinhala'))->first();

        $enrolled = 0;
        $moved = 0;
        $warnings = [];

        // One outer transaction so a dry run can roll the whole thing back. The
        // per-student transactions below become savepoints, preserving the same
        // per-student atomicity a real run has.
        DB::beginTransaction();
        try {
            foreach ($payload['students'] as $child) {
                DB::transaction(function () use ($child, $dhammaSubject, $sinhalaSubject, &$enrolled, &$moved, &$warnings) {
                    $student = Student::updateOrCreate(
                        ['student_number' => (string) $child['student_number']],
                        [
                            'first_name' => $child['first_name'] ?? '',
                            'last_name' => $child['last_name'] ?? '',
                        ],
                    );

                    foreach ([
                        [$dhammaSubject, config('integration.subject_for_dhamma'), $child['allocated_dhamma_class'] ?? null],
                        [$sinhalaSubject, config('integration.subject_for_sinhala'), $child['allocated_sinhala_class'] ?? null],
                    ] as [$subject, $subjectName, $className]) {
                        $result = $this->reconcile($student, $subject, $subjectName, $className, $warnings);
                        $enrolled += $result['enrolled'];
                        $moved += $result['moved'];
                    }
                });
            }

            $state->update([
                'last_synced_at' => $payload['last_changed_at'] ?: $state->last_synced_at,
                'last_checked_at' => now(),
                'last_count' => $payload['count'],
            ]);

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        return [
            'received' => count($payload['students']),
            'enrolled' => $enrolled,
            'moved' => $moved,
            'warnings' => $warnings,
            'last_synced_at' => $dryRun ? $state->getOriginal('last_synced_at') : $state->fresh()->last_synced_at,
            'dry_run' => $dryRun,
        ];
    }

    /**
     * Ensure the student's enrollment for one subject is exactly the allocated
     * class, moving them if it changed. A null/blank allocation is left alone
     * (it means "not allocated", not "remove"). Missing subject/class is skipped
     * with a warning, never destructively.
     *
     * @return array{enrolled:int, moved:int}
     */
    private function reconcile(Student $student, ?Subject $subject, string $subjectName, ?string $className, array &$warnings): array
    {
        $result = ['enrolled' => 0, 'moved' => 0];

        if ($className === null || $className === '') {
            return $result;
        }

        if (! $subject) {
            $warnings[] = "Subject \"{$subjectName}\" isn't set up here — skipped student {$student->student_number}.";

            return $result;
        }

        $class = ClassModel::where('name', $className)->first();
        if (! $class) {
            $warnings[] = "Class \"{$className}\" isn't set up here — skipped student {$student->student_number} ({$subjectName}).";

            return $result;
        }

        $existing = Enrollment::where('student_number', $student->student_number)
            ->where('subject_id', $subject->id)
            ->get();

        $alreadyCorrect = $existing->firstWhere('class_id', $class->id);
        $stale = $existing->where('class_id', '!=', $class->id);

        if ($stale->isNotEmpty()) {
            Enrollment::whereIn('id', $stale->pluck('id'))->delete();
            $result['moved'] = 1;
        }

        if (! $alreadyCorrect) {
            Enrollment::create([
                'student_number' => $student->student_number,
                'subject_id' => $subject->id,
                'class_id' => $class->id,
            ]);
            if ($result['moved'] === 0) {
                $result['enrolled'] = 1;
            }
        }

        return $result;
    }
}
