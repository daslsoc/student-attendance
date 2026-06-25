<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_baseline_security_headers_are_present(): void
    {
        $response = $this->get(route('login.form'));

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');
    }

    public function test_hsts_is_only_sent_over_https(): void
    {
        $path = route('login.form', absolute: false);

        $this->get('http://localhost'.$path)
            ->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost'.$path)
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
