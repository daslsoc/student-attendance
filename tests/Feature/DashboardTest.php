<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\ClassModel;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_lists_todays_attendance_per_subject_and_class(): void
    {
        $teacher = $this->actingAsTeacher();
        $subject = Subject::factory()->create(['name' => 'Mathematics']);
        $class = ClassModel::factory()->create(['name' => 'Grade A']);
        $student = Student::create([
            'student_number' => 'S010',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
        ]);
        Attendance::create([
            'date' => now()->toDateString(),
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'student_number' => $student->student_number,
            'teacher_id' => $teacher->id,
        ]);

        $response = $this->get(route('attendance.summary'));

        $response->assertStatus(200);
        $response->assertSee('Mathematics');
        $response->assertSee('Grade A');
    }

    public function test_details_requires_a_subject_and_class(): void
    {
        $this->actingAsTeacher();

        $response = $this->get(route('attendance.details'));

        $response->assertRedirect(route('attendance.summary'));
    }

    public function test_details_lists_students_marked_present_today(): void
    {
        $teacher = $this->actingAsTeacher();
        $subject = Subject::factory()->create();
        $class = ClassModel::factory()->create();
        $student = Student::create([
            'student_number' => 'S011',
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
        ]);
        Attendance::create([
            'date' => now()->toDateString(),
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'student_number' => $student->student_number,
            'teacher_id' => $teacher->id,
        ]);

        $response = $this->get(route('attendance.details', [
            'subject_id' => $subject->id,
            'class_id' => $class->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Grace');
    }
}
