<?php

namespace Tests\Unit;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
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

    public function test_the_audit_log_records_the_number_but_not_the_name(): void
    {
        Log::spy();

        Student::create([
            'student_number' => 'S003',
            'first_name' => 'Secret',
            'last_name' => 'Name',
        ]);

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context = []) {
            return $message === 'Student created'
                && ($context['student_number'] ?? null) === 'S003'
                && ! array_key_exists('first_name', $context)
                && ! array_key_exists('last_name', $context);
        });
    }
}
