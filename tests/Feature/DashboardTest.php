<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_shows_present_over_enrolled_per_subject_and_class(): void
    {
        $teacher = $this->actingAsTeacher();
        $subject = Subject::factory()->create(['name' => 'Mathematics']);
        $class = ClassModel::factory()->create(['name' => 'Grade A']);

        // Two enrolled, one present today.
        foreach (['S010' => 'Ada', 'S011' => 'Alan'] as $number => $first) {
            Student::create(['student_number' => $number, 'first_name' => $first, 'last_name' => 'Test']);
            Enrollment::create(['student_number' => $number, 'subject_id' => $subject->id, 'class_id' => $class->id]);
        }
        Attendance::create([
            'date' => now()->toDateString(),
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'student_number' => 'S010',
            'teacher_id' => $teacher->id,
        ]);

        $response = $this->get(route('attendance.summary'));

        $response->assertStatus(200);
        $response->assertSee('Mathematics');
        $response->assertSee('Grade A');
        // present / enrolled = 1 / 2 at both subject and class level.
        $response->assertSee('1 / 2');
    }

    public function test_details_requires_a_subject(): void
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
            'student_number' => 'S012',
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

    public function test_details_with_subject_only_lists_present_across_classes(): void
    {
        $teacher = $this->actingAsTeacher();
        $subject = Subject::factory()->create();
        $classA = ClassModel::factory()->create();
        $classB = ClassModel::factory()->create();

        foreach (['S013' => [$classA, 'Katherine'], 'S014' => [$classB, 'Dorothy']] as $number => [$class, $first]) {
            Student::create(['student_number' => $number, 'first_name' => $first, 'last_name' => 'Johnson']);
            Attendance::create([
                'date' => now()->toDateString(),
                'subject_id' => $subject->id,
                'class_id' => $class->id,
                'student_number' => $number,
                'teacher_id' => $teacher->id,
            ]);
        }

        $response = $this->get(route('attendance.details', ['subject_id' => $subject->id]));

        $response->assertStatus(200);
        $response->assertSee('Katherine');
        $response->assertSee('Dorothy');
    }

    public function test_report_shows_a_student_with_their_total_days_attended(): void
    {
        $teacher = $this->actingAsTeacher();
        $subject = Subject::factory()->create(['name' => 'Sinhala']);
        $class = ClassModel::factory()->create();
        $student = Student::create([
            'student_number' => 'S015',
            'first_name' => 'Margaret',
            'last_name' => 'Hamilton',
        ]);
        Enrollment::create([
            'student_number' => $student->student_number,
            'subject_id' => $subject->id,
            'class_id' => $class->id,
        ]);

        // Present on two distinct dates this year.
        foreach ([now()->toDateString(), now()->subDays(7)->toDateString()] as $date) {
            Attendance::create([
                'date' => $date,
                'subject_id' => $subject->id,
                'class_id' => $class->id,
                'student_number' => $student->student_number,
                'teacher_id' => $teacher->id,
            ]);
        }

        $response = $this->get(route('attendance.report', ['subject_id' => $subject->id]));

        $response->assertStatus(200);
        $response->assertSee('Margaret');
        // Total days attended.
        $response->assertSee('2');
    }

    public function test_report_without_a_subject_just_shows_the_picker(): void
    {
        $this->actingAsTeacher();

        $response = $this->get(route('attendance.report'));

        $response->assertStatus(200);
        $response->assertSee('Choose a subject');
    }
}
