<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Student;
use App\Models\Subject;
use App\Models\ClassModel;
use App\Models\Attendance;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function attendance_form_requires_subject_and_class()
    {
        session(['teacher_logged_in' => true]);
        $response = $this->get(route('attendance.form'));
        $response->assertRedirect(route('dashboard'));
    }

    /** @test */
    public function attendance_can_be_submitted()
    {
        session(['teacher_logged_in' => true]);

        $subject = Subject::factory()->create();
        $class = ClassModel::factory()->create();

        Student::create([
            'student_number' => 'S001',
            'first_name'     => 'John',
            'last_name'      => 'Doe'
        ]);

        $response = $this->post(route('attendance.submit'), [
            'subject_id'      => $subject->id,
            'class_id'        => $class->id,
            'present_students'=> json_encode(['S001'])
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('attendance', [
            'subject_id'     => $subject->id,
            'class_id'       => $class->id,
            'student_number' => 'S001'
        ]);
    }
}
