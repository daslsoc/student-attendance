<?php

namespace Tests\Unit;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_a_student_keyed_by_student_number(): void
    {
        Student::create([
            'student_number' => 'S002',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        $this->assertDatabaseHas('students', ['student_number' => 'S002']);
        $this->assertSame('S002', Student::first()->getKey());
    }
}
