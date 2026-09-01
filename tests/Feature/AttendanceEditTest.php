<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
            'submitted_students' => [$student->student_number],
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
        // The student's row was in the grid, so their marker submits.
        $response = $this->post(route('attendance.edit.update'), [
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'dates' => [$date],
            'submitted_students' => [$student->student_number],
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
            'submitted_students' => [$student->student_number],
            'present' => [],
        ]);

        // The off-grid record survives.
        $this->assertDatabaseHas('attendances', [
            'student_number' => 'E004',
            'date' => $otherDate,
        ]);
    }

    /**
     * The data-loss regression: filtering the grid to one student (via the
     * DataTables search) drops every other row — and its checkboxes — from the
     * POST. The reconcile must leave those filtered-out students' history alone,
     * not read the absent boxes as "unticked" and delete it.
     */
    public function test_a_filtered_submit_does_not_wipe_other_students_history(): void
    {
        $teacher = $this->actingAsTeacher();
        $subject = Subject::factory()->create();
        $class = ClassModel::factory()->create();

        $edited = $this->enrolledStudent($subject, $class, 'F001', 'Edited');
        $others = [
            $this->enrolledStudent($subject, $class, 'F002', 'Bystander'),
            $this->enrolledStudent($subject, $class, 'F003', 'Alsohere'),
        ];

        // Every student already has attendance on two prior dates.
        $d1 = now()->subDays(14)->toDateString();
        $d2 = now()->subDays(7)->toDateString();
        foreach ([$edited, ...$others] as $s) {
            foreach ([$d1, $d2] as $date) {
                Attendance::create([
                    'date' => $date,
                    'subject_id' => $subject->id,
                    'class_id' => $class->id,
                    'student_number' => $s->student_number,
                    'teacher_id' => $teacher->id,
                ]);
            }
        }

        // The teacher searched for "Edited", so ONLY that row is in the DOM.
        // A new date column is added and ticked just for them; the other rows
        // (and their markers/checkboxes) are absent from the submission.
        $newDate = now()->subDays(3)->toDateString();
        $this->post(route('attendance.edit.update'), [
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'dates' => [$d1, $d2, $newDate],
            'submitted_students' => [$edited->student_number],
            'present' => [
                $edited->student_number => [$d1 => '1', $d2 => '1', $newDate => '1'],
            ],
        ]);

        // The edited student gains the new date and keeps the old ones.
        $this->assertSame(3, Attendance::where('student_number', 'F001')->count());

        // The bystanders' history is fully intact — nothing was deleted.
        foreach (['F002', 'F003'] as $number) {
            $this->assertSame(2, Attendance::where('student_number', $number)->count(), "History for {$number} must survive a filtered submit");
        }
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
        $response->assertSee(Carbon::parse($addDate)->format('j M'));
    }
}
