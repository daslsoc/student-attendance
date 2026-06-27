@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-1">Enrol from Registration</h2>
    <p class="text-muted mb-4">Students whose family has <strong>paid</strong> in the registration app but who aren&rsquo;t enrolled here yet. Pick a class for each subject (pre-filled from registration when its class name matches one of yours), then <strong>Enrol</strong>. Leave a subject on &ldquo;Don&rsquo;t enrol&rdquo; to skip it; a student with no class chosen stays in the list.</p>

    @if ($error)
        <div class="alert alert-danger">
            <strong>Couldn&rsquo;t load registration data.</strong> {{ $error }}
        </div>
    @endif

    @if (count($missingSubjects))
        <div class="alert alert-warning">
            These subjects aren&rsquo;t set up here yet, so you can&rsquo;t enrol into them:
            <strong>{{ implode(', ', $missingSubjects) }}</strong>. Create the matching Subject row first.
        </div>
    @endif

    @if (! $error)
        @if (count($students) === 0)
            <p class="text-muted">No paid students are waiting to be enrolled.</p>
        @elseif (count($subjectColumns) === 0)
            <p class="text-muted">No subjects are set up to enrol into.</p>
        @elseif ($classes->isEmpty())
            <p class="text-muted">There are no classes to choose from yet — add some classes first.</p>
        @else
            <form action="{{ route('integration.enroll') }}" method="POST">
                @csrf
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Age</th>
                                <th>Day school</th>
                                @foreach ($subjectColumns as $col)
                                    <th>{{ $col['name'] }} class</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($students as $student)
                                <tr>
                                    <td class="text-nowrap">{{ $student['student_number'] }}</td>
                                    <td class="text-nowrap">{{ $student['name'] }}</td>
                                    <td class="text-nowrap">{{ $student['age'] ?? '—' }}</td>
                                    <td class="text-nowrap">{{ $student['day_school'] !== '' ? $student['day_school'] : '—' }}</td>
                                    @foreach ($subjectColumns as $col)
                                        @php $regValue = $student['registration'][$col['field']] ?? null; @endphp
                                        <td>
                                            <select name="class_for[{{ $student['student_number'] }}][{{ $col['id'] }}]"
                                                    class="form-select form-select-sm">
                                                <option value="">— Don&rsquo;t enrol —</option>
                                                @foreach ($classes as $class)
                                                    <option value="{{ $class->id }}" @selected(($student['defaults'][$col['id']] ?? null) == $class->id)>{{ $class->name }}</option>
                                                @endforeach
                                            </select>
                                            @if ($regValue)
                                                <div class="small text-muted mt-1">registration: {{ $regValue }}</div>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button type="submit" class="btn btn-success">Enrol</button>
            </form>
        @endif
    @endif
</div>
@endsection
