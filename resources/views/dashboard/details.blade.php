@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-1">Present today</h2>
    <p class="text-muted mb-4">
        {{ $subject->name ?? 'Unknown subject' }}@if ($class) &middot; {{ $class->name }}@else <span class="text-muted">(all classes)</span>@endif
    </p>
    <ul class="list-group">
        @forelse ($students as $student)
            <li class="list-group-item">{{ $student->first_name }} {{ $student->last_name }}</li>
        @empty
            <li class="list-group-item">No students marked present.</li>
        @endforelse
    </ul>
    <a href="{{ route('attendance.summary') }}" class="btn btn-secondary mt-3">Back to Summary</a>
</div>
@endsection
