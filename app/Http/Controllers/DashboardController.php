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
}
