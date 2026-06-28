@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-1">Today's Report</h2>
    <p class="text-muted mb-4">Each badge shows <strong>present today / enrolled</strong>. Click a subject total or a class to see who's present.</p>

    <div class="row g-4">
        @forelse ($dashboard as $subject)
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-bold">{{ $subject['subject_name'] }}</span>
                        <a href="{{ route('attendance.details', ['subject_id' => $subject['subject_id']]) }}"
                           class="text-decoration-none"
                           title="See everyone present in {{ $subject['subject_name'] }} today">
                            <span class="badge bg-primary rounded-pill fs-6">
                                {{ $subject['present'] }} / {{ $subject['enrolled'] }}
                            </span>
                        </a>
                    </div>
                    <ul class="list-group list-group-flush">
                        @forelse ($subject['classes'] as $class)
                            <a href="{{ route('attendance.details', ['subject_id' => $subject['subject_id'], 'class_id' => $class['class_id']]) }}"
                               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                {{ $class['class_name'] }}
                                <span class="badge bg-secondary rounded-pill">
                                    {{ $class['present'] }} / {{ $class['enrolled'] }}
                                </span>
                            </a>
                        @empty
                            <li class="list-group-item text-muted">No classes enrolled yet.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        @empty
            <div class="col">
                <p class="text-muted">No subjects yet.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
