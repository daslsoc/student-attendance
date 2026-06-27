<?php

namespace App\Http\Controllers;

use App\Exceptions\RegistrationApiException;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Subject;
use App\Services\RegistrationClient;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Imports paid students from the student-registration app. Lists those who have
 * paid but aren't here yet, and lets a teacher pick a class per subject for each
 * (pre-filled from registration when its class name matches a local one) before
 * enrolling — creating the students row and the chosen enrollments.
 */
class RegistrationImportController extends Controller
{
    /**
     * The subjects we can enrol into, mapped to the registration field that
     * carries each child's class for that subject. Only subjects that actually
     * exist locally are returned; missing ones are reported separately.
     *
     * @return array{columns: array<int, array{id:int, name:string, field:string}>, missing: array<int, string>}
     */
    private function subjectColumns(): array
    {
        $configured = [
            ['name' => config('integration.subject_for_dhamma'), 'field' => 'dhamma_class'],
            ['name' => config('integration.subject_for_sinhala'), 'field' => 'sinhala_class'],
        ];

        $columns = [];
        $missing = [];
        foreach ($configured as $cfg) {
            $subject = Subject::where('name', $cfg['name'])->first();
            if ($subject) {
                $columns[] = ['id' => $subject->id, 'name' => $subject->name, 'field' => $cfg['field']];
            } else {
                $missing[] = $cfg['name'];
            }
        }

        return ['columns' => $columns, 'missing' => $missing];
    }

    /**
     * Whole-years age from a Y-m-d date of birth, or null if missing/unparseable.
     */
    private function ageFrom(?string $dateOfBirth): ?int
    {
        if (! $dateOfBirth) {
            return null;
        }

        try {
            return Carbon::parse($dateOfBirth)->age;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * A short "day school" label from the registration fields, e.g.
     * "Lyneham PS · Grade 3". day_school_year is free text in registration
     * (e.g. "Pre School", "Grade 8", "3"), so it's shown verbatim, not prefixed.
     */
    private function daySchool(array $child): string
    {
        $name = trim((string) ($child['day_school_name'] ?? ''));
        $year = trim((string) ($child['day_school_year'] ?? ''));

        return implode(' · ', array_filter([$name, $year], fn ($part) => $part !== ''));
    }

    public function index(RegistrationClient $client)
    {
        $students = [];
        $error = null;

        $classes = ClassModel::orderBy('name')->get(['id', 'name']);
        ['columns' => $subjectColumns, 'missing' => $missingSubjects] = $this->subjectColumns();
        $noClass = config('integration.no_class_value');

        try {
            $paid = $client->paidStudents();

            // "Not enrolled here" = has no enrollment rows yet. A bare students
            // row with no enrollments still counts as not properly enrolled, so
            // it stays on this list until it has a class.
            $enrolled = Enrollment::distinct()
                ->pluck('student_number')
                ->map(fn ($n) => (string) $n)
                ->all();

            foreach ($paid as $child) {
                $number = (string) ($child['student_number'] ?? '');
                if ($number === '' || in_array($number, $enrolled, true)) {
                    continue;
                }

                // Pre-select the local class whose name matches what registration
                // recorded (unless it's the "did not attend" sentinel).
                $defaults = [];
                foreach ($subjectColumns as $col) {
                    $value = $child[$col['field']] ?? null;
                    $defaults[$col['id']] = ($value && $value !== $noClass)
                        ? optional($classes->firstWhere('name', $value))->id
                        : null;
                }

                $students[] = [
                    'student_number' => $number,
                    'name' => trim(($child['first_name'] ?? '').' '.($child['last_name'] ?? '')),
                    'age' => $this->ageFrom($child['date_of_birth'] ?? null),
                    'day_school' => $this->daySchool($child),
                    'registration' => [
                        'dhamma_class' => $child['dhamma_class'] ?? '',
                        'sinhala_class' => $child['sinhala_class'] ?? '',
                    ],
                    'defaults' => $defaults,
                ];
            }
        } catch (RegistrationApiException $e) {
            $error = $e->getMessage();
        }

        return view('integration.index', compact(
            'students', 'error', 'classes', 'subjectColumns', 'missingSubjects'
        ));
    }

    public function enroll(Request $request, RegistrationClient $client)
    {
        $validated = $request->validate([
            'class_for' => ['array'],
            'class_for.*' => ['array'],
            'class_for.*.*' => ['nullable', 'integer', 'exists:classes,id'],
        ]);

        try {
            // Re-fetch the authoritative paid list — never trust the browser for
            // who has paid or for their names; only the class choice is the
            // teacher's input.
            $paid = collect($client->paidStudents())
                ->keyBy(fn ($child) => (string) ($child['student_number'] ?? ''));
        } catch (RegistrationApiException $e) {
            return redirect()->route('integration.index')->withErrors($e->getMessage());
        }

        // Only the configured subjects that exist locally may be enrolled into.
        $allowedSubjectIds = Subject::whereIn('name', [
            config('integration.subject_for_dhamma'),
            config('integration.subject_for_sinhala'),
        ])->pluck('id')->map(fn ($id) => (int) $id)->all();

        $enrolled = 0;

        foreach (($validated['class_for'] ?? []) as $number => $selections) {
            $child = $paid->get((string) $number);
            if (! $child) {
                continue; // not a paid student — ignore
            }

            $chosen = collect($selections)
                ->filter(fn ($classId) => ! empty($classId))
                ->filter(fn ($classId, $subjectId) => in_array((int) $subjectId, $allowedSubjectIds, true));

            if ($chosen->isEmpty()) {
                continue; // no class picked — leave them in the list
            }

            DB::transaction(function () use ($child, $chosen, &$enrolled) {
                $student = Student::firstOrCreate(
                    ['student_number' => (string) $child['student_number']],
                    [
                        'first_name' => $child['first_name'] ?? '',
                        'last_name' => $child['last_name'] ?? '',
                    ],
                );

                foreach ($chosen as $subjectId => $classId) {
                    Enrollment::firstOrCreate([
                        'student_number' => $student->student_number,
                        'subject_id' => (int) $subjectId,
                        'class_id' => (int) $classId,
                    ]);
                }

                $enrolled++;
            });
        }

        return redirect()->route('integration.index')
            ->with('message', $enrolled.' student'.($enrolled === 1 ? '' : 's').' enrolled.');
    }
}
