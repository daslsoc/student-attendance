<?php

namespace Tests\Feature;

use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportAllocationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'integration.subject_for_dhamma' => 'Buddhism',
            'integration.subject_for_sinhala' => 'Sinhala',
        ]);
    }

    public function test_it_emits_update_sql_from_current_enrollments(): void
    {
        $buddhism = Subject::factory()->create(['name' => 'Buddhism']);
        $sinhala = Subject::factory()->create(['name' => 'Sinhala']);
        $classC = ClassModel::factory()->create(['name' => 'Class C']);
        $classD = ClassModel::factory()->create(['name' => 'Class D']);

        Student::create(['student_number' => '4321', 'first_name' => 'Amara', 'last_name' => 'Perera']);
        Enrollment::create(['student_number' => '4321', 'subject_id' => $buddhism->id, 'class_id' => $classC->id]);
        Enrollment::create(['student_number' => '4321', 'subject_id' => $sinhala->id, 'class_id' => $classD->id]);

        $this->artisan('integration:export-allocations')
            ->expectsOutputToContain("UPDATE children SET allocated_dhamma_class = 'Class C', allocated_sinhala_class = 'Class D' WHERE student_number = '4321';")
            ->assertExitCode(0);
    }

    public function test_it_skips_students_with_no_enrollments(): void
    {
        Subject::factory()->create(['name' => 'Buddhism']);
        Subject::factory()->create(['name' => 'Sinhala']);
        Student::create(['student_number' => '5000', 'first_name' => 'No', 'last_name' => 'Class']);

        $this->artisan('integration:export-allocations')
            ->doesntExpectOutputToContain('5000')
            ->assertExitCode(0);
    }
}
