@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-4">Attendance Details</h2>
    <ul class="list-group">
        @forelse ($students as $student)
            <li class="list-group-item">{{ $student->first_name }} {{ $student->last_name }}</li>
        @empty
            <li class="list-group-item">No students found for this attendance.</li>
        @endforelse
    </ul>
    <a href="{{ route('attendance.summary') }}" class="btn btn-secondary mt-3">Back to Summary</a>
</div>
@endsection
