<?php

namespace Wramirez83\FluxUpload\Services;

use Wramirez83\FluxUpload\Models\UploadSession;
use Wramirez83\FluxUpload\Models\Chunk;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Event;
use Wramirez83\FluxUpload\Events\FluxUploadCompleted;
use Wramirez83\FluxUpload\Events\FluxUploadFailed;
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
            
            // Ensure directory exists
            $finalDir = dirname($disk->path($finalPath));
            if (!is_dir($finalDir)) {
                @mkdir($finalDir, 0755, true);
            }
            
            // For large files, write directly to final location instead of using php://temp
            // This avoids loading entire file into memory
            $finalFilePath = $disk->path($finalPath);
            $outputStream = fopen($finalFilePath, 'wb');
            if (!$outputStream) {
                throw new Exception('Could not create output file: ' . $finalFilePath);
            }

            // Stream chunks directly into final file
            $totalBytesWritten = 0;
            foreach ($chunks as $chunk) {
                if (!file_exists($chunk->chunk_path)) {
                    fclose($outputStream);
                    @unlink($finalFilePath);
                    throw new Exception("Chunk {$chunk->chunk_index} file not found at: {$chunk->chunk_path}");
                }

                $chunkStream = fopen($chunk->chunk_path, 'rb');
                if (!$chunkStream) {
                    fclose($outputStream);
                    @unlink($finalFilePath);
                    throw new Exception("Could not open chunk {$chunk->chunk_index} at: {$chunk->chunk_path}");
                }

                $bytesCopied = stream_copy_to_stream($chunkStream, $outputStream);
                fclose($chunkStream);
                
                if ($bytesCopied === false || $bytesCopied === 0) {
                    fclose($outputStream);
                    @unlink($finalFilePath);
                    throw new Exception("Failed to copy chunk {$chunk->chunk_index} content");
                }
                
                $totalBytesWritten += $bytesCopied;
            }

            fclose($outputStream);

            // Verify file size matches expected
            $actualSize = filesize($finalFilePath);
            if ($actualSize !== $session->total_size) {
                @unlink($finalFilePath);
                throw new Exception("File size mismatch. Expected: {$session->total_size}, Got: {$actualSize}");
            }

            // Calculate hash if needed (read file again for hash calculation)
            $hash = null;
            if (config('fluxupload.validate_hash', false)) {
                $hash = hash_file($session->hash_algorithm, $finalFilePath);

                // Validate hash if provided
                if ($session->hash && $hash !== $session->hash) {
                    @unlink($finalFilePath);
                    throw new Exception('File hash validation failed. Expected: ' . $session->hash . ', Got: ' . $hash);
                }
            }

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

