<?php

namespace Wramirez83\FluxUpload\Tests\Unit;

use Wramirez83\FluxUpload\Tests\TestCase;
use Wramirez83\FluxUpload\Services\SessionService;
use Wramirez83\FluxUpload\Models\UploadSession;
use Carbon\Carbon;

class SessionServiceTest extends TestCase
{
    protected SessionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SessionService();
    }

    public function test_can_create_session(): void
    {
        $data = [
            'original_filename' => 'test.txt',
            'total_size' => 10485760, // 10MB
            'total_chunks' => 2,
            'chunk_size' => 5242880,
        ];

        $session = $this->service->createSession($data);

        $this->assertInstanceOf(UploadSession::class, $session);
        $this->assertNotNull($session->session_id);
        $this->assertEquals('test.txt', $session->original_filename);
        $this->assertEquals(10485760, $session->total_size);
        $this->assertEquals(2, $session->total_chunks);
        $this->assertEquals('pending', $session->status);
        $this->assertFalse($session->isExpired());
    }

    public function test_can_find_session(): void
    {
        $session = UploadSession::factory()->create([
            'session_id' => 'test-session-123',
        ]);

        $found = $this->service->findSession('test-session-123');

        $this->assertNotNull($found);
        $this->assertEquals($session->id, $found->id);
    }

    public function test_can_resume_session(): void
    {
        $session = UploadSession::factory()->create([
            'session_id' => 'test-session-456',
            'status' => 'uploading',
            'expires_at' => Carbon::now()->addHours(1),
        ]);

        $resumed = $this->service->findOrResumeSession('test-session-456', []);

        $this->assertNotNull($resumed);
        $this->assertEquals('uploading', $resumed->status);
    }

    public function test_cannot_resume_expired_session(): void
    {
        $session = UploadSession::factory()->create([
            'session_id' => 'test-session-expired',
            'expires_at' => Carbon::now()->subHour(),
        ]);

        $resumed = $this->service->findOrResumeSession('test-session-expired', []);

        $this->assertNull($resumed);
    }

    public function test_can_get_missing_chunks(): void
    {
        $session = UploadSession::factory()->create([
            'total_chunks' => 5,
        ]);

        $missing = $this->service->getMissingChunks($session->session_id);

        $this->assertEquals([0, 1, 2, 3, 4], $missing);
    }

    public function test_can_update_status(): void
    {
        $session = UploadSession::factory()->create([
            'status' => 'pending',
        ]);

        $this->service->updateStatus($session->session_id, 'uploading');

        $session->refresh();
        $this->assertEquals('uploading', $session->status);
    }
}

