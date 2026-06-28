@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <h2 class="mb-1">Full Year Report</h2>
    <p class="text-muted mb-4">{{ now()->year }} &middot; one row per student, one column per date the subject met.</p>

    <form action="{{ route('attendance.report') }}" method="GET" class="row g-2 align-items-end mb-4">
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
            <button type="submit" class="btn btn-primary">Show</button>
        </div>
    </form>

    @if ($subject)
        <h4 class="mb-3">{{ $subject->name }}</h4>

        @if ($students->isEmpty())
            <p class="text-muted">No students enrolled in {{ $subject->name }}.</p>
        @elseif ($dates->isEmpty())
            <p class="text-muted">No attendance recorded for {{ $subject->name }} in {{ now()->year }}.</p>
        @else
            <div class="table-responsive">
                <table id="report-table" class="table table-bordered table-sm align-middle text-center mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-start text-nowrap">Student</th>
                            @foreach ($dates as $date)
                                <th class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($date)->format('j M') }}</th>
                            @endforeach
                            <th class="text-nowrap">Total days</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($students as $student)
                            <tr>
                                <td class="text-start text-nowrap">{{ $student->first_name }} {{ $student->last_name }}</td>
                                @foreach ($dates as $date)
                                    <td>
                                        @if (! empty($present[$student->student_number][$date]))
                                            <span class="text-success fw-bold">&check;</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="fw-bold">{{ $totals[$student->student_number] ?? 0 }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
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
  // Searchable, sortable, paginated. Only the Student name and Total days
  // columns sort meaningfully; the per-date tick columns are left unsorted.
  new DataTable('#report-table', {
    paging: true,
    pageLength: 25,
    lengthMenu: [[25, 50, 100, -1], [25, 50, 100, 'All']],
    scrollX: true,
    order: [],
    columnDefs: [
      { orderable: false, targets: '_all' },
      { orderable: true, targets: [0, -1] },
    ],
  });
</script>
@endsection
