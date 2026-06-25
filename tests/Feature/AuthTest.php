<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Mail;
use App\Mail\LoginLinkMail;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function login_form_is_accessible()
    {
        $response = $this->get(route('login.form'));
        $response->assertStatus(200);
        $response->assertSee('Teacher Login');
    }

    /** @test */
    public function can_send_login_link()
    {
        Mail::fake();
        $response = $this->post(route('login.send'), ['email' => 'teacher@example.com']);
        $response->assertSessionHas('message');
        Mail::assertSent(LoginLinkMail::class);
    }

    /** @test */
    public function token_login_redirects_to_dashboard()
    {
        session(['login_token' => 'testtoken']);
        $response = $this->get(route('login.token', ['token' => 'testtoken']));
        $response->assertRedirect(route('dashboard'));
    }
}
