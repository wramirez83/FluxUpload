<?php

namespace Wramirez83\FluxUpload\Tests\Feature;

use Wramirez83\FluxUpload\Tests\TestCase;
use Wramirez83\FluxUpload\Models\UploadSession;
use Wramirez83\FluxUpload\Models\Chunk;

class StatusControllerTest extends TestCase
{
    public function test_can_get_status(): void
    {
        $session = UploadSession::factory()->create();

        $response = $this->getJson("/fluxupload/status/{$session->session_id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'session_id',
                'status',
                'uploaded_chunks',
                'total_chunks',
                'progress',
            ]);
    }

    public function test_returns_404_for_invalid_session(): void
    {
        $response = $this->getJson('/fluxupload/status/invalid-session');

        $response->assertStatus(404);
    }

    public function test_shows_correct_progress(): void
    {
        $session = UploadSession::factory()->create([
            'total_chunks' => 4,
        ]);

        Chunk::factory()->create([
            'session_id' => $session->id,
            'chunk_index' => 0,
        ]);

        Chunk::factory()->create([
            'session_id' => $session->id,
            'chunk_index' => 1,
        ]);

        $response = $this->getJson("/fluxupload/status/{$session->session_id}");

        $response->assertStatus(200)
            ->assertJson([
                'uploaded_chunks' => 2,
                'total_chunks' => 4,
                'progress' => 50.0,
            ]);
    }
}

