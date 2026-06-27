<?php

namespace Tests\Feature;

use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RegistrationImportTest extends TestCase
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
            'integration.no_class_value' => 'Did not attend last year',
        ]);
    }

    private function fakeApi(array $children): void
    {
        Http::fake([
            'https://registration.test/*' => Http::response(['data' => $children], 200),
        ]);
    }

    private function child(array $overrides = []): array
    {
        return array_merge([
            'student_number' => '4321',
            'first_name' => 'Amara',
            'last_name' => 'Perera',
            'date_of_birth' => '2016-01-01',
            'day_school_name' => 'Lyneham PS',
            'day_school_year' => '3',
            'dhamma_class' => 'Did not attend last year',
            'sinhala_class' => 'Did not attend last year',
        ], $overrides);
    }

    private function subjects(): array
    {
        return [
            'buddhism' => Subject::factory()->create(['name' => 'Buddhism']),
            'sinhala' => Subject::factory()->create(['name' => 'Sinhala']),
        ];
    }

    public function test_index_lists_paid_students_not_yet_enrolled(): void
    {
        $this->actingAsTeacher();
        $this->subjects();
        ClassModel::factory()->create(['name' => 'Class 1 (A)']);
        $this->fakeApi([$this->child()]);

        $response = $this->get(route('integration.index'));

        $response->assertStatus(200);
        $response->assertSee('Amara Perera');
        $response->assertSee('4321');
    }

    public function test_index_excludes_students_that_already_have_an_enrollment(): void
    {
        $this->actingAsTeacher();
        ['buddhism' => $buddhism] = $this->subjects();
        $class = ClassModel::factory()->create(['name' => 'Class 1 (A)']);
        Student::create(['student_number' => '4321', 'first_name' => 'Amara', 'last_name' => 'Perera']);
        Enrollment::create([
            'student_number' => '4321', 'subject_id' => $buddhism->id, 'class_id' => $class->id,
        ]);
        $this->fakeApi([$this->child()]);

        $response = $this->get(route('integration.index'));

        $response->assertStatus(200);
        $response->assertDontSee('Amara Perera');
        $response->assertSee('No paid students');
    }

    public function test_index_still_shows_a_paid_student_with_a_row_but_no_enrollment(): void
    {
        $this->actingAsTeacher();
        $this->subjects();
        ClassModel::factory()->create(['name' => 'Class 1 (A)']);
        // A bare students row with no enrollments — not properly enrolled.
        Student::create(['student_number' => '4321', 'first_name' => 'Amara', 'last_name' => 'Perera']);
        $this->fakeApi([$this->child()]);

        $response = $this->get(route('integration.index'));

        $response->assertStatus(200);
        $response->assertSee('Amara Perera');
    }

    public function test_index_shows_age_and_day_school_to_help_decide(): void
    {
        $this->actingAsTeacher();
        $this->subjects();
        ClassModel::factory()->create(['name' => 'Class 1 (A)']);
        $this->fakeApi([$this->child(['date_of_birth' => now()->subYears(9)->toDateString()])]);

        $response = $this->get(route('integration.index'));

        $response->assertStatus(200);
        $response->assertSee('9');                 // age
        $response->assertSee('Lyneham PS · 3');    // day school label (name · year)
    }

    public function test_index_shows_a_friendly_error_when_the_api_fails(): void
    {
        $this->actingAsTeacher();
        Http::fake(['https://registration.test/*' => Http::response('nope', 500)]);

        $response = $this->get(route('integration.index'));

        $response->assertStatus(200);
        $response->assertSee('Cou'); // "Couldn't load registration data."
    }

    public function test_index_prefills_the_class_when_registration_matches_a_local_class(): void
    {
        $this->actingAsTeacher();
        $this->subjects();
        $classA = ClassModel::factory()->create(['name' => 'Class 1 (A)']);
        $this->fakeApi([$this->child(['dhamma_class' => 'Class 1 (A)'])]);

        $response = $this->get(route('integration.index'));

        $response->assertStatus(200);
        // The matching class option is pre-selected in the dropdown.
        $response->assertSee('value="'.$classA->id.'" selected', false);
    }

    public function test_enroll_creates_the_student_and_the_chosen_enrollments(): void
    {
        $this->actingAsTeacher();
        ['buddhism' => $buddhism, 'sinhala' => $sinhala] = $this->subjects();
        $classA = ClassModel::factory()->create(['name' => 'Class 1 (A)']);
        $classC = ClassModel::factory()->create(['name' => 'Class 2 (C)']);
        $this->fakeApi([$this->child()]);

        $response = $this->post(route('integration.enroll'), [
            'class_for' => [
                '4321' => [
                    (string) $buddhism->id => (string) $classA->id,
                    (string) $sinhala->id => (string) $classC->id,
                ],
            ],
        ]);

        $response->assertRedirect(route('integration.index'));
        $this->assertDatabaseHas('students', [
            'student_number' => '4321',
            'first_name' => 'Amara',
            'last_name' => 'Perera',
        ]);
        $this->assertDatabaseHas('enrollments', [
            'student_number' => '4321', 'subject_id' => $buddhism->id, 'class_id' => $classA->id,
        ]);
        $this->assertDatabaseHas('enrollments', [
            'student_number' => '4321', 'subject_id' => $sinhala->id, 'class_id' => $classC->id,
        ]);
        $this->assertSame(2, Enrollment::where('student_number', '4321')->count());
    }

    public function test_enroll_can_pick_a_single_subject(): void
    {
        $this->actingAsTeacher();
        ['buddhism' => $buddhism, 'sinhala' => $sinhala] = $this->subjects();
        $classA = ClassModel::factory()->create(['name' => 'Class 1 (A)']);
        $this->fakeApi([$this->child()]);

        $this->post(route('integration.enroll'), [
            'class_for' => [
                '4321' => [
                    (string) $buddhism->id => (string) $classA->id,
                    (string) $sinhala->id => '', // don't enrol
                ],
            ],
        ]);

        $this->assertSame(1, Enrollment::where('student_number', '4321')->count());
        $this->assertDatabaseHas('enrollments', [
            'student_number' => '4321', 'subject_id' => $buddhism->id, 'class_id' => $classA->id,
        ]);
    }

    public function test_enroll_skips_a_student_with_no_class_chosen(): void
    {
        $this->actingAsTeacher();
        ['buddhism' => $buddhism, 'sinhala' => $sinhala] = $this->subjects();
        ClassModel::factory()->create(['name' => 'Class 1 (A)']);
        $this->fakeApi([$this->child()]);

        $this->post(route('integration.enroll'), [
            'class_for' => [
                '4321' => [
                    (string) $buddhism->id => '',
                    (string) $sinhala->id => '',
                ],
            ],
        ]);

        $this->assertDatabaseMissing('students', ['student_number' => '4321']);
        $this->assertSame(0, Enrollment::count());
    }

    public function test_enroll_ignores_a_number_that_is_not_in_the_paid_set(): void
    {
        $this->actingAsTeacher();
        ['buddhism' => $buddhism] = $this->subjects();
        $classA = ClassModel::factory()->create(['name' => 'Class 1 (A)']);
        $this->fakeApi([$this->child(['student_number' => '4321'])]);

        $this->post(route('integration.enroll'), [
            'class_for' => [
                '9999' => [(string) $buddhism->id => (string) $classA->id],
            ],
        ]);

        $this->assertDatabaseMissing('students', ['student_number' => '9999']);
        $this->assertSame(0, Enrollment::count());
    }

    public function test_enroll_ignores_a_subject_that_is_not_one_of_the_mapped_ones(): void
    {
        $this->actingAsTeacher();
        $this->subjects();
        $art = Subject::factory()->create(['name' => 'Art']); // not a mapped subject
        $classA = ClassModel::factory()->create(['name' => 'Class 1 (A)']);
        $this->fakeApi([$this->child()]);

        $this->post(route('integration.enroll'), [
            'class_for' => [
                '4321' => [(string) $art->id => (string) $classA->id],
            ],
        ]);

        // Art isn't allowed, so nothing is enrolled and no student is created.
        $this->assertDatabaseMissing('students', ['student_number' => '4321']);
        $this->assertSame(0, Enrollment::count());
    }
}
