<?php

namespace Medusa\FluxUpload\Services;

use Medusa\FluxUpload\Models\UploadSession;
use Medusa\FluxUpload\Models\Chunk;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Event;
use Medusa\FluxUpload\Events\FluxUploadCompleted;
use Medusa\FluxUpload\Events\FluxUploadFailed;
use Exception;

class UploadService
{
    protected ChunkService $chunkService;

    public function __construct()
    {
        $this->chunkService = new ChunkService();
    }

    /**
     * Assemble chunks into final file
     */
    public function assembleFile(UploadSession $session): bool
    {
        try {
            $session->update(['status' => 'assembling']);

            // Get all chunks ordered by index
            $chunks = Chunk::where('session_id', $session->id)
                ->orderBy('chunk_index')
                ->get();

            if ($chunks->count() !== $session->total_chunks) {
                throw new Exception('Not all chunks are uploaded');
            }

            // Generate final file path
            $disk = Storage::disk($session->storage_disk);
            $finalPath = $this->generateFinalPath($session);
            
            // Open output stream
            $outputStream = fopen('php://temp', 'r+');
            if (!$outputStream) {
                throw new Exception('Could not create output stream');
            }

            // Stream chunks into output
            foreach ($chunks as $chunk) {
                if (!file_exists($chunk->chunk_path)) {
                    throw new Exception("Chunk {$chunk->chunk_index} file not found");
                }

                $chunkStream = fopen($chunk->chunk_path, 'r');
                if (!$chunkStream) {
                    throw new Exception("Could not open chunk {$chunk->chunk_index}");
                }

                stream_copy_to_stream($chunkStream, $outputStream);
                fclose($chunkStream);
            }

            // Rewind output stream
            rewind($outputStream);

            // Calculate hash if needed
            $hash = null;
            if (config('fluxupload.validate_hash', false)) {
                $hash = $this->calculateStreamHash($outputStream, $session->hash_algorithm);
                rewind($outputStream);

                // Validate hash if provided
                if ($session->hash && $hash !== $session->hash) {
                    throw new Exception('File hash validation failed');
                }
            }

            // Store final file
            $disk->put($finalPath, stream_get_contents($outputStream));
            fclose($outputStream);

            // Update session
            $session->update([
                'status' => 'completed',
                'storage_path' => $finalPath,
                'hash' => $hash ?? $session->hash,
            ]);

            // Clean up chunks
            $this->cleanupChunks($session);

            // Emit event
            Event::dispatch(new FluxUploadCompleted($session));

            return true;
        } catch (Exception $e) {
            $session->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Event::dispatch(new FluxUploadFailed($session, $e->getMessage()));

            return false;
        }
    }

    /**
     * Generate final file path
     */
    protected function generateFinalPath(UploadSession $session): string
    {
        $basePath = $session->storage_path ?: config('fluxupload.storage_path', 'fluxupload');
        $filename = $session->filename;
        
        // Add date subdirectory for organization
        $datePath = date('Y/m/d');
        
        return rtrim($basePath, '/') . '/' . $datePath . '/' . $filename;
    }

    /**
     * Calculate hash of stream
     */
    protected function calculateStreamHash($stream, string $algorithm): string
    {
        $hashContext = hash_init($algorithm);
        
        rewind($stream);
        while (!feof($stream)) {
            $data = fread($stream, 8192);
            if ($data !== false) {
                hash_update($hashContext, $data);
            }
        }
        
        return hash_final($hashContext);
    }

    /**
     * Clean up chunk files
     */
    protected function cleanupChunks(UploadSession $session): void
    {
        foreach ($session->chunks as $chunk) {
            if (file_exists($chunk->chunk_path)) {
                @unlink($chunk->chunk_path);
            }
        }

        // Delete session directory
        $sessionDir = dirname($session->chunks()->first()?->chunk_path ?? '');
        if ($sessionDir && is_dir($sessionDir)) {
            @rmdir($sessionDir);
        }

        // Optionally delete chunk records (or keep for audit)
        // $session->chunks()->delete();
    }
}

