<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    private function assertSecurityHeaders(mixed $response): void
    {
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_admin_responses_carry_the_security_headers(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertOk();
        $this->assertSecurityHeaders($response);
    }

    public function test_public_responses_carry_the_security_headers(): void
    {
        $response = $this->getJson('/api/public/registration_form/tournaments');

        $response->assertOk();
        $this->assertSecurityHeaders($response);
    }

    public function test_web_responses_carry_the_security_headers(): void
    {
        // The web group is what Horizon and Log Viewer run on: clickjacking on
        // those panels is the only real target these headers close. The headers
        // must land even on the basic-auth rejection, which is why this asserts
        // against a 401 rather than logging in.
        $response = $this->get('/horizon');

        $response->assertUnauthorized();

        $this->assertSecurityHeaders($response);
    }
}
