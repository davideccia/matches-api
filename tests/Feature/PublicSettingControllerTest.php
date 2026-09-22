<?php

namespace Tests\Feature;

use App\Support\AppLogo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicSettingControllerTest extends TestCase
{
    public function test_logo_returns_not_found_when_no_logo_uploaded(): void
    {
        Storage::fake('public');

        $this->get('/api/public/settings/logo')->assertNotFound();
    }

    public function test_logo_returns_image_content_without_authentication(): void
    {
        Storage::fake('public');

        AppLogo::store(UploadedFile::fake()->image('logo.png'));

        $response = $this->get('/api/public/settings/logo');

        $response->assertOk();
        $this->assertStringStartsWith('image/', $response->headers->get('Content-Type'));
    }
}
