<?php

namespace Medusa\FluxUpload\Services;

use Medusa\FluxUpload\Models\UploadSession;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class SessionService
{
    /**
     * Create a new upload session
     */
    public function createSession(array $data): UploadSession
    {
        $expirationHours = config('fluxupload.session_expiration_hours', 24);
        
        return UploadSession::create([
            'session_id' => $this->generateSessionId(),
            'filename' => $data['filename'] ?? Str::uuid() . '.' . ($data['extension'] ?? ''),
            'original_filename' => $data['original_filename'],
            'total_size' => $data['total_size'],
            'total_chunks' => $data['total_chunks'],
            'chunk_size' => $data['chunk_size'] ?? config('fluxupload.chunk_size'),
            'mime_type' => $data['mime_type'] ?? null,
            'extension' => $data['extension'] ?? null,
            'hash' => $data['hash'] ?? null,
            'hash_algorithm' => $data['hash_algorithm'] ?? config('fluxupload.hash_algorithm'),
            'storage_disk' => $data['storage_disk'] ?? config('fluxupload.storage_disk'),
            'storage_path' => $data['storage_path'] ?? config('fluxupload.storage_path'),
            'status' => 'pending',
            'expires_at' => Carbon::now()->addHours($expirationHours),
        ]);
    }

    /**
     * Find session by session_id
     */
    public function findSession(string $sessionId): ?UploadSession
    {
        return UploadSession::where('session_id', $sessionId)->first();
    }

    /**
     * Find or resume existing session
     */
    public function findOrResumeSession(string $sessionId, array $data): ?UploadSession
    {
        $session = $this->findSession($sessionId);
        
        if ($session && !$session->isExpired() && $session->status !== 'completed') {
            $session->update(['status' => 'uploading']);
            return $session;
        }

        return null;
    }

    /**
     * Update session status
     */
    public function updateStatus(string $sessionId, string $status, ?string $errorMessage = null): bool
    {
        $session = $this->findSession($sessionId);
        
        if (!$session) {
            return false;
        }

        $session->update([
            'status' => $status,
            'error_message' => $errorMessage,
        ]);

        return true;
    }

    /**
     * Get missing chunks for a session
     */
    public function getMissingChunks(string $sessionId): array
    {
        $session = $this->findSession($sessionId);
        
        if (!$session) {
            return [];
        }

        return $session->getMissingChunkIndices();
    }

    /**
     * Clean expired sessions
     */
    public function cleanExpiredSessions(bool $dryRun = false): int
    {
        $expiredSessions = UploadSession::where('expires_at', '<', Carbon::now())
            ->orWhere(function ($query) {
                $query->where('status', 'completed')
                    ->where('updated_at', '<', Carbon::now()->subHours(1));
            })
            ->get();

        $count = 0;
        
        foreach ($expiredSessions as $session) {
            if (!$dryRun) {
                $this->deleteSession($session->session_id);
            }
            $count++;
        }

        return $count;
    }

    /**
     * Delete a session and its chunks
     */
    public function deleteSession(string $sessionId): bool
    {
        $session = $this->findSession($sessionId);
        
        if (!$session) {
            return false;
        }

        // Delete chunk files
        foreach ($session->chunks as $chunk) {
            if (file_exists($chunk->chunk_path)) {
                @unlink($chunk->chunk_path);
            }
        }

        // Delete session directory if exists
        $sessionDir = dirname($session->chunks()->first()?->chunk_path ?? '');
        if ($sessionDir && is_dir($sessionDir)) {
            @rmdir($sessionDir);
        }

        // Delete from database
        $session->delete();

        return true;
    }

    /**
     * Generate unique session ID
     */
    protected function generateSessionId(): string
    {
        do {
            $sessionId = Str::random(32);
        } while (UploadSession::where('session_id', $sessionId)->exists());

        return $sessionId;
    }
}

