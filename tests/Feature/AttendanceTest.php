<?php

namespace Tests\Feature;

use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_form_requires_a_subject_and_class(): void
    {
        $this->actingAsTeacher();

        $response = $this->get(route('attendance.form'));

        $response->assertRedirect(route('attendance.selection'));
    }

    public function test_attendance_can_be_submitted_for_present_students(): void
    {
        $teacher = $this->actingAsTeacher();
        $subject = Subject::factory()->create();
        $class = ClassModel::factory()->create();

        $student = Student::create([
            'student_number' => 'S001',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
        Enrollment::create([
            'student_number' => $student->student_number,
            'subject_id' => $subject->id,
            'class_id' => $class->id,
        ]);

        $response = $this->post(route('attendance.submit'), [
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'present_students' => json_encode(['S001']),
        ]);

        $response->assertRedirect(route('attendance.selection'));
        $this->assertDatabaseHas('attendances', [
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'student_number' => 'S001',
            'teacher_id' => $teacher->id,
        ]);
    }

    public function test_unselecting_a_student_removes_todays_attendance(): void
    {
        $teacher = $this->actingAsTeacher();
        $subject = Subject::factory()->create();
        $class = ClassModel::factory()->create();
        $student = Student::create([
            'student_number' => 'S002',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        // First submission marks the student present.
        $this->post(route('attendance.submit'), [
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'present_students' => json_encode(['S002']),
        ]);
        $this->assertDatabaseHas('attendances', ['student_number' => 'S002']);

        // Re-submitting with an empty list removes today's record.
        $this->post(route('attendance.submit'), [
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'present_students' => json_encode([]),
        ]);

        $this->assertDatabaseMissing('attendances', [
            'student_number' => 'S002',
            'teacher_id' => $teacher->id,
        ]);
    }

    public function test_submit_validates_subject_and_class_exist(): void
    {
        $this->actingAsTeacher();

        $response = $this->post(route('attendance.submit'), [
            'subject_id' => 999,
            'class_id' => 999,
            'present_students' => json_encode(['S001']),
        ]);

        $response->assertSessionHasErrors(['subject_id', 'class_id']);
    }
}
