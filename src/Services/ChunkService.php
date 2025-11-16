<?php

namespace Medusa\FluxUpload\Services;

use Medusa\FluxUpload\Models\UploadSession;
use Medusa\FluxUpload\Models\Chunk;
use Medusa\FluxUpload\Events\FluxUploadChunkReceived;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Event;
use Carbon\Carbon;

class ChunkService
{
    /**
     * Store a chunk
     */
    public function storeChunk(UploadSession $session, int $chunkIndex, UploadedFile $file): Chunk
    {
        // Check if chunk already exists
        $existingChunk = Chunk::where('session_id', $session->id)
            ->where('chunk_index', $chunkIndex)
            ->first();

        if ($existingChunk) {
            // Verify the chunk is the same size (basic validation)
            if ($existingChunk->chunk_size === $file->getSize()) {
                return $existingChunk;
            }
            // If different, delete and recreate
            $this->deleteChunk($existingChunk);
        }

        // Create session directory
        $sessionDir = $this->getSessionDirectory($session->session_id);
        if (!is_dir($sessionDir)) {
            @mkdir($sessionDir, 0755, true);
        }
        
        // Ensure directory was created
        if (!is_dir($sessionDir)) {
            throw new \RuntimeException("Could not create directory: {$sessionDir}");
        }

        // Store chunk
        $chunkPath = $sessionDir . '/' . $chunkIndex;
        $fileSize = $file->getSize();
        $file->move($sessionDir, $chunkIndex);

        // Calculate hash if needed
        $hash = null;
        if (config('fluxupload.validate_hash', false)) {
            $hash = hash_file(config('fluxupload.hash_algorithm', 'sha256'), $chunkPath);
        }

        // Create chunk record
        $chunk = Chunk::create([
            'session_id' => $session->id,
            'chunk_index' => $chunkIndex,
            'chunk_size' => $fileSize,
            'chunk_path' => $chunkPath,
            'hash' => $hash,
            'uploaded_at' => Carbon::now(),
        ]);

        // Update session status
        if ($session->status === 'pending') {
            $session->update(['status' => 'uploading']);
        }

        // Emit event
        Event::dispatch(new FluxUploadChunkReceived($session, $chunk));

        return $chunk;
    }

    /**
     * Get chunk by session and index
     */
    public function getChunk(UploadSession $session, int $chunkIndex): ?Chunk
    {
        return Chunk::where('session_id', $session->id)
            ->where('chunk_index', $chunkIndex)
            ->first();
    }

    /**
     * Get all chunks for a session ordered by index
     */
    public function getChunks(UploadSession $session): array
    {
        return Chunk::where('session_id', $session->id)
            ->orderBy('chunk_index')
            ->get()
            ->toArray();
    }

    /**
     * Validate chunk
     */
    public function validateChunk(UploadSession $session, int $chunkIndex, int $chunkSize): bool
    {
        // Validate chunk index
        if ($chunkIndex < 0 || $chunkIndex >= $session->total_chunks) {
            return false;
        }

        // Basic validation: chunk must be positive and not exceed chunk_size
        if ($chunkSize <= 0 || $chunkSize > $session->chunk_size) {
            return false;
        }
        
        // Validate chunk size against expected size
        $expectedSize = $this->getExpectedChunkSize($session, $chunkIndex);
        
        // Last chunk can be smaller or equal to expected size
        if ($chunkIndex === $session->total_chunks - 1) {
            return $chunkSize <= $expectedSize;
        }
        
        // For non-last chunks, allow small tolerance (1 byte) for rounding errors
        return abs($chunkSize - $expectedSize) <= 1;
    }

    /**
     * Get expected chunk size
     */
    public function getExpectedChunkSize(UploadSession $session, int $chunkIndex): int
    {
        $chunkSize = $session->chunk_size;
        $totalSize = $session->total_size;
        $totalChunks = $session->total_chunks;

        // Last chunk might be smaller
        if ($chunkIndex === $totalChunks - 1) {
            $remainingSize = $totalSize - ($chunkSize * ($totalChunks - 1));
            return $remainingSize > 0 ? $remainingSize : $chunkSize;
        }

        return $chunkSize;
    }

    /**
     * Delete a chunk
     */
    public function deleteChunk(Chunk $chunk): bool
    {
        if (file_exists($chunk->chunk_path)) {
            @unlink($chunk->chunk_path);
        }

        return $chunk->delete();
    }

    /**
     * Get session directory path
     */
    protected function getSessionDirectory(string $sessionId): string
    {
        $chunksPath = config('fluxupload.chunks_path', storage_path('app/fluxupload/chunks'));
        return $chunksPath . '/' . $sessionId;
    }
}

