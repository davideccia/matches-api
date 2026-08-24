<?php

namespace Tests\Feature;

use Tests\TestCase;

class LogViewerTest extends TestCase
{
    private const USERNAME = 'log-viewer-user';

    private const PASSWORD = 'log-viewer-secret';

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
            'log-viewer.basic_auth_username' => self::USERNAME,
            'log-viewer.basic_auth_password' => self::PASSWORD,
        ]);
    }

    // ---- ui ----

    public function test_index_is_unauthorized_without_credentials(): void
    {
        $response = $this->get('/log-viewer');

        $response->assertUnauthorized();
        $response->assertHeader('WWW-Authenticate', 'Basic realm="Log Viewer"');
    }

    public function test_index_is_unauthorized_with_wrong_credentials(): void
    {
        $response = $this->get('/log-viewer', $this->basicAuthHeader(self::USERNAME, 'wrong-password'));

        $response->assertUnauthorized();
    }

    public function test_index_is_accessible_with_valid_credentials(): void
    {
        $response = $this->get('/log-viewer', $this->basicAuthHeader(self::USERNAME, self::PASSWORD));

        $response->assertOk();
    }

    public function test_index_is_unauthorized_when_credentials_are_not_configured(): void
    {
        config([
            'log-viewer.basic_auth_username' => null,
            'log-viewer.basic_auth_password' => null,
        ]);

        $response = $this->get('/log-viewer', $this->basicAuthHeader(self::USERNAME, self::PASSWORD));

        $response->assertUnauthorized();
    }

    // ---- api ----

    public function test_api_is_unauthorized_without_credentials(): void
    {
        $response = $this->getJson('/log-viewer/api/folders');

        $response->assertUnauthorized();
    }

    public function test_api_is_accessible_with_valid_credentials(): void
    {
        $response = $this->getJson('/log-viewer/api/folders', $this->basicAuthHeader(self::USERNAME, self::PASSWORD));

        $response->assertOk();
    }
}
