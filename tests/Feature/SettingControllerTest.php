<?php

namespace Tests\Feature;

use App\Support\AppLogo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingControllerTest extends TestCase
{
    // ---- logo (show) ----

    public function test_logo_returns_not_found_when_no_logo_uploaded(): void
    {
        Storage::fake('public');

        $this->authenticate();

        $this->getJson('/api/admin/settings/logo')->assertNotFound();
    }

    public function test_logo_returns_image_content_when_logo_uploaded(): void
    {
        Storage::fake('public');

        $this->authenticate();

        AppLogo::store(UploadedFile::fake()->image('logo.png'));

        $response = $this->get('/api/admin/settings/logo');

        $response->assertOk();
        $this->assertStringStartsWith('image/', $response->headers->get('Content-Type'));
    }

    public function test_logo_requires_authentication(): void
    {
        $this->getJson('/api/admin/settings/logo')->assertUnauthorized();
    }

    // ---- logo (update) ----

    public function test_update_logo_stores_file(): void
    {
        Storage::fake('public');

        $this->authenticate();

        $file = UploadedFile::fake()->image('logo.png');

        $this->postJson('/api/admin/settings/logo', [
            'logo' => $file,
        ])->assertNoContent();

        Storage::disk('public')->assertExists('settings/logo.png');
    }

    public function test_update_logo_replaces_previous_file_with_different_extension(): void
    {
        Storage::fake('public');

        $this->authenticate();

        $this->postJson('/api/admin/settings/logo', [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertNoContent();

        Storage::disk('public')->assertExists('settings/logo.png');

        $this->postJson('/api/admin/settings/logo', [
            'logo' => UploadedFile::fake()->image('logo.webp'),
        ])->assertNoContent();

        Storage::disk('public')->assertMissing('settings/logo.png');
        Storage::disk('public')->assertExists('settings/logo.webp');
    }

    public function test_update_logo_rejects_invalid_mime_type(): void
    {
        Storage::fake('public');

        $this->authenticate();

        $this->postJson('/api/admin/settings/logo', [
            'logo' => UploadedFile::fake()->create('logo.txt', 10, 'text/plain'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('logo');
    }

    public function test_update_logo_requires_authentication(): void
    {
        Storage::fake('public');

        $this->postJson('/api/admin/settings/logo', [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertUnauthorized();
    }

    // ---- logo (destroy) ----

    public function test_destroy_logo_deletes_file(): void
    {
        Storage::fake('public');

        $this->authenticate();

        AppLogo::store(UploadedFile::fake()->image('logo.png'));

        $this->deleteJson('/api/admin/settings/logo')->assertNoContent();

        Storage::disk('public')->assertMissing('settings/logo.png');
    }

    public function test_destroy_logo_returns_not_found_when_no_logo_uploaded(): void
    {
        Storage::fake('public');

        $this->authenticate();

        $this->deleteJson('/api/admin/settings/logo')->assertNotFound();
    }

    public function test_destroy_logo_requires_authentication(): void
    {
        Storage::fake('public');

        $this->deleteJson('/api/admin/settings/logo')->assertUnauthorized();
    }
}
