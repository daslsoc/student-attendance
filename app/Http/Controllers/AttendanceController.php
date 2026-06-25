<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\ClassModel;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller
{
    public function index()
    {
        $subjects = Subject::all();
        $classes = ClassModel::all();

        return view('attendance.selection', compact('subjects', 'classes'));
    }

    public function showForm(Request $request)
    {
        $subjectId = $request->query('subject_id');
        $classId = $request->query('class_id');

        if (! $subjectId || ! $classId) {
            return redirect()->route('attendance.selection')->withErrors('Subject and class are required.');
        }

        // Retrieve enrollments for the selected subject and class.
        $enrollments = \App\Models\Enrollment::where('subject_id', $subjectId)
            ->where('class_id', $classId)
            ->get();

        // Get the list of student_numbers from enrollments.
        $studentNumbers = $enrollments->pluck('student_number')->toArray();

        // Retrieve only the students enrolled in this subject and class.
        $students = \App\Models\Student::whereIn('student_number', $studentNumbers)->orderBy('first_name')->get();

        // Retrieve existing attendance for the current day for this subject, class, and teacher.
        $today = now()->toDateString();
        $attendedStudents = \App\Models\Attendance::where('subject_id', $subjectId)
            ->where('class_id', $classId)
            ->whereDate('date', $today)
            ->pluck('student_number')
            ->toArray();

        return view('attendance.form', compact('students', 'subjectId', 'classId', 'attendedStudents'));
    }

    public function submit(Request $request)
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'class_id' => 'required|exists:classes,id',
            'present_students' => 'required',
        ]);

        $presentStudents = json_decode($request->present_students, true);
        $today = now()->toDateString();
        $teacherId = session('teacher_id');
        $teacherName = session('teacher_name');
        $subjectId = $request->subject_id;
        $classId = $request->class_id;

        // Get current attendance records for this teacher, subject, and class today.
        $currentAttendance = \App\Models\Attendance::where('subject_id', $subjectId)
            ->where('class_id', $classId)
            ->whereDate('date', $today)
            ->where('teacher_id', $teacherId)
            ->pluck('student_number')
            ->toArray();

        // Determine which attendances to add and which to remove.
        $toAdd = array_diff($presentStudents, $currentAttendance);
        $toRemove = array_diff($currentAttendance, $presentStudents);

        // Remove records that are no longer selected.
        if (! empty($toRemove)) {
            Log::info("$teacherName unselected the following students as previously attended:", $toRemove);
            \App\Models\Attendance::where('subject_id', $subjectId)
                ->where('class_id', $classId)
                ->whereDate('date', $today)
                ->where('teacher_id', $teacherId)
                ->whereIn('student_number', $toRemove)
                ->delete();
        }

        // Insert new attendance records.
        foreach ($toAdd as $studentNumber) {
            \App\Models\Attendance::create([
                'date' => $today,
                'subject_id' => $subjectId,
                'class_id' => $classId,
                'student_number' => $studentNumber,
                'teacher_id' => $teacherId,
            ]);
        }

        Log::info(session('teacher_name').' submitted student attendance.');

        return redirect()->route('attendance.selection')->with('message', 'Attendance submitted successfully.');
    }
}
