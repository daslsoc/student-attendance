<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Student;

class StudentTest extends TestCase
{
    /** @test */
    public function it_can_create_a_student()
    {
        $student = Student::create([
            'student_number' => 'S002',
            'first_name'     => 'Jane',
            'last_name'      => 'Doe'
        ]);

        $this->assertDatabaseHas('students', ['student_number' => 'S002']);
    }
}
