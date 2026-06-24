<?php

namespace Tests\Feature;

use App\Support\TemporaryFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TemporaryUploadControllerTest extends TestCase
{
    public function test_store_uploads_file_and_caches_temporary_file(): void
    {
        Storage::fake('local');

        $this->authenticate();

        $file = UploadedFile::fake()->image('cover.jpg');

        $response = $this->postJson('/api/admin/temporary_uploads', [
            'file' => $file,
        ]);

        // A POST returning a plain JsonResource yields 200, not 201.
        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'original_name', 'mime_type', 'size'],
            ])
            ->assertJsonPath('data.original_name', 'cover.jpg');

        $id = $response->json('data.id');

        $this->assertNotNull($id);

        // Cache entry created by the action.
        $this->assertTrue(Cache::has($id));
        $this->assertInstanceOf(TemporaryFile::class, Cache::get($id));

        // File stored on the local disk at the temp path.
        $temporaryFile = Cache::get($id);
        Storage::disk('local')->assertExists($temporaryFile->path);
        $this->assertStringStartsWith('temp/', $temporaryFile->path);
    }

    public function test_store_requires_a_file(): void
    {
        Storage::fake('local');

        $this->authenticate();

        $this->postJson('/api/admin/temporary_uploads', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_store_rejects_oversized_file(): void
    {
        Storage::fake('local');

        $this->authenticate();

        // Rule allows max:10240 KB; 20000 KB exceeds it.
        $file = UploadedFile::fake()->create('big.pdf', 20000, 'application/pdf');

        $this->postJson('/api/admin/temporary_uploads', [
            'file' => $file,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_store_rejects_non_file_value(): void
    {
        Storage::fake('local');

        $this->authenticate();

        $this->postJson('/api/admin/temporary_uploads', [
            'file' => 'not-a-file',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_store_requires_authentication(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->image('cover.jpg');

        $this->postJson('/api/admin/temporary_uploads', [
            'file' => $file,
        ])->assertUnauthorized();
    }
}
