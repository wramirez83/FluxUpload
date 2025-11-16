<?php

namespace Medusa\FluxUpload\Tests\Unit;

use Medusa\FluxUpload\Tests\TestCase;
use Medusa\FluxUpload\Services\ChunkService;
use Medusa\FluxUpload\Models\UploadSession;
use Medusa\FluxUpload\Models\Chunk;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ChunkServiceTest extends TestCase
{
    protected ChunkService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ChunkService();
    }

    public function test_can_store_chunk(): void
    {
        $session = UploadSession::factory()->create([
            'total_chunks' => 2,
            'chunk_size' => 5242880,
        ]);

        $file = UploadedFile::fake()->create('chunk.txt', 1024);

        $chunk = $this->service->storeChunk($session, 0, $file);

        $this->assertInstanceOf(Chunk::class, $chunk);
        $this->assertEquals(0, $chunk->chunk_index);
        $this->assertFileExists($chunk->chunk_path);
        $this->assertEquals('uploading', $session->fresh()->status);
    }

    public function test_can_validate_chunk(): void
    {
        $session = UploadSession::factory()->create([
            'total_chunks' => 5,
            'chunk_size' => 5242880,
            'total_size' => 26214400, // 5 * 5242880
        ]);

        $valid = $this->service->validateChunk($session, 0, 5242880);
        $this->assertTrue($valid);

        $invalidIndex = $this->service->validateChunk($session, 10, 5242880);
        $this->assertFalse($invalidIndex);

        $invalidSize = $this->service->validateChunk($session, 0, 1000);
        $this->assertFalse($invalidSize);
    }

    public function test_handles_duplicate_chunk(): void
    {
        $session = UploadSession::factory()->create([
            'total_chunks' => 2,
            'chunk_size' => 5242880,
        ]);

        $file1 = UploadedFile::fake()->create('chunk.txt', 1024);
        $file2 = UploadedFile::fake()->create('chunk.txt', 1024);

        $chunk1 = $this->service->storeChunk($session, 0, $file1);
        $chunk2 = $this->service->storeChunk($session, 0, $file2);

        $this->assertEquals($chunk1->id, $chunk2->id);
    }

    public function test_can_get_chunks(): void
    {
        $session = UploadSession::factory()->create();

        Chunk::factory()->create([
            'session_id' => $session->id,
            'chunk_index' => 0,
        ]);

        Chunk::factory()->create([
            'session_id' => $session->id,
            'chunk_index' => 1,
        ]);

        $chunks = $this->service->getChunks($session);

        $this->assertCount(2, $chunks);
        $this->assertEquals(0, $chunks[0]['chunk_index']);
        $this->assertEquals(1, $chunks[1]['chunk_index']);
    }
}

