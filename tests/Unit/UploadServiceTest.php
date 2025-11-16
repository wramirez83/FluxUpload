<?php

namespace Medusa\FluxUpload\Tests\Unit;

use Medusa\FluxUpload\Tests\TestCase;
use Medusa\FluxUpload\Services\UploadService;
use Medusa\FluxUpload\Services\ChunkService;
use Medusa\FluxUpload\Models\UploadSession;
use Medusa\FluxUpload\Models\Chunk;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Event;
use Medusa\FluxUpload\Events\FluxUploadCompleted;
use Medusa\FluxUpload\Events\FluxUploadFailed;

class UploadServiceTest extends TestCase
{
    protected UploadService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->service = new UploadService();
    }

    public function test_can_assemble_file(): void
    {
        Event::fake();

        $session = UploadSession::factory()->create([
            'total_chunks' => 2,
            'chunk_size' => 1024,
            'total_size' => 2048,
        ]);

        // Create chunk files
        $chunk1Path = storage_path('app/fluxupload/chunks/' . $session->session_id . '/0');
        $chunk2Path = storage_path('app/fluxupload/chunks/' . $session->session_id . '/1');
        
        @mkdir(dirname($chunk1Path), 0755, true);
        file_put_contents($chunk1Path, str_repeat('A', 1024));
        file_put_contents($chunk2Path, str_repeat('B', 1024));

        Chunk::factory()->create([
            'session_id' => $session->id,
            'chunk_index' => 0,
            'chunk_path' => $chunk1Path,
            'chunk_size' => 1024,
        ]);

        Chunk::factory()->create([
            'session_id' => $session->id,
            'chunk_index' => 1,
            'chunk_path' => $chunk2Path,
            'chunk_size' => 1024,
        ]);

        $result = $this->service->assembleFile($session);

        $this->assertTrue($result);
        $session->refresh();
        $this->assertEquals('completed', $session->status);
        $this->assertNotNull($session->storage_path);

        Event::assertDispatched(FluxUploadCompleted::class);
    }

    public function test_fails_when_chunks_missing(): void
    {
        Event::fake();

        $session = UploadSession::factory()->create([
            'total_chunks' => 2,
        ]);

        // Only create one chunk
        Chunk::factory()->create([
            'session_id' => $session->id,
            'chunk_index' => 0,
        ]);

        $result = $this->service->assembleFile($session);

        $this->assertFalse($result);
        $session->refresh();
        $this->assertEquals('failed', $session->status);

        Event::assertDispatched(FluxUploadFailed::class);
    }
}

