<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('help'))->assertRedirect(route('login.form'));
    }

    public function test_the_help_page_renders_for_a_logged_in_teacher(): void
    {
        $this->actingAsTeacher();

        $response = $this->get(route('help'));

        $response->assertStatus(200);
        $response->assertSee('Help &amp; Guide', false);
        $response->assertSee('Marking attendance');
        // The screenshots the guide leans on are wired up.
        $response->assertSee('/images/help/mark-attendance.png', false);
    }
}
