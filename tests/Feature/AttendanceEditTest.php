<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceEditTest extends TestCase
{
    use RefreshDatabase;

    private function enrolledStudent(Subject $subject, ClassModel $class, string $number, string $first): Student
    {
        $student = Student::create([
            'student_number' => $number,
            'first_name' => $first,
            'last_name' => 'Test',
        ]);
        Enrollment::create([
            'student_number' => $number,
            'subject_id' => $subject->id,
            'class_id' => $class->id,
        ]);

        return $student;
    }

    public function test_editor_requires_subject_and_class_to_show_a_grid(): void
    {
        $this->actingAsTeacher();

        $response = $this->get(route('attendance.edit'));

        $response->assertStatus(200);
        $response->assertSee('Choose a subject');
    }

    public function test_editor_shows_enrolled_students_for_the_chosen_subject_and_class(): void
    {
        $this->actingAsTeacher();
        $subject = Subject::factory()->create();
        $class = ClassModel::factory()->create();
        $this->enrolledStudent($subject, $class, 'E001', 'Edith');

        $response = $this->get(route('attendance.edit', [
            'subject_id' => $subject->id,
            'class_id' => $class->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Edith');
    }

    public function test_ticking_a_date_back_fills_attendance(): void
    {
        $teacher = $this->actingAsTeacher();
        $subject = Subject::factory()->create();
        $class = ClassModel::factory()->create();
        $student = $this->enrolledStudent($subject, $class, 'E002', 'Backfill');
        $date = now()->subDays(7)->toDateString();

        $response = $this->post(route('attendance.edit.update'), [
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'dates' => [$date],
            'present' => [
                $student->student_number => [$date => '1'],
            ],
        ]);

        $response->assertRedirect(route('attendance.edit', [
            'subject_id' => $subject->id,
            'class_id' => $class->id,
        ]));
        $this->assertDatabaseHas('attendances', [
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'student_number' => 'E002',
            'date' => $date,
            'teacher_id' => $teacher->id,
        ]);
    }

    public function test_unticking_a_date_removes_attendance(): void
    {
        $teacher = $this->actingAsTeacher();
        $subject = Subject::factory()->create();
        $class = ClassModel::factory()->create();
        $student = $this->enrolledStudent($subject, $class, 'E003', 'Remove');
        $date = now()->toDateString();
        Attendance::create([
            'date' => $date,
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'student_number' => $student->student_number,
            'teacher_id' => $teacher->id,
        ]);

        // Date shown, but the box left unticked (no 'present' entry) => delete.
        $response = $this->post(route('attendance.edit.update'), [
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'dates' => [$date],
            'present' => [],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('attendances', [
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'student_number' => 'E003',
            'date' => $date,
        ]);
    }

    public function test_update_only_touches_dates_that_were_shown(): void
    {
        $teacher = $this->actingAsTeacher();
        $subject = Subject::factory()->create();
        $class = ClassModel::factory()->create();
        $student = $this->enrolledStudent($subject, $class, 'E004', 'Untouched');

        // An existing record on a date NOT included in the submitted columns.
        $otherDate = now()->subDays(30)->toDateString();
        Attendance::create([
            'date' => $otherDate,
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'student_number' => $student->student_number,
            'teacher_id' => $teacher->id,
        ]);

        $shownDate = now()->toDateString();
        $this->post(route('attendance.edit.update'), [
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'dates' => [$shownDate],
            'present' => [],
        ]);

        // The off-grid record survives.
        $this->assertDatabaseHas('attendances', [
            'student_number' => 'E004',
            'date' => $otherDate,
        ]);
    }

    public function test_add_date_shows_an_empty_column(): void
    {
        $this->actingAsTeacher();
        $subject = Subject::factory()->create();
        $class = ClassModel::factory()->create();
        $this->enrolledStudent($subject, $class, 'E005', 'Columned');
        $addDate = now()->subDays(3)->toDateString();

        $response = $this->get(route('attendance.edit', [
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'add_date' => $addDate,
        ]));

        $response->assertStatus(200);
        // The column header renders the added date.
        $response->assertSee(\Illuminate\Support\Carbon::parse($addDate)->format('j M'));
    }
}
