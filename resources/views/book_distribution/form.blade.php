@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <h2 class="mb-4">Mark Books Given</h2>
        <form action="{{ route('book_distribution.submit') }}" method="POST">
            @csrf
            <input type="hidden" name="subject_id" value="{{ $subjectId }}">
            <input type="hidden" name="class_id" value="{{ $classId }}">
            <input type="hidden" name="present_students" id="present_students"
                   data-selection-input
                   data-selected-class="btn-success"
                   data-unselected-class="btn-outline-primary"
                   value="{{ json_encode($givenBooks) }}">
            <div class="mb-3">
                <div class="d-flex flex-wrap gap-2">
                    @foreach($students as $student)
                        @php
                            $isGiven = in_array($student->student_number, $givenBooks);
                        @endphp
                        <button type="button" class="btn student-btn {{ $isGiven ? 'btn-success' : 'btn-outline-primary' }}" data-student="{{ $student->student_number }}">
                            {{ $student->first_name }} {{ $student->last_name }}
                        </button>
                    @endforeach
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Submit Book Distribution</button>
        </form>
    </div>
</div>
@endsection

{{-- Toggle behaviour lives in resources/js/studentSelector.js (bundled via
     @vite in the layout) and is unit-tested in tests/js. --}}
