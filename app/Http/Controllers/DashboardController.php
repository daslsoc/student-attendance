<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Models\ClassModel;
use Illuminate\Http\Request;

class DashboardController extends Controller
{

    public function summary()
    {
        $today = now()->toDateString();
        # SELECT subject_id, class_id, count( DISTINCT subject_id, class_id, student_number) as `count` FROM `attendances` group by subject_id, class_id;
        $attendanceSummary = \App\Models\Attendance::select('subject_id', 'class_id', \DB::raw('COUNT(DISTINCT subject_id, class_id, student_number) as count'))
            ->whereDate('date', $today)
            ->groupBy('subject_id', 'class_id')
            ->get();

        $summary = $attendanceSummary->map(function ($item) {
            $subject = \App\Models\Subject::find($item->subject_id);
            $class = \App\Models\ClassModel::find($item->class_id);
            return [
                'subject_id'   => $item->subject_id,
                'class_id'     => $item->class_id,
                'subject_name' => $subject ? $subject->name : 'Unknown',
                'class_name'   => $class ? $class->name : 'Unknown',
                'count'        => $item->count,
            ];
        });

        return view('dashboard.summary', compact('summary'));
    }

    public function details(Request $request)
    {
        $subjectId = $request->query('subject_id');
        $classId   = $request->query('class_id');
        if (!$subjectId || !$classId) {
            return redirect()->route('attendance.summary')->withErrors('Subject and class are required.');
        }
        $today = now()->toDateString();
        $attendances = \App\Models\Attendance::where('subject_id', $subjectId)
            ->where('class_id', $classId)
            ->whereDate('date', $today)
            ->get();

        $studentNumbers = $attendances->pluck('student_number')->toArray();
        $students = \App\Models\Student::whereIn('student_number', $studentNumbers)->get();

        return view('dashboard.details', compact('students'));
    }
}
