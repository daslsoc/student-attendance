<?php

namespace Tests\Browser;

use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class StudentSelectorTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * End-to-end proof that the Vite bundle loads and the student-selector
     * toggle works in a real browser (Vitest covers the module in isolation;
     * this covers it wired into the actual page + bundle).
     */
    public function test_tapping_a_student_toggles_them_and_updates_the_hidden_input(): void
    {
        $subject = Subject::factory()->create();
        $class = ClassModel::factory()->create();
        $student = Student::create([
            'student_number' => 'S001',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
        Enrollment::create([
            'student_number' => $student->student_number,
            'subject_id' => $subject->id,
            'class_id' => $class->id,
        ]);

        // Log in by following a valid magic link (the app's real auth path).
        User::factory()->create([
            'login_token' => 'dusk-token',
            'login_token_expires_at' => now()->addHour(),
        ]);

        $this->browse(function (Browser $browser) use ($subject, $class) {
            $browser->visit('/login/dusk-token')
                ->assertPathIs('/attendance-selection')
                ->visit("/attendance?subject_id={$subject->id}&class_id={$class->id}")
                ->assertSee('Mark Attendance')
                ->assertPresent('button[data-student="S001"].btn-outline-primary')
                ->click('button[data-student="S001"]')
                ->waitFor('button[data-student="S001"].btn-success')
                ->assertPresent('button[data-student="S001"].btn-success')
                ->assertScript("JSON.parse(document.getElementById('present_students').value).includes('S001')", true);
        });
    }
}
