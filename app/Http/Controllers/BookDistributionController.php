<?php

namespace App\Http\Controllers;

use App\Models\BookDistribution;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BookDistributionController extends Controller
{
    public function index()
    {
        $subjects = Subject::all();
        $classes = ClassModel::all();

        return view('book_distribution.selection', compact('subjects', 'classes'));
    }

    public function showForm(Request $request)
    {
        $subjectId = $request->query('subject_id');
        $classId = $request->query('class_id');

        if (! $subjectId || ! $classId) {
            return redirect()->route('book_distribution.selection')->withErrors('Subject and class are required.');
        }

        // Retrieve enrollments for the selected subject and class.
        $enrollments = Enrollment::where('subject_id', $subjectId)
            ->where('class_id', $classId)
            ->get();
        $studentNumbers = $enrollments->pluck('student_number')->toArray();

        // Retrieve only the students enrolled in this subject and class, sorted by first name.
        $students = Student::whereIn('student_number', $studentNumbers)
            ->orderBy('first_name')
            ->get();

        // Retrieve existing book distribution records for the current year.
        $currentYear = now()->year;
        $givenBooks = BookDistribution::where('subject_id', $subjectId)
            ->where('class_id', $classId)
            ->whereYear('created_at', $currentYear)
            ->pluck('student_number')
            ->toArray();

        return view('book_distribution.form', compact('students', 'subjectId', 'classId', 'givenBooks'));
    }

    public function submit(Request $request)
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'class_id' => 'required|exists:classes,id',
            'present_students' => 'required',
        ]);

        $presentStudents = json_decode($request->present_students, true);
        $teacherId = session('teacher_id');
        $teacherName = session('teacher_name');
        $subjectId = $request->subject_id;
        $classId = $request->class_id;
        $currentYear = now()->year;

        // Retrieve current book distributions for this teacher, subject, and class for the current year.
        $currentBooks = BookDistribution::where('subject_id', $subjectId)
            ->where('class_id', $classId)
            ->where('teacher_id', $teacherId)
            ->whereYear('created_at', $currentYear)
            ->pluck('student_number')
            ->toArray();

        $toAdd = array_diff($presentStudents, $currentBooks);
        $toRemove = array_diff($currentBooks, $presentStudents);

        if (! empty($toRemove)) {
            Log::info("$teacherName unselected the following students as previously having books:", $toRemove);

            BookDistribution::where('subject_id', $subjectId)
                ->where('class_id', $classId)
                ->where('teacher_id', $teacherId)
                ->whereYear('created_at', $currentYear)
                ->whereIn('student_number', $toRemove)
                ->delete();
        }

        foreach ($toAdd as $studentNumber) {
            BookDistribution::create([
                'subject_id' => $subjectId,
                'class_id' => $classId,
                'student_number' => $studentNumber,
                'teacher_id' => $teacherId,
            ]);
        }

        Log::info(session('teacher_name').' submitted an update to the book distribution.');

        return redirect()->route('book_distribution.selection')->with('message', 'Book distribution updated successfully.');
    }
}
