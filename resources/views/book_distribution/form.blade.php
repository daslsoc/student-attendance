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
            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                <input type="search" class="form-control flex-grow-1" style="min-width: 12rem; max-width: 22rem"
                       placeholder="Search a student…" data-student-filter aria-label="Search students" autocomplete="off">
                <button type="button" class="btn btn-outline-secondary" data-select-all>Select all</button>
                <button type="button" class="btn btn-outline-secondary" data-clear-all>Clear</button>
            </div>
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

            {{-- Sticky action bar with the running count, handy on a phone. --}}
            <div class="position-sticky bottom-0 bg-body border-top py-2">
                <div class="d-flex align-items-center justify-content-between gap-2">
                    <span class="fs-5">
                        <span class="badge text-bg-success" data-selection-count>{{ count($givenBooks) }}</span>
                        / <span data-selection-total>{{ count($students) }}</span> given
                    </span>
                    <button type="submit" class="btn btn-primary">Submit Book Distribution</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

{{-- Toggle behaviour lives in resources/js/studentSelector.js (bundled via
     @vite in the layout) and is unit-tested in tests/js. --}}
