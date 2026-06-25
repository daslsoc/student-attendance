@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <h2 class="mb-4">Mark Attendance</h2>
        <form action="{{ route('attendance.submit') }}" method="POST">
            @csrf
            <input type="hidden" name="subject_id" value="{{ $subjectId }}">
            <input type="hidden" name="class_id" value="{{ $classId }}">
            <input type="hidden" name="present_students" id="present_students" value="[]">
            <div class="mb-3">
                <div class="d-flex flex-wrap gap-2">
                    @foreach($students as $student)
                        @php
                            $isAttended = in_array($student->student_number, $attendedStudents);
                        @endphp
                        <button type="button" class="btn student-btn {{ $isAttended ? 'btn-success' : 'btn-outline-primary' }}" data-student="{{ $student->student_number }}">
                            {{ $student->first_name }} {{ $student->last_name }}
                        </button>
                    @endforeach
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Submit Attendance</button>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Pre-populate with already attended students.
    let presentStudents = {!! json_encode($attendedStudents) !!};
    document.getElementById('present_students').value = JSON.stringify(presentStudents);
    
    document.querySelectorAll('.student-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const studentId = this.getAttribute('data-student');
            // Toggle selection: add if not present, remove if already selected.
            if (presentStudents.includes(studentId)) {
                presentStudents = presentStudents.filter(id => id !== studentId);
                this.classList.remove('btn-success');
                this.classList.add('btn-outline-primary');
            } else {
                presentStudents.push(studentId);
                this.classList.remove('btn-outline-primary');
                this.classList.add('btn-success');
            }
            document.getElementById('present_students').value = JSON.stringify(presentStudents);
        });
    });
</script>
@endsection