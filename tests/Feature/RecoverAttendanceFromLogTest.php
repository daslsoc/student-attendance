<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\ClassModel;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecoverAttendanceFromLogTest extends TestCase
{
    use RefreshDatabase;

    private string $logPath;

    private int $teacherId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logPath = tempnam(sys_get_temp_dir(), 'recover-log-');
        // attendances.teacher_id is a NOT-NULL FK, so the logged teacher must
        // exist for a rebuild to succeed.
        $this->teacherId = User::factory()->create()->id;
    }

    protected function tearDown(): void
    {
        @unlink($this->logPath);
        parent::tearDown();
    }

    private function deletedLine(string $ts, int $subjectId, int $classId, string $number, string $date, ?int $teacherId = null): string
    {
        $json = json_encode([
            'id' => random_int(1, 9999), 'date' => $date, 'subject_id' => $subjectId,
            'class_id' => $classId, 'student_number' => $number, 'teacher_id' => $teacherId ?? $this->teacherId,
        ]);

        return "[{$ts}] production.INFO: Attendance deleted {$json}".PHP_EOL;
    }

    public function test_it_rebuilds_clustered_deletions_and_skips_single_ones(): void
    {
        $subject = Subject::factory()->create();
        $class = ClassModel::factory()->create();
        foreach (['A1', 'A2', 'A3'] as $n) {
            Student::create(['student_number' => $n, 'first_name' => $n, 'last_name' => 'T']);
        }

        // A same-second burst of 3 (the wipe) plus one lone deletion elsewhere.
        file_put_contents($this->logPath,
            $this->deletedLine('2026-05-01 10:00:00', $subject->id, $class->id, 'A1', '2026-04-01').
            $this->deletedLine('2026-05-01 10:00:00', $subject->id, $class->id, 'A2', '2026-04-01').
            $this->deletedLine('2026-05-01 10:00:00', $subject->id, $class->id, 'A3', '2026-04-01').
            $this->deletedLine('2026-05-02 09:00:00', $subject->id, $class->id, 'A1', '2026-04-08')
        );

        $this->artisan('attendance:recover-from-log', [
            'log' => $this->logPath,
            '--min-cluster' => 3,
        ])->assertSuccessful();

        // The three clustered rows are back…
        foreach (['A1', 'A2', 'A3'] as $n) {
            $this->assertDatabaseHas('attendances', [
                'subject_id' => $subject->id, 'class_id' => $class->id,
                'student_number' => $n, 'date' => '2026-04-01',
            ]);
        }
        // …the lone deletion is not resurrected.
        $this->assertDatabaseMissing('attendances', ['student_number' => 'A1', 'date' => '2026-04-08']);
    }

    public function test_it_is_idempotent_and_never_duplicates(): void
    {
        $subject = Subject::factory()->create();
        $class = ClassModel::factory()->create();
        Student::create(['student_number' => 'B1', 'first_name' => 'B', 'last_name' => 'T']);

        file_put_contents($this->logPath,
            $this->deletedLine('2026-05-01 10:00:00', $subject->id, $class->id, 'B1', '2026-04-01').
            $this->deletedLine('2026-05-01 10:00:00', $subject->id, $class->id, 'B1', '2026-04-01')
        );

        $this->artisan('attendance:recover-from-log', ['log' => $this->logPath])->assertSuccessful();
        $this->artisan('attendance:recover-from-log', ['log' => $this->logPath])->assertSuccessful();

        $this->assertSame(1, Attendance::where('student_number', 'B1')->where('date', '2026-04-01')->count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $subject = Subject::factory()->create();
        $class = ClassModel::factory()->create();
        Student::create(['student_number' => 'C1', 'first_name' => 'C', 'last_name' => 'T']);

        file_put_contents($this->logPath,
            $this->deletedLine('2026-05-01 10:00:00', $subject->id, $class->id, 'C1', '2026-04-01')
        );

        $this->artisan('attendance:recover-from-log', ['log' => $this->logPath, '--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseMissing('attendances', ['student_number' => 'C1']);
    }

    public function test_it_skips_rows_whose_student_no_longer_exists(): void
    {
        $subject = Subject::factory()->create();
        $class = ClassModel::factory()->create();
        // No student 'GONE' created.

        file_put_contents($this->logPath,
            $this->deletedLine('2026-05-01 10:00:00', $subject->id, $class->id, 'GONE', '2026-04-01')
        );

        $this->artisan('attendance:recover-from-log', ['log' => $this->logPath])->assertSuccessful();

        $this->assertDatabaseMissing('attendances', ['student_number' => 'GONE']);
    }
}
