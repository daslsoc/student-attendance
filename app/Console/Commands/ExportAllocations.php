<?php

namespace App\Console\Commands;

use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Console\Command;

/**
 * One-off backfill helper. Emits `UPDATE children …` SQL that sets the
 * registration app's allocated classes from the CURRENT attendance enrollments
 * — so existing students (including manual exceptions the grade rule can't
 * reproduce) carry over. Run once and apply the output to the registration DB:
 *
 *   php artisan integration:export-allocations > allocations.sql
 */
class ExportAllocations extends Command
{
    protected $signature = 'integration:export-allocations';

    protected $description = 'Emit one-off SQL to set registration allocations from current enrollments.';

    public function handle(): int
    {
        $dhamma = Subject::where('name', config('integration.subject_for_dhamma'))->first();
        $sinhala = Subject::where('name', config('integration.subject_for_sinhala'))->first();

        $emitted = 0;

        foreach (Student::orderByRaw('student_number + 0')->get() as $student) {
            $dhammaClass = $this->classNameFor($student->student_number, $dhamma);
            $sinhalaClass = $this->classNameFor($student->student_number, $sinhala);

            if ($dhammaClass === null && $sinhalaClass === null) {
                continue;
            }

            $this->line(sprintf(
                'UPDATE children SET allocated_dhamma_class = %s, allocated_sinhala_class = %s WHERE student_number = %s;',
                $this->sqlValue($dhammaClass),
                $this->sqlValue($sinhalaClass),
                $this->sqlValue((string) $student->student_number),
            ));
            $emitted++;
        }

        $this->getOutput()->getErrorStyle()->writeln("-- {$emitted} UPDATE statement(s) emitted.");

        return self::SUCCESS;
    }

    private function classNameFor(string $studentNumber, ?Subject $subject): ?string
    {
        if (! $subject) {
            return null;
        }

        $enrollment = Enrollment::where('student_number', $studentNumber)
            ->where('subject_id', $subject->id)
            ->first();

        if (! $enrollment) {
            return null;
        }

        return optional(ClassModel::find($enrollment->class_id))->name;
    }

    private function sqlValue(?string $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        return "'".str_replace("'", "''", $value)."'";
    }
}
