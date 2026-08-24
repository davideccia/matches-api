<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * This is an API with no welcome page: the framework health endpoint
     * (registered via withRouting(health: '/up') in bootstrap/app.php) is the
     * smoke test for "the application boots and responds".
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/up');

        $response->assertStatus(200);
    }

    public function test_the_root_path_is_not_routed(): void
    {
        $response = $this->get('/');

        $response->assertNotFound();
    }
}
