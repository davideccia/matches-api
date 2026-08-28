<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    // ---------------------------------------------------------------------
    // index
    // ---------------------------------------------------------------------

    public function test_index_returns_users_with_resource_shape_and_hides_sensitive_fields(): void
    {
        // authenticate() creates one user; create one more so we have at least two.
        $this->authenticate();
        User::factory()->create();

        $response = $this->getJson('/api/admin/users');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    ['id', 'username', 'email', 'superadmin'],
                ],
            ]);

        // password / remember_token must never be exposed.
        $response->assertJsonMissing(['password' => true]);
        foreach ($response->json('data') as $user) {
            $this->assertArrayNotHasKey('password', $user);
            $this->assertArrayNotHasKey('remember_token', $user);
        }
    }

    public function test_index_search_is_a_no_op_and_returns_all_users(): void
    {
        // The User::search scope returns the builder unchanged, so a search term
        // must not filter anything out.
        $this->authenticate();
        User::factory()->count(2)->create();

        $this->getJson('/api/admin/users?search=anything-here')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_index_supports_pagination(): void
    {
        $this->authenticate();
        User::factory()->count(3)->create();

        // 4 users total (authenticate + 3); per_page=2 returns 2 on the first page.
        $this->getJson('/api/admin/users?paginate=1&per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/admin/users')->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // store
    // ---------------------------------------------------------------------

    public function test_store_creates_user_and_hashes_password(): void
    {
        $this->authenticate(User::factory()->superadmin()->create());

        $response = $this->postJson('/api/admin/users', [
            'username' => 'johndoe',
            'email' => 'john@example.com',
            'password' => 'secret-password',
            'superadmin' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.username', 'johndoe')
            ->assertJsonPath('data.email', 'john@example.com')
            ->assertJsonPath('data.superadmin', true);

        // Password is never returned.
        $this->assertArrayNotHasKey('password', $response->json('data'));

        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);

        // Stored password is hashed, not plaintext.
        $user = User::where('email', 'john@example.com')->firstOrFail();
        $this->assertNotSame('secret-password', $user->password);
        $this->assertTrue(Hash::check('secret-password', $user->password));
    }

    public function test_store_rejects_a_password_shorter_than_twelve_characters(): void
    {
        $this->authenticate(User::factory()->superadmin()->create());

        // 11 characters: passed under the old 8-character floor, must not now.
        $this->postJson('/api/admin/users', [
            'username' => 'shortpass',
            'email' => 'shortpass@example.com',
            'password' => 'elevenchars',
        ])->assertJsonValidationErrors(['password']);

        $this->assertDatabaseMissing('users', ['email' => 'shortpass@example.com']);
    }

    public function test_update_rejects_a_password_shorter_than_twelve_characters(): void
    {
        $user = User::factory()->superadmin()->create();
        $this->authenticate($user);

        $this->patchJson("/api/admin/users/{$user->id}", [
            'password' => 'elevenchars',
        ])->assertJsonValidationErrors(['password']);
    }

    public function test_store_allows_omitting_optional_superadmin(): void
    {
        // superadmin is optional and has no DB default, so it stays null when omitted.
        $this->authenticate(User::factory()->superadmin()->create());

        $this->postJson('/api/admin/users', [
            'username' => 'plainuser',
            'email' => 'plain@example.com',
            'password' => 'secret-password',
        ])
            ->assertCreated()
            ->assertJsonPath('data.superadmin', null);

        $this->assertDatabaseHas('users', ['email' => 'plain@example.com']);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->authenticate(User::factory()->superadmin()->create());

        $this->postJson('/api/admin/users', [])
            ->assertJsonValidationErrors(['username', 'email', 'password']);
    }

    public function test_store_rejects_short_password(): void
    {
        $this->authenticate(User::factory()->superadmin()->create());

        $this->postJson('/api/admin/users', [
            'username' => 'shortpw',
            'email' => 'shortpw@example.com',
            'password' => 'short',
        ])->assertJsonValidationErrors(['password']);
    }

    public function test_store_rejects_invalid_email_format(): void
    {
        $this->authenticate(User::factory()->superadmin()->create());

        $this->postJson('/api/admin/users', [
            'username' => 'bademail',
            'email' => 'not-an-email',
            'password' => 'secret-password',
        ])->assertJsonValidationErrors(['email']);
    }

    public function test_store_rejects_duplicate_username(): void
    {
        $this->authenticate(User::factory()->superadmin()->create());
        $existing = User::factory()->create(['username' => 'taken']);

        $this->postJson('/api/admin/users', [
            'username' => 'taken',
            'email' => 'fresh@example.com',
            'password' => 'secret-password',
        ])->assertJsonValidationErrors(['username']);
    }

    public function test_store_rejects_duplicate_email(): void
    {
        $this->authenticate(User::factory()->superadmin()->create());
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/admin/users', [
            'username' => 'freshname',
            'email' => 'taken@example.com',
            'password' => 'secret-password',
        ])->assertJsonValidationErrors(['email']);
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/admin/users', [])->assertUnauthorized();
    }

    public function test_store_returns_forbidden_for_non_superadmin(): void
    {
        $this->authenticate();

        $this->postJson('/api/admin/users', [
            'username' => 'johndoe',
            'email' => 'john@example.com',
            'password' => 'secret-password',
        ])->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // show
    // ---------------------------------------------------------------------

    public function test_show_returns_user_without_sensitive_fields(): void
    {
        $this->authenticate();
        $user = User::factory()->create();

        $response = $this->getJson("/api/admin/users/{$user->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.username', $user->username)
            ->assertJsonPath('data.email', $user->email);

        $this->assertArrayNotHasKey('password', $response->json('data'));
        $this->assertArrayNotHasKey('remember_token', $response->json('data'));
    }

    public function test_show_requires_authentication(): void
    {
        $user = User::factory()->create();

        $this->getJson("/api/admin/users/{$user->id}")->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // update
    // ---------------------------------------------------------------------

    public function test_update_modifies_user(): void
    {
        $this->authenticate(User::factory()->superadmin()->create());
        $user = User::factory()->create(['superadmin' => false]);

        $this->putJson("/api/admin/users/{$user->id}", [
            'username' => 'updatedname',
            'email' => 'updated@example.com',
            'superadmin' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.username', 'updatedname')
            ->assertJsonPath('data.email', 'updated@example.com')
            ->assertJsonPath('data.superadmin', true);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'username' => 'updatedname',
            'email' => 'updated@example.com',
        ]);
    }

    public function test_update_rehashes_password_when_provided(): void
    {
        $this->authenticate(User::factory()->superadmin()->create());
        $user = User::factory()->create();

        $this->putJson("/api/admin/users/{$user->id}", [
            'password' => 'brand-new-password',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('brand-new-password', $user->password));
    }

    public function test_update_allows_keeping_same_email(): void
    {
        // The unique rule ignores the current user, so re-sending its own email passes.
        $this->authenticate(User::factory()->superadmin()->create());
        $user = User::factory()->create(['email' => 'self@example.com']);

        $this->putJson("/api/admin/users/{$user->id}", [
            'email' => 'self@example.com',
        ])->assertOk();
    }

    public function test_update_rejects_email_of_another_user(): void
    {
        $this->authenticate(User::factory()->superadmin()->create());
        $other = User::factory()->create(['email' => 'other@example.com']);
        $user = User::factory()->create();

        $this->putJson("/api/admin/users/{$user->id}", [
            'email' => 'other@example.com',
        ])->assertJsonValidationErrors(['email']);
    }

    public function test_update_allows_non_superadmin_to_update_themselves(): void
    {
        $user = $this->authenticate();

        $this->putJson("/api/admin/users/{$user->id}", [
            'username' => 'newname',
        ])->assertOk();
    }

    public function test_update_returns_forbidden_for_non_superadmin_updating_another_user(): void
    {
        $this->authenticate();
        $other = User::factory()->create();

        $this->putJson("/api/admin/users/{$other->id}", [
            'username' => 'hijacked',
        ])->assertForbidden();
    }

    public function test_update_requires_authentication(): void
    {
        $user = User::factory()->create();

        $this->putJson("/api/admin/users/{$user->id}", [])->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // destroy
    // ---------------------------------------------------------------------

    public function test_destroy_deletes_user(): void
    {
        $this->authenticate(User::factory()->superadmin()->create());
        $user = User::factory()->create();

        $this->deleteJson("/api/admin/users/{$user->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_destroy_prevents_superadmin_from_deleting_themselves(): void
    {
        $superadmin = $this->authenticate(User::factory()->superadmin()->create());

        $this->deleteJson("/api/admin/users/{$superadmin->id}")->assertForbidden();
    }

    public function test_destroy_returns_forbidden_for_non_superadmin(): void
    {
        $user = $this->authenticate();

        $this->deleteJson("/api/admin/users/{$user->id}")->assertForbidden();
    }

    public function test_destroy_requires_authentication(): void
    {
        $user = User::factory()->create();

        $this->deleteJson("/api/admin/users/{$user->id}")->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // bulkDestroy
    // ---------------------------------------------------------------------

    public function test_bulk_destroy_deletes_multiple_users(): void
    {
        $this->authenticate(User::factory()->superadmin()->create());

        $users = User::factory()->count(3)->create();
        $ids = $users->pluck('id')->all();

        $this->deleteJson('/api/admin/users/bulk', ['ids' => $ids])
            ->assertNoContent();

        foreach ($ids as $id) {
            $this->assertDatabaseMissing('users', ['id' => $id]);
        }
    }

    public function test_bulk_destroy_prevents_superadmin_from_deleting_themselves(): void
    {
        $superadmin = $this->authenticate(User::factory()->superadmin()->create());
        $other = User::factory()->create();

        $this->deleteJson('/api/admin/users/bulk', [
            'ids' => [$superadmin->id, $other->id],
        ])->assertJsonValidationErrors(['ids.0']);
    }

    public function test_bulk_destroy_validates_missing_ids(): void
    {
        $this->authenticate(User::factory()->superadmin()->create());

        $this->deleteJson('/api/admin/users/bulk', [])
            ->assertJsonValidationErrors(['ids']);
    }

    public function test_bulk_destroy_validates_nonexistent_ids(): void
    {
        $this->authenticate(User::factory()->superadmin()->create());

        $this->deleteJson('/api/admin/users/bulk', [
            'ids' => ['00000000-0000-0000-0000-000000000000'],
        ])->assertJsonValidationErrors(['ids.0']);
    }

    public function test_bulk_destroy_returns_forbidden_for_non_superadmin(): void
    {
        $this->authenticate();

        $this->deleteJson('/api/admin/users/bulk', ['ids' => []])->assertForbidden();
    }

    public function test_bulk_destroy_requires_authentication(): void
    {
        $this->deleteJson('/api/admin/users/bulk', ['ids' => []])->assertUnauthorized();
    }
}
