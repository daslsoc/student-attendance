<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\IntegrationSyncState;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\RegistrationSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RegistrationSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'integration.registration_url' => 'https://registration.test',
            'integration.registration_token' => 'shared-secret',
            'integration.subject_for_dhamma' => 'Buddhism',
            'integration.subject_for_sinhala' => 'Sinhala',
        ]);
    }

    private function fakeChanges(array $students, string $lastChangedAt = '2026-06-28 10:00:00', ?int $count = null, array $removed = []): void
    {
        Http::fake(['https://registration.test/*' => Http::response([
            'last_changed_at' => $lastChangedAt,
            'count' => $count ?? count($students),
            'students' => $students,
            'removed' => $removed,
        ], 200)]);
    }

    private function child(array $overrides = []): array
    {
        return array_merge([
            'student_number' => '4321',
            'first_name' => 'Amara',
            'last_name' => 'Perera',
            'allocated_dhamma_class' => 'Class C',
            'allocated_sinhala_class' => 'Class C',
        ], $overrides);
    }

    private function sync(): array
    {
        return app(RegistrationSyncService::class)->sync();
    }

    public function test_it_creates_the_student_and_both_enrollments(): void
    {
        $buddhism = Subject::factory()->create(['name' => 'Buddhism']);
        $sinhala = Subject::factory()->create(['name' => 'Sinhala']);
        $classC = ClassModel::factory()->create(['name' => 'Class C']);
        $this->fakeChanges([$this->child()]);

        $result = $this->sync();

        $this->assertDatabaseHas('students', ['student_number' => '4321', 'first_name' => 'Amara']);
        $this->assertDatabaseHas('enrollments', ['student_number' => '4321', 'subject_id' => $buddhism->id, 'class_id' => $classC->id]);
        $this->assertDatabaseHas('enrollments', ['student_number' => '4321', 'subject_id' => $sinhala->id, 'class_id' => $classC->id]);
        $this->assertSame(2, Enrollment::where('student_number', '4321')->count());
        $this->assertSame(2, $result['enrolled']);
    }

    public function test_it_moves_a_student_when_the_allocation_changes(): void
    {
        $buddhism = Subject::factory()->create(['name' => 'Buddhism']);
        Subject::factory()->create(['name' => 'Sinhala']);
        $classA = ClassModel::factory()->create(['name' => 'Class A']);
        $classC = ClassModel::factory()->create(['name' => 'Class C']);
        Student::create(['student_number' => '4321', 'first_name' => 'Amara', 'last_name' => 'Perera']);
        Enrollment::create(['student_number' => '4321', 'subject_id' => $buddhism->id, 'class_id' => $classA->id]);

        $this->fakeChanges([$this->child(['allocated_dhamma_class' => 'Class C', 'allocated_sinhala_class' => 'Class C'])]);
        $result = $this->sync();

        $this->assertSame(1, Enrollment::where('student_number', '4321')->where('subject_id', $buddhism->id)->count());
        $this->assertDatabaseHas('enrollments', ['student_number' => '4321', 'subject_id' => $buddhism->id, 'class_id' => $classC->id]);
        $this->assertDatabaseMissing('enrollments', ['student_number' => '4321', 'subject_id' => $buddhism->id, 'class_id' => $classA->id]);
        $this->assertSame(1, $result['moved']);
    }

    public function test_it_is_idempotent(): void
    {
        Subject::factory()->create(['name' => 'Buddhism']);
        Subject::factory()->create(['name' => 'Sinhala']);
        ClassModel::factory()->create(['name' => 'Class C']);
        $this->fakeChanges([$this->child()]);

        $this->sync();
        $this->sync();

        $this->assertSame(2, Enrollment::where('student_number', '4321')->count());
    }

    public function test_a_dry_run_reports_counts_but_writes_nothing(): void
    {
        Subject::factory()->create(['name' => 'Buddhism']);
        Subject::factory()->create(['name' => 'Sinhala']);
        ClassModel::factory()->create(['name' => 'Class C']);
        $this->fakeChanges([$this->child()]);

        $result = app(RegistrationSyncService::class)->sync(true);

        // It reports what a real run would do...
        $this->assertSame(2, $result['enrolled']);
        $this->assertTrue($result['dry_run']);

        // ...but nothing is persisted: no student, no enrollments, no state.
        $this->assertDatabaseMissing('students', ['student_number' => '4321']);
        $this->assertSame(0, Enrollment::count());
        $this->assertNull(IntegrationSyncState::current()->last_synced_at);
    }

    public function test_it_logs_an_audit_line_for_every_run(): void
    {
        Subject::factory()->create(['name' => 'Buddhism']);
        Subject::factory()->create(['name' => 'Sinhala']);
        ClassModel::factory()->create(['name' => 'Class C']);
        $this->fakeChanges([$this->child()]);

        Log::spy();

        $this->sync();

        Log::shouldHaveReceived('info')
            ->withArgs(fn ($message, $context = []) => str_contains($message, 'Registration sync')
                && ($context['received'] ?? null) === 1
                && ($context['enrolled'] ?? null) === 2)
            ->once();
    }

    public function test_it_skips_a_missing_class_with_a_warning(): void
    {
        Subject::factory()->create(['name' => 'Buddhism']);
        Subject::factory()->create(['name' => 'Sinhala']);
        // "Class C" deliberately not created.
        $this->fakeChanges([$this->child()]);

        $result = $this->sync();

        $this->assertSame(0, Enrollment::count());
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_a_null_allocation_leaves_existing_enrollment_alone(): void
    {
        $buddhism = Subject::factory()->create(['name' => 'Buddhism']);
        Subject::factory()->create(['name' => 'Sinhala']);
        $classA = ClassModel::factory()->create(['name' => 'Class A']);
        Student::create(['student_number' => '4321', 'first_name' => 'Amara', 'last_name' => 'Perera']);
        Enrollment::create(['student_number' => '4321', 'subject_id' => $buddhism->id, 'class_id' => $classA->id]);

        $this->fakeChanges([$this->child(['allocated_dhamma_class' => null, 'allocated_sinhala_class' => null])]);
        $this->sync();

        $this->assertDatabaseHas('enrollments', ['student_number' => '4321', 'subject_id' => $buddhism->id, 'class_id' => $classA->id]);
    }

    public function test_a_removed_student_is_unenrolled_but_record_and_history_are_kept(): void
    {
        $buddhism = Subject::factory()->create(['name' => 'Buddhism']);
        $classC = ClassModel::factory()->create(['name' => 'Class C']);
        $teacher = User::factory()->create();
        Student::create(['student_number' => '4321', 'first_name' => 'Amara', 'last_name' => 'Perera']);
        Enrollment::create(['student_number' => '4321', 'subject_id' => $buddhism->id, 'class_id' => $classC->id]);
        Attendance::create([
            'date' => '2026-06-14',
            'subject_id' => $buddhism->id,
            'class_id' => $classC->id,
            'student_number' => '4321',
            'teacher_id' => $teacher->id,
        ]);

        // No students, but 4321 is now in the removed list.
        $this->fakeChanges([], '2026-06-29 09:00:00', 0, ['4321']);
        $result = $this->sync();

        // Off the class roster…
        $this->assertSame(0, Enrollment::where('student_number', '4321')->count());
        $this->assertSame(1, $result['removed']);
        // …but the student row and their attendance history survive.
        $this->assertDatabaseHas('students', ['student_number' => '4321']);
        $this->assertDatabaseHas('attendances', ['student_number' => '4321', 'date' => '2026-06-14']);
    }

    public function test_removing_an_unknown_student_is_a_no_op(): void
    {
        $this->fakeChanges([], '2026-06-29 09:00:00', 0, ['9999']);

        $result = $this->sync();

        $this->assertSame(0, $result['removed']);
        $this->assertSame(0, Enrollment::count());
    }

    public function test_a_dry_run_does_not_unenroll_a_removed_student(): void
    {
        $buddhism = Subject::factory()->create(['name' => 'Buddhism']);
        $classC = ClassModel::factory()->create(['name' => 'Class C']);
        Student::create(['student_number' => '4321', 'first_name' => 'Amara', 'last_name' => 'Perera']);
        Enrollment::create(['student_number' => '4321', 'subject_id' => $buddhism->id, 'class_id' => $classC->id]);

        $this->fakeChanges([], '2026-06-29 09:00:00', 0, ['4321']);
        $result = app(RegistrationSyncService::class)->sync(true);

        // Reports the would-be removal but persists nothing.
        $this->assertSame(1, $result['removed']);
        $this->assertTrue($result['dry_run']);
        $this->assertSame(1, Enrollment::where('student_number', '4321')->count());
    }

    public function test_it_records_state_and_sends_since_on_the_next_run(): void
    {
        Subject::factory()->create(['name' => 'Buddhism']);
        Subject::factory()->create(['name' => 'Sinhala']);
        ClassModel::factory()->create(['name' => 'Class C']);
        $this->fakeChanges([$this->child()], '2026-06-28 10:00:00');

        $this->sync();

        $state = IntegrationSyncState::current();
        $this->assertNotNull($state->last_synced_at);
        $this->assertNotNull($state->last_checked_at);
        $this->assertSame(1, $state->last_count);

        $this->sync();
        Http::assertSent(fn ($request) => str_contains($request->url(), 'since='));
    }

    public function test_status_page_shows_the_last_sync_info(): void
    {
        $this->actingAsTeacher();
        IntegrationSyncState::current()->update(['last_synced_at' => now()->subHour(), 'last_count' => 5]);

        $this->get(route('integration.status'))
            ->assertStatus(200)
            ->assertSee('Last synced')
            ->assertSee('5');
    }

    public function test_sync_now_button_runs_a_sync(): void
    {
        $this->actingAsTeacher();
        Subject::factory()->create(['name' => 'Buddhism']);
        Subject::factory()->create(['name' => 'Sinhala']);
        ClassModel::factory()->create(['name' => 'Class C']);
        $this->fakeChanges([$this->child()]);

        $this->post(route('integration.sync'))
            ->assertRedirect(route('integration.status'))
            ->assertSessionHas('message');

        $this->assertDatabaseHas('students', ['student_number' => '4321']);
    }
}
