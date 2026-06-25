<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The root path redirects unauthenticated visitors to the login form.
     */
    public function test_the_root_redirects_to_the_login_form(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login.form'));
    }
}
