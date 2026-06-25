@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-4">Today's Attendance Summary</h2>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Subject</th>
                <th>Class</th>
                <th>Count</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($summary as $item)
                <tr>
                    <td>{{ $item['subject_name'] }}</td>
                    <td>{{ $item['class_name'] }}</td>
                    <td>
                        <a href="{{ route('attendance.details', ['subject_id' => $item['subject_id'], 'class_id' => $item['class_id']]) }}">
                            {{ $item['count'] }}
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">No attendance recorded today.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
