<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Dashboard of today's attendance, grouped into a card per subject. Each
     * card shows the subject total (present / enrolled) and, below it, every
     * class with its own present / enrolled count. Both the subject total and
     * each class are links into the present-students list.
     */
    public function summary()
    {
        $today = now()->toDateString();

        $classNames = ClassModel::pluck('name', 'id');

        // Distinct enrolled students, per subject+class and per subject.
        $enrolledByClass = Enrollment::select('subject_id', 'class_id', DB::raw('COUNT(DISTINCT student_number) as total'))
            ->groupBy('subject_id', 'class_id')
            ->get();
        $enrolledBySubject = Enrollment::select('subject_id', DB::raw('COUNT(DISTINCT student_number) as total'))
            ->groupBy('subject_id')
            ->pluck('total', 'subject_id');

        // Distinct students present today, per subject+class and per subject.
        $presentByClass = Attendance::select('subject_id', 'class_id', DB::raw('COUNT(DISTINCT student_number) as total'))
            ->whereDate('date', $today)
            ->groupBy('subject_id', 'class_id')
            ->get()
            ->keyBy(fn ($row) => $row->subject_id.'-'.$row->class_id);
        $presentBySubject = Attendance::select('subject_id', DB::raw('COUNT(DISTINCT student_number) as total'))
            ->whereDate('date', $today)
            ->groupBy('subject_id')
            ->pluck('total', 'subject_id');

        $dashboard = Subject::orderBy('name')->get()->map(function ($subject) use (
            $classNames, $enrolledByClass, $presentByClass, $enrolledBySubject, $presentBySubject
        ) {
            $classes = $enrolledByClass
                ->where('subject_id', $subject->id)
                ->map(function ($row) use ($subject, $classNames, $presentByClass) {
                    $key = $subject->id.'-'.$row->class_id;

                    return [
                        'class_id' => $row->class_id,
                        'class_name' => $classNames[$row->class_id] ?? 'Unknown',
                        'enrolled' => (int) $row->total,
                        'present' => (int) ($presentByClass->get($key)->total ?? 0),
                    ];
                })
                ->sortBy('class_name')
                ->values();

            return [
                'subject_id' => $subject->id,
                'subject_name' => $subject->name,
                'enrolled' => (int) ($enrolledBySubject[$subject->id] ?? 0),
                'present' => (int) ($presentBySubject[$subject->id] ?? 0),
                'classes' => $classes,
            ];
        });

        return view('dashboard.summary', compact('dashboard'));
    }

    /**
     * The students marked present today for a subject (and optionally a single
     * class). Reached from the dashboard: the subject badge passes subject_id
     * only; a class row passes subject_id + class_id.
     */
    public function details(Request $request)
    {
        $subjectId = $request->query('subject_id');
        $classId = $request->query('class_id');

        if (! $subjectId) {
            return redirect()->route('attendance.summary')->withErrors('Subject is required.');
        }

        $today = now()->toDateString();

        $query = Attendance::where('subject_id', $subjectId)->whereDate('date', $today);
        if ($classId) {
            $query->where('class_id', $classId);
        }

        $studentNumbers = $query->pluck('student_number')->unique()->all();
        $students = Student::whereIn('student_number', $studentNumbers)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $subject = Subject::find($subjectId);
        $class = $classId ? ClassModel::find($classId) : null;

        return view('dashboard.details', compact('students', 'subject', 'class'));
    }

    /**
     * A grid for one subject (all its classes merged): every enrolled student
     * down the side, every date the subject met this year across the top, a
     * tick in each cell they attended, and their total days attended.
     */
    public function grid(Request $request)
    {
        $subjects = Subject::orderBy('name')->get();
        $subjectId = $request->query('subject_id');
        $subject = $subjectId ? Subject::find($subjectId) : null;

        $students = collect();
        $dates = collect();
        $present = [];   // [student_number][date] => true
        $totals = [];    // [student_number] => count of distinct days

        if ($subject) {
            $year = now()->year;

            // Students enrolled in this subject across all classes.
            $studentNumbers = Enrollment::where('subject_id', $subject->id)
                ->pluck('student_number')
                ->unique()
                ->values();
            $students = Student::whereIn('student_number', $studentNumbers)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get();

            // Distinct dates the subject met this calendar year.
            $dates = Attendance::where('subject_id', $subject->id)
                ->whereYear('date', $year)
                ->select('date')
                ->distinct()
                ->orderBy('date')
                ->pluck('date')
                ->map(fn ($d) => Carbon::parse($d)->toDateString())
                ->unique()
                ->values();

            // Mark which (student, date) pairs were present.
            $rows = Attendance::where('subject_id', $subject->id)
                ->whereYear('date', $year)
                ->get(['student_number', 'date']);
            foreach ($rows as $row) {
                $present[$row->student_number][Carbon::parse($row->date)->toDateString()] = true;
            }
            foreach ($students as $student) {
                $totals[$student->student_number] = count($present[$student->student_number] ?? []);
            }
        }

        return view('dashboard.grid', compact(
            'subjects', 'subject', 'students', 'dates', 'present', 'totals'
        ));
    }

    /**
     * Editable grid scoped to one subject + class: tick the dates each
     * enrolled student attended and save in one go. Useful for back-filling
     * students who were missed for several sessions. An optional add_date
     * query param adds an empty column for a session that has no records yet.
     */
    public function editGrid(Request $request)
    {
        $subjects = Subject::orderBy('name')->get();
        $classes = ClassModel::orderBy('name')->get();

        $subjectId = $request->query('subject_id');
        $classId = $request->query('class_id');
        $subject = $subjectId ? Subject::find($subjectId) : null;
        $class = $classId ? ClassModel::find($classId) : null;

        $students = collect();
        $dates = collect();
        $present = [];   // [student_number][date] => true

        if ($subject && $class) {
            $year = now()->year;

            $studentNumbers = Enrollment::where('subject_id', $subject->id)
                ->where('class_id', $class->id)
                ->pluck('student_number')
                ->unique()
                ->values();
            $students = Student::whereIn('student_number', $studentNumbers)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get();

            $dateSet = Attendance::where('subject_id', $subject->id)
                ->where('class_id', $class->id)
                ->whereYear('date', $year)
                ->select('date')
                ->distinct()
                ->pluck('date')
                ->map(fn ($d) => Carbon::parse($d)->toDateString());

            // Optional extra column for a session not yet recorded.
            $addDate = $this->normaliseDate($request->query('add_date'), $year);
            if ($addDate) {
                $dateSet->push($addDate);
            }

            $dates = $dateSet->unique()->sort()->values();

            $rows = Attendance::where('subject_id', $subject->id)
                ->where('class_id', $class->id)
                ->whereYear('date', $year)
                ->get(['student_number', 'date']);
            foreach ($rows as $row) {
                $present[$row->student_number][Carbon::parse($row->date)->toDateString()] = true;
            }
        }

        return view('dashboard.edit', compact(
            'subjects', 'classes', 'subject', 'class', 'students', 'dates', 'present'
        ));
    }

    /**
     * Reconcile the editable grid: for each enrolled student and each date
     * column that was shown, create the attendance row if it's now ticked and
     * delete it if it's now unticked. Only the submitted (subject, class,
     * students, dates) are ever touched.
     */
    public function updateGrid(Request $request)
    {
        $validated = $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'class_id' => ['required', 'exists:classes,id'],
            'dates' => ['array'],
            'dates.*' => ['date_format:Y-m-d'],
            'present' => ['array'],
        ]);

        $subjectId = (int) $validated['subject_id'];
        $classId = (int) $validated['class_id'];
        $teacherId = $request->session()->get('teacher_id');
        $year = now()->year;

        // The columns we're allowed to touch: submitted dates within this year,
        // never in the future.
        $dates = collect($validated['dates'] ?? [])
            ->map(fn ($d) => $this->normaliseDate($d, $year))
            ->filter()
            ->unique()
            ->values();

        $present = $request->input('present', []);

        $studentNumbers = Enrollment::where('subject_id', $subjectId)
            ->where('class_id', $classId)
            ->pluck('student_number')
            ->unique();

        foreach ($studentNumbers as $studentNumber) {
            foreach ($dates as $date) {
                $shouldBePresent = isset($present[$studentNumber][$date]);
                $existing = Attendance::where('subject_id', $subjectId)
                    ->where('class_id', $classId)
                    ->where('student_number', $studentNumber)
                    ->whereDate('date', $date)
                    ->first();

                if ($shouldBePresent && ! $existing) {
                    Attendance::create([
                        'date' => $date,
                        'subject_id' => $subjectId,
                        'class_id' => $classId,
                        'student_number' => $studentNumber,
                        'teacher_id' => $teacherId,
                    ]);
                } elseif (! $shouldBePresent && $existing) {
                    $existing->delete();
                }
            }
        }

        return redirect()
            ->route('attendance.edit', ['subject_id' => $subjectId, 'class_id' => $classId])
            ->with('message', 'Attendance updated.');
    }

    /**
     * Return a Y-m-d string for a date that is parseable, in the given year,
     * and not in the future — otherwise null.
     */
    private function normaliseDate(?string $value, int $year): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            $date = Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }

        if ($date->year !== $year || $date->isAfter(now())) {
            return null;
        }

        return $date->toDateString();
    }
}
