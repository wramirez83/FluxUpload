<?php

namespace Medusa\FluxUpload\Tests\Feature;

use Medusa\FluxUpload\Tests\TestCase;
use Medusa\FluxUpload\Models\UploadSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ChunkControllerTest extends TestCase
{
    public function test_can_upload_chunk(): void
    {
        $session = UploadSession::factory()->create([
            'total_chunks' => 2,
            'chunk_size' => 1048576, // 1MB
            'total_size' => 2097152, // 2MB
        ]);

        // UploadedFile::fake()->create() uses kilobytes, so 1024 = 1MB
        $file = UploadedFile::fake()->create('chunk.txt', 1024); // 1MB in KB

        $response = $this->call('POST', '/fluxupload/chunk', [
            'session_id' => $session->session_id,
            'chunk_index' => 0,
        ], [], [
            'chunk' => $file,
        ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('fluxupload_chunks', [
            'session_id' => $session->id,
            'chunk_index' => 0,
        ]);
    }

    public function test_validates_chunk_data(): void
    {
        $response = $this->post('/fluxupload/chunk', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['session_id', 'chunk_index', 'chunk']);
    }

    public function test_rejects_invalid_session(): void
    {
        $file = UploadedFile::fake()->create('chunk.txt', 1024);

        $response = $this->call('POST', '/fluxupload/chunk', [
            'session_id' => 'invalid-session',
            'chunk_index' => 0,
        ], [], [
            'chunk' => $file,
        ]);

        $response->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    public function test_rejects_expired_session(): void
    {
        $session = UploadSession::factory()->create([
            'expires_at' => now()->subHour(),
        ]);

        $file = UploadedFile::fake()->create('chunk.txt', 1024);

        $response = $this->call('POST', '/fluxupload/chunk', [
            'session_id' => $session->session_id,
            'chunk_index' => 0,
        ], [], [
            'chunk' => $file,
        ]);

        $response->assertStatus(410);
    }
}

