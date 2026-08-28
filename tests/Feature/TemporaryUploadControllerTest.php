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

        // Cache entry created by the action, under a key namespaced and scoped to the uploader.
        $this->assertTrue(Cache::has(TemporaryFile::cacheKey($id)));
        $this->assertFalse(Cache::has($id));

        // File stored on the local disk at the temp path.
        $temporaryFile = TemporaryFile::find($id);
        $this->assertInstanceOf(TemporaryFile::class, $temporaryFile);
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

    public function test_temporary_upload_cannot_be_consumed_by_another_user(): void
    {
        Storage::fake('local');

        $this->authenticate();

        $id = $this->postJson('/api/admin/temporary_uploads', [
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ])->json('data.id');

        // A different authenticated user must not be able to attach someone else's upload.
        $this->authenticate();

        $this->assertNull(TemporaryFile::find($id));

        $this->postJson('/api/admin/athletes', [
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'tax_number' => 'RSSMRA80A01H501U',
            'email' => 'mario.rossi@example.com',
            'birth_date' => '1980-01-01',
            'gender' => 'male',
            'photo' => $id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');
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
