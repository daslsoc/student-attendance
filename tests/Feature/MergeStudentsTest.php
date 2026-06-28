<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BookDistribution;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MergeStudentsTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private ClassModel $class;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = Subject::factory()->create(['name' => 'Buddhism']);
        $this->class = ClassModel::factory()->create(['name' => 'Class B']);
        $this->teacher = User::factory()->create();
    }

    private function student(string $number, string $first = 'Senaree', string $last = 'Piyasenage'): Student
    {
        return Student::create(['student_number' => $number, 'first_name' => $first, 'last_name' => $last]);
    }

    private function attendance(string $number, string $date): void
    {
        Attendance::create([
            'date' => $date,
            'subject_id' => $this->subject->id,
            'class_id' => $this->class->id,
            'student_number' => $number,
            'teacher_id' => $this->teacher->id,
        ]);
    }

    public function test_it_merges_split_history_onto_the_canonical_number(): void
    {
        $this->student('228');
        $this->student('230');
        Enrollment::create(['student_number' => '228', 'subject_id' => $this->subject->id, 'class_id' => $this->class->id]);
        Enrollment::create(['student_number' => '230', 'subject_id' => $this->subject->id, 'class_id' => $this->class->id]);
        $this->attendance('228', '2026-05-01');   // only on old
        $this->attendance('230', '2026-05-08');   // only on new
        $this->attendance('228', '2026-05-15');   // same date on both -> dedup
        $this->attendance('230', '2026-05-15');

        $this->artisan('integration:merge-students --merge=228:230')->assertExitCode(0);

        // Old record gone, canonical kept.
        $this->assertDatabaseMissing('students', ['student_number' => '228']);
        $this->assertDatabaseHas('students', ['student_number' => '230']);
        // The single shared enrollment survives once (the old duplicate dropped).
        $this->assertSame(1, Enrollment::where('student_number', '230')->count());
        $this->assertSame(0, Enrollment::where('student_number', '228')->count());
        // 3 distinct attendance days preserved (5/01, 5/08, 5/15), the dup dropped.
        $this->assertSame(3, Attendance::where('student_number', '230')->count());
        $this->assertSame(0, Attendance::where('student_number', '228')->count());
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->student('228');
        $this->student('230');
        $this->attendance('228', '2026-05-01');

        $this->artisan('integration:merge-students --merge=228:230 --dry-run')
            ->expectsOutputToContain('WOULD MERGE')
            ->assertExitCode(0);

        $this->assertDatabaseHas('students', ['student_number' => '228']);
        $this->assertSame(1, Attendance::where('student_number', '228')->count());
    }

    public function test_it_refuses_to_merge_different_children(): void
    {
        $this->student('74', 'Malitha', 'Munasinghe');
        $this->student('75', 'Bhanuka', 'Matara Liyanage'); // different child!

        $this->artisan('integration:merge-students --merge=74:75')
            ->expectsOutputToContain('names differ')
            ->assertExitCode(1);

        // Both records untouched.
        $this->assertDatabaseHas('students', ['student_number' => '74']);
        $this->assertDatabaseHas('students', ['student_number' => '75']);
    }

    public function test_it_renumbers_when_the_target_does_not_exist_yet(): void
    {
        $this->student('228');
        Enrollment::create(['student_number' => '228', 'subject_id' => $this->subject->id, 'class_id' => $this->class->id]);
        $this->attendance('228', '2026-05-01');

        $this->artisan('integration:merge-students --merge=228:230')->assertExitCode(0);

        $this->assertDatabaseMissing('students', ['student_number' => '228']);
        $this->assertDatabaseHas('students', ['student_number' => '230', 'first_name' => 'Senaree']);
        $this->assertSame(1, Enrollment::where('student_number', '230')->count());
        $this->assertSame(1, Attendance::where('student_number', '230')->count());
    }

    public function test_it_is_idempotent(): void
    {
        $this->student('228');
        $this->student('230');

        $this->artisan('integration:merge-students --merge=228:230')->assertExitCode(0);
        // Running again is a no-op (no #228 left).
        $this->artisan('integration:merge-students --merge=228:230')
            ->expectsOutputToContain('already merged')
            ->assertExitCode(0);

        $this->assertDatabaseHas('students', ['student_number' => '230']);
    }

    public function test_it_also_moves_book_distributions(): void
    {
        $this->student('228');
        $this->student('230');
        BookDistribution::create(['subject_id' => $this->subject->id, 'class_id' => $this->class->id, 'student_number' => '228', 'teacher_id' => $this->teacher->id]);

        $this->artisan('integration:merge-students --merge=228:230')->assertExitCode(0);

        $this->assertSame(1, BookDistribution::where('student_number', '230')->count());
        $this->assertSame(0, BookDistribution::where('student_number', '228')->count());
    }
}
