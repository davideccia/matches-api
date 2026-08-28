<?php

namespace Tests\Feature;

use Tests\TestCase;

class HorizonTest extends TestCase
{
    private const USERNAME = 'horizon-user';

    private const PASSWORD = 'horizon-secret';

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
            'horizon.basic_auth_username' => self::USERNAME,
            'horizon.basic_auth_password' => self::PASSWORD,
        ]);
    }

    public function test_dashboard_is_unauthorized_without_credentials(): void
    {
        $response = $this->get('/horizon');

        $response->assertUnauthorized();
        $response->assertHeader('WWW-Authenticate', 'Basic realm="Horizon"');
    }

    public function test_dashboard_is_unauthorized_with_wrong_password(): void
    {
        $response = $this->get('/horizon', $this->basicAuthHeader(self::USERNAME, 'wrong-password'));

        $response->assertUnauthorized();
    }

    public function test_dashboard_is_unauthorized_with_wrong_username(): void
    {
        $response = $this->get('/horizon', $this->basicAuthHeader('wrong-user', self::PASSWORD));

        $response->assertUnauthorized();
    }

    public function test_dashboard_is_accessible_with_valid_credentials(): void
    {
        $response = $this->get('/horizon', $this->basicAuthHeader(self::USERNAME, self::PASSWORD));

        $response->assertOk();
    }

    public function test_dashboard_is_unauthorized_when_credentials_are_not_configured(): void
    {
        config([
            'horizon.basic_auth_username' => null,
            'horizon.basic_auth_password' => null,
        ]);

        $response = $this->get('/horizon', $this->basicAuthHeader(self::USERNAME, self::PASSWORD));

        $response->assertUnauthorized();
    }
}
