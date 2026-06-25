@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <h2 class="mb-4">Select Subject and Class</h2>
        <form action="{{ route('attendance.form') }}" method="GET">
            <div class="mb-3">
                <label for="subject_id" class="form-label">Subject:</label>
                <select name="subject_id" id="subject_id" class="form-select" required>
                    @foreach($subjects as $subject)
                        <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label for="class_id" class="form-label">Class:</label>
                <select name="class_id" id="class_id" class="form-select" required>
                    @foreach($classes as $class)
                        <option value="{{ $class->id }}">{{ $class->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Continue</button>
        </form>
    </div>
</div>
@endsection
