<?php

namespace Tests\Feature;

use Tests\TestCase;

class PulseTest extends TestCase
{
    private const USERNAME = 'pulse-user';

    private const PASSWORD = 'pulse-secret';

    /**
     * @return array<string, string>
     */
    private function basicAuthHeader(string $username, string $password): array
    {
        return ['Authorization' => 'Basic '.base64_encode($username.':'.$password)];
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'pulse.basic_auth_username' => self::USERNAME,
            'pulse.basic_auth_password' => self::PASSWORD,
        ]);
    }

    public function test_dashboard_is_unauthorized_without_credentials(): void
    {
        $response = $this->get('/pulse');

        $response->assertUnauthorized();
        $response->assertHeader('WWW-Authenticate', 'Basic realm="Pulse"');
    }

    public function test_dashboard_is_unauthorized_with_wrong_password(): void
    {
        $response = $this->get('/pulse', $this->basicAuthHeader(self::USERNAME, 'wrong-password'));

        $response->assertUnauthorized();
    }

    public function test_dashboard_is_unauthorized_with_wrong_username(): void
    {
        $response = $this->get('/pulse', $this->basicAuthHeader('wrong-user', self::PASSWORD));

        $response->assertUnauthorized();
    }

    public function test_dashboard_is_accessible_with_valid_credentials(): void
    {
        $response = $this->get('/pulse', $this->basicAuthHeader(self::USERNAME, self::PASSWORD));

        $response->assertOk();
    }

    public function test_dashboard_is_unauthorized_when_credentials_are_not_configured(): void
    {
        config([
            'pulse.basic_auth_username' => null,
            'pulse.basic_auth_password' => null,
        ]);

        $response = $this->get('/pulse', $this->basicAuthHeader(self::USERNAME, self::PASSWORD));

        $response->assertUnauthorized();
    }
}
