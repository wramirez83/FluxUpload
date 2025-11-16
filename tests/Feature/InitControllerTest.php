<?php

namespace Medusa\FluxUpload\Tests\Feature;

use Medusa\FluxUpload\Tests\TestCase;
use Medusa\FluxUpload\Models\UploadSession;

class InitControllerTest extends TestCase
{
    public function test_can_initialize_upload(): void
    {
        $response = $this->postJson('/fluxupload/init', [
            'filename' => 'test.txt',
            'total_size' => 10485760,
            'chunk_size' => 5242880,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'session_id',
                'total_chunks',
                'chunk_size',
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertNotNull($response->json('session_id'));
    }

    public function test_validates_required_fields(): void
    {
        $response = $this->postJson('/fluxupload/init', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['filename', 'total_size']);
    }

    public function test_validates_file_size_limit(): void
    {
        $maxSize = config('fluxupload.max_file_size');
        
        $response = $this->postJson('/fluxupload/init', [
            'filename' => 'huge.txt',
            'total_size' => $maxSize + 1,
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_can_resume_existing_session(): void
    {
        $session = UploadSession::factory()->create([
            'status' => 'uploading',
            'expires_at' => now()->addHour(),
        ]);

        $response = $this->postJson('/fluxupload/init', [
            'filename' => $session->original_filename,
            'total_size' => $session->total_size,
            'session_id' => $session->session_id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'resumed' => true,
            ]);
    }
}

