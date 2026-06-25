<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Subject;
use App\Models\ClassModel;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function dashboard_displays_subjects_and_classes()
    {
        Subject::factory()->create(['name' => 'Math']);
        ClassModel::factory()->create(['name' => 'Class A']);
        session(['teacher_logged_in' => true]);
        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Math');
        $response->assertSee('Class A');
    }
}
