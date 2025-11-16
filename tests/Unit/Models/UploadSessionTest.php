<?php

namespace Wramirez83\FluxUpload\Tests\Unit\Models;

use Wramirez83\FluxUpload\Tests\TestCase;
use Wramirez83\FluxUpload\Models\UploadSession;
use Wramirez83\FluxUpload\Models\Chunk;
use Carbon\Carbon;

class UploadSessionTest extends TestCase
{
    public function test_can_check_expiration(): void
    {
        $expired = UploadSession::factory()->create([
            'expires_at' => Carbon::now()->subHour(),
        ]);

        $active = UploadSession::factory()->create([
            'expires_at' => Carbon::now()->addHour(),
        ]);

        $this->assertTrue($expired->isExpired());
        $this->assertFalse($active->isExpired());
    }

    public function test_can_get_missing_chunks(): void
    {
        $session = UploadSession::factory()->create([
            'total_chunks' => 5,
        ]);

        Chunk::factory()->create([
            'session_id' => $session->id,
            'chunk_index' => 0,
        ]);

        Chunk::factory()->create([
            'session_id' => $session->id,
            'chunk_index' => 2,
        ]);

        $missing = $session->getMissingChunkIndices();

        $this->assertEquals([1, 3, 4], $missing);
    }

    public function test_can_check_completion(): void
    {
        $session = UploadSession::factory()->create([
            'total_chunks' => 3,
        ]);

        $this->assertFalse($session->isComplete());

        Chunk::factory()->create([
            'session_id' => $session->id,
            'chunk_index' => 0,
        ]);

        Chunk::factory()->create([
            'session_id' => $session->id,
            'chunk_index' => 1,
        ]);

        Chunk::factory()->create([
            'session_id' => $session->id,
            'chunk_index' => 2,
        ]);

        $this->assertTrue($session->fresh()->isComplete());
    }

    public function test_can_calculate_progress(): void
    {
        $session = UploadSession::factory()->create([
            'total_chunks' => 4,
        ]);

        $this->assertEquals(0, $session->getProgressPercentage());

        Chunk::factory()->create([
            'session_id' => $session->id,
            'chunk_index' => 0,
        ]);

        Chunk::factory()->create([
            'session_id' => $session->id,
            'chunk_index' => 1,
        ]);

        $this->assertEquals(50.0, $session->fresh()->getProgressPercentage());
    }
}

