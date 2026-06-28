@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <h2 class="mb-1">Edit Attendance</h2>
    <p class="text-muted mb-4">{{ now()->year }} &middot; tick the dates each student attended, then Save. Use this to back-fill students who were missed.</p>

    {{-- Pick a subject + class to edit. --}}
    <form action="{{ route('attendance.edit') }}" method="GET" class="row g-2 align-items-end mb-4">
        <div class="col-auto">
            <label for="subject_id" class="form-label">Subject</label>
            <select name="subject_id" id="subject_id" class="form-select" required>
                <option value="" disabled {{ $subject ? '' : 'selected' }}>Choose a subject&hellip;</option>
                @foreach ($subjects as $s)
                    <option value="{{ $s->id }}" {{ $subject && $subject->id == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <label for="class_id" class="form-label">Class</label>
            <select name="class_id" id="class_id" class="form-select" required>
                <option value="" disabled {{ $class ? '' : 'selected' }}>Choose a class&hellip;</option>
                @foreach ($classes as $c)
                    <option value="{{ $c->id }}" {{ $class && $class->id == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">Show</button>
        </div>
    </form>

    @if ($subject && $class)
        <h4 class="mb-3">{{ $subject->name }} &middot; {{ $class->name }}</h4>

        @if ($students->isEmpty())
            <p class="text-muted">No students enrolled in {{ $subject->name }} &middot; {{ $class->name }}.</p>
        @else
            {{-- Add an extra date column for a session that has no records yet. --}}
            <form action="{{ route('attendance.edit') }}" method="GET" class="row g-2 align-items-end mb-3">
                <input type="hidden" name="subject_id" value="{{ $subject->id }}">
                <input type="hidden" name="class_id" value="{{ $class->id }}">
                <div class="col-auto">
                    <label for="add_date" class="form-label">Add a date</label>
                    <input type="date" name="add_date" id="add_date" class="form-control"
                           max="{{ now()->toDateString() }}" required>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-secondary">Add column</button>
                </div>
            </form>

            {{-- The grid itself: one Save persists every tick/untick. --}}
            <form action="{{ route('attendance.edit.update') }}" method="POST">
                @csrf
                <input type="hidden" name="subject_id" value="{{ $subject->id }}">
                <input type="hidden" name="class_id" value="{{ $class->id }}">
                @foreach ($dates as $date)
                    <input type="hidden" name="dates[]" value="{{ $date }}">
                @endforeach

                @if ($dates->isEmpty())
                    <p class="text-muted">No dates yet &mdash; use &ldquo;Add a date&rdquo; above to start a column, then tick and Save.</p>
                @endif

                <div class="table-responsive">
                    <table id="edit-table" class="table table-bordered table-sm align-middle text-center mb-3">
                        <thead class="table-light">
                            <tr>
                                <th class="text-start text-nowrap">Student</th>
                                @foreach ($dates as $date)
                                    <th class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($date)->format('j M') }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($students as $student)
                                <tr>
                                    <td class="text-start text-nowrap">{{ $student->first_name }} {{ $student->last_name }}</td>
                                    @foreach ($dates as $date)
                                        <td>
                                            <input type="checkbox"
                                                   class="form-check-input"
                                                   name="present[{{ $student->student_number }}][{{ $date }}]"
                                                   value="1"
                                                   @checked(! empty($present[$student->student_number][$date]))>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <button type="submit" class="btn btn-success" @disabled($dates->isEmpty())>Save</button>
            </form>
        @endif
    @endif
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css">
@endpush

@section('scripts')
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js"></script>
<script>
  // Search + sort by name. Paging is OFF on purpose: every checkbox must stay
  // in the DOM so a Save submits the whole grid, not just the current page.
  if (document.getElementById('edit-table')) {
    new DataTable('#edit-table', {
      paging: false,
      info: false,
      scrollX: true,
      order: [],
      columnDefs: [
        { orderable: false, targets: '_all' },
        { orderable: true, targets: [0] },
      ],
    });
  }
</script>
@endsection
