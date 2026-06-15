<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function testLoginReturnsTokenAndUserWithValidCredentials(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        $response = $this->postJson('/api/admin/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user'])
            ->assertJsonPath('user.id', $user->id);
    }

    public function testLoginReturns401WithInvalidCredentials(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $this->postJson('/api/admin/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    public function testLoginReturns422WhenFieldsAreMissing(): void
    {
        $this->postJson('/api/admin/auth/login', [])->assertUnprocessable();
    }

    public function testUserReturnsAuthenticatedUser(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->getJson('/api/admin/auth/user', [
            'Authorization' => "Bearer {$token}",
        ])->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function testUserReturns401WithoutToken(): void
    {
        $this->getJson('/api/admin/auth/user')->assertUnauthorized();
    }

    public function testLogoutDeletesCurrentToken(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->postJson('/api/admin/auth/logout', [], [
            'Authorization' => "Bearer {$token}",
        ])->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function testLogoutReturns401WithoutToken(): void
    {
        $this->postJson('/api/admin/auth/logout')->assertUnauthorized();
    }
}
