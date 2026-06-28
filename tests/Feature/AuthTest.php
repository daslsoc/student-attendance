<?php

namespace Tests\Feature;

use App\Mail\LoginLinkMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_form_is_accessible(): void
    {
        $response = $this->get(route('login.form'));

        $response->assertStatus(200);
        $response->assertSee('Teacher Login');
    }

    public function test_send_login_link_emails_a_known_teacher_and_stores_a_token(): void
    {
        Mail::fake();
        $teacher = User::factory()->create(['email' => 'teacher@example.com']);

        $response = $this->post(route('login.send'), ['email' => 'teacher@example.com']);

        $response->assertSessionHas('message');
        Mail::assertSent(LoginLinkMail::class);

        $teacher->refresh();
        $this->assertNotNull($teacher->login_token);
        $this->assertNotNull($teacher->login_token_expires_at);
    }

    public function test_requesting_a_second_link_reuses_the_same_valid_token(): void
    {
        Mail::fake();
        $teacher = User::factory()->create([
            'email' => 'teacher@example.com',
            'login_token' => 'still-valid',
            'login_token_expires_at' => now()->addHour(),
        ]);

        $this->post(route('login.send'), ['email' => 'teacher@example.com']);

        // The earlier email's link must stay valid, so the token is unchanged.
        $teacher->refresh();
        $this->assertSame('still-valid', $teacher->login_token);
        Mail::assertSent(LoginLinkMail::class);
    }

    public function test_an_expired_token_is_replaced_on_the_next_request(): void
    {
        Mail::fake();
        $teacher = User::factory()->create([
            'email' => 'teacher@example.com',
            'login_token' => 'stale-token',
            'login_token_expires_at' => now()->subHour(),
        ]);

        $this->post(route('login.send'), ['email' => 'teacher@example.com']);

        $teacher->refresh();
        $this->assertNotSame('stale-token', $teacher->login_token);
        $this->assertTrue($teacher->login_token_expires_at->isFuture());
    }

    public function test_send_login_link_rejects_an_unknown_email(): void
    {
        Mail::fake();

        $response = $this->post(route('login.send'), ['email' => 'nobody@example.com']);

        $response->assertSessionHasErrors();
        Mail::assertNothingSent();
    }

    public function test_valid_token_logs_the_teacher_in_and_redirects_to_selection(): void
    {
        $teacher = User::factory()->create([
            'login_token' => 'valid-token',
            'login_token_expires_at' => now()->addHour(),
        ]);

        $response = $this->get(route('login.token', ['token' => 'valid-token']));

        $response->assertRedirect(route('attendance.selection'));
        $this->assertTrue(session('teacher_logged_in'));
        $this->assertSame($teacher->id, session('teacher_id'));
    }

    public function test_login_regenerates_the_session_id(): void
    {
        User::factory()->create([
            'login_token' => 'valid-token',
            'login_token_expires_at' => now()->addHour(),
        ]);

        $this->startSession();
        $originalId = session()->getId();

        $this->get(route('login.token', ['token' => 'valid-token']));

        $this->assertNotSame($originalId, session()->getId());
        $this->assertTrue(session('teacher_logged_in'));
    }

    public function test_expired_token_is_rejected(): void
    {
        User::factory()->create([
            'login_token' => 'stale-token',
            'login_token_expires_at' => now()->subHour(),
        ]);

        $response = $this->get(route('login.token', ['token' => 'stale-token']));

        $response->assertRedirect('login');
        $response->assertSessionHasErrors();
        $this->assertNull(session('teacher_logged_in'));
    }

    public function test_protected_route_redirects_a_guest_to_the_login_form(): void
    {
        $response = $this->get(route('attendance.selection'));

        $response->assertRedirect(route('login.form'));
    }
}
