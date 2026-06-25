<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class LoginTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_login_page_renders(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->assertSee('Teacher Login');
        });
    }

    public function test_requesting_a_link_for_a_known_teacher_shows_a_confirmation(): void
    {
        User::factory()->create(['email' => 'teacher@example.com']);

        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->type('email', 'teacher@example.com')
                ->press('Send Login Link')
                ->waitForText('A login link has been sent')
                ->assertSee('A login link has been sent');
        });
    }
}
