@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <h2 class="mb-4">Mark Attendance</h2>
        <form action="{{ route('attendance.submit') }}" method="POST">
            @csrf
            <input type="hidden" name="subject_id" value="{{ $subjectId }}">
            <input type="hidden" name="class_id" value="{{ $classId }}">
            <input type="hidden" name="present_students" id="present_students"
                   data-selection-input
                   data-selected-class="btn-success"
                   data-unselected-class="btn-outline-primary"
                   value="{{ json_encode($attendedStudents) }}">
            <div class="mb-3">
                <div class="d-flex flex-wrap gap-2">
                    @foreach($students as $student)
                        @php
                            $isAttended = in_array($student->student_number, $attendedStudents);
                        @endphp
                        <button type="button" class="btn student-btn {{ $isAttended ? 'btn-success' : 'btn-outline-primary' }}" data-student="{{ $student->student_number }}">
                            {{ $student->first_name }} {{ $student->last_name }}
                        </button>
                    @endforeach
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Submit Attendance</button>
        </form>
    </div>
</div>
@endsection

{{-- Toggle behaviour lives in resources/js/studentSelector.js (bundled via
     @vite in the layout) and is unit-tested in tests/js. --}}
