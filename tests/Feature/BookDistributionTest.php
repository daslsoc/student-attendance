<?php

namespace Tests\Feature;

use App\Models\ClassModel;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookDistributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_requires_a_subject_and_class(): void
    {
        $this->actingAsTeacher();

        $response = $this->get(route('book_distribution.form'));

        $response->assertRedirect(route('book_distribution.selection'));
    }

    public function test_book_distribution_can_be_recorded(): void
    {
        $teacher = $this->actingAsTeacher();
        $subject = Subject::factory()->create();
        $class = ClassModel::factory()->create();
        Student::create([
            'student_number' => 'S100',
            'first_name' => 'Alan',
            'last_name' => 'Turing',
        ]);

        $response = $this->post(route('book_distribution.submit'), [
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'present_students' => json_encode(['S100']),
        ]);

        $response->assertRedirect(route('book_distribution.selection'));
        $this->assertDatabaseHas('book_distributions', [
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'student_number' => 'S100',
            'teacher_id' => $teacher->id,
        ]);
    }
}
