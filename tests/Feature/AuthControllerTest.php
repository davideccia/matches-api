<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    // ---------------------------------------------------------------------
    // login
    // ---------------------------------------------------------------------

    public function test_login_returns_token_and_user_with_valid_credentials(): void
    {
        // Factory default password is 'password'.
        $user = User::factory()->create(['email' => 'login@example.com']);

        $response = $this->postJson('/api/admin/auth/login', [
            'email' => 'login@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'username', 'email', 'superadmin'],
            ])
            ->assertJsonPath('user.id', $user->id);

        $this->assertNotEmpty($response->json('token'));
        // The user payload must not leak the password.
        $this->assertArrayNotHasKey('password', $response->json('user'));
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'login@example.com']);

        $this->postJson('/api/admin/auth/login', [
            'email' => 'login@example.com',
            'password' => 'wrong-password',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_login_validates_required_fields(): void
    {
        $this->postJson('/api/admin/auth/login', [])
            ->assertJsonValidationErrors(['email', 'password']);
    }

    // ---------------------------------------------------------------------
    // user
    // ---------------------------------------------------------------------

    public function test_user_returns_current_authenticated_user(): void
    {
        $user = $this->authenticate();

        $this->getJson('/api/admin/auth/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_user_requires_authentication(): void
    {
        $this->getJson('/api/admin/auth/user')->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // logout
    // ---------------------------------------------------------------------

    public function test_logout_deletes_the_current_access_token(): void
    {
        // Use a real issued token so currentAccessToken()->delete() removes a DB row.
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/auth/logout')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/admin/auth/logout')->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // forgot_password
    // ---------------------------------------------------------------------

    public function test_forgot_password_sends_reset_notification_for_existing_email(): void
    {
        $user = User::factory()->create(['email' => 'forgot@example.com']);

        Notification::fake();

        $this->postJson('/api/admin/auth/forgot_password', [
            'email' => 'forgot@example.com',
        ])
            ->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_forgot_password_returns200_for_unknown_email_without_leaking_existence(): void
    {
        Notification::fake();

        $this->postJson('/api/admin/auth/forgot_password', [
            'email' => 'nobody@example.com',
        ])
            ->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertNothingSent();
    }

    public function test_forgot_password_validates_email(): void
    {
        $this->postJson('/api/admin/auth/forgot_password', [])
            ->assertJsonValidationErrors(['email']);

        $this->postJson('/api/admin/auth/forgot_password', ['email' => 'not-an-email'])
            ->assertJsonValidationErrors(['email']);
    }

    // ---------------------------------------------------------------------
    // reset_password
    // ---------------------------------------------------------------------

    public function test_reset_password_updates_password_and_revokes_tokens(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        // An existing token that should be revoked on a successful reset.
        $user->createToken('api');
        $token = Password::createToken($user);

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->postJson('/api/admin/auth/reset_password', [
            'email' => 'reset@example.com',
            'password' => 'fresh-password',
            'password_confirmation' => 'fresh-password',
            'token' => $token,
        ])
            ->assertOk()
            ->assertJsonStructure(['message']);

        $user->refresh();
        $this->assertTrue(Hash::check('fresh-password', $user->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        User::factory()->create(['email' => 'reset@example.com']);

        $this->postJson('/api/admin/auth/reset_password', [
            'email' => 'reset@example.com',
            'password' => 'fresh-password',
            'password_confirmation' => 'fresh-password',
            'token' => 'totally-invalid-token',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    public function test_reset_password_validates_confirmation_and_length(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $token = Password::createToken($user);

        // Mismatched confirmation.
        $this->postJson('/api/admin/auth/reset_password', [
            'email' => 'reset@example.com',
            'password' => 'fresh-password',
            'password_confirmation' => 'different-password',
            'token' => $token,
        ])->assertJsonValidationErrors(['password']);

        // Too short.
        $this->postJson('/api/admin/auth/reset_password', [
            'email' => 'reset@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
            'token' => $token,
        ])->assertJsonValidationErrors(['password']);
    }

    public function test_reset_password_validates_required_fields(): void
    {
        $this->postJson('/api/admin/auth/reset_password', [])
            ->assertJsonValidationErrors(['email', 'password', 'token']);
    }
}
