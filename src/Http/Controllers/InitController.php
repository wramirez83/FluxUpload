<?php

namespace Medusa\FluxUpload\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Medusa\FluxUpload\Services\SessionService;
use Illuminate\Support\Facades\Validator;

class InitController extends Controller
{
    protected SessionService $sessionService;

    public function __construct(SessionService $sessionService)
    {
        $this->sessionService = $sessionService;
    }

    /**
     * Initialize upload session
     */
    public function init(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'filename' => 'required|string|max:255',
            'total_size' => 'required|integer|min:1',
            'chunk_size' => 'sometimes|integer|min:1024',
            'mime_type' => 'sometimes|string|max:255',
            'hash' => 'sometimes|string',
            'session_id' => 'sometimes|string|max:64',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $maxFileSize = config('fluxupload.max_file_size', 5368709120);
        
        // Validate file size
        if ($data['total_size'] > $maxFileSize) {
            return response()->json([
                'success' => false,
                'error' => "File size exceeds maximum allowed size of " . $this->formatBytes($maxFileSize),
            ], 422);
        }

        // Check for existing session
        if (isset($data['session_id'])) {
            $existingSession = $this->sessionService->findOrResumeSession(
                $data['session_id'],
                $data
            );

            if ($existingSession) {
                $missingChunks = $existingSession->getMissingChunkIndices();
                
                return response()->json([
                    'success' => true,
                    'session_id' => $existingSession->session_id,
                    'resumed' => true,
                    'uploaded_chunks' => $existingSession->getUploadedChunksCount(),
                    'total_chunks' => $existingSession->total_chunks,
                    'missing_chunks' => $missingChunks,
                    'progress' => round($existingSession->getProgressPercentage(), 2),
                ]);
            }
        }

        // Validate extension if configured
        $extension = pathinfo($data['filename'], PATHINFO_EXTENSION);
        $allowedExtensions = config('fluxupload.allowed_extensions', []);
        
        if (!empty($allowedExtensions) && !in_array(strtolower($extension), array_map('strtolower', $allowedExtensions))) {
            return response()->json([
                'success' => false,
                'error' => "File extension '{$extension}' is not allowed",
            ], 422);
        }

        // Calculate total chunks
        $chunkSize = $data['chunk_size'] ?? config('fluxupload.chunk_size', 5242880);
        $totalChunks = (int) ceil($data['total_size'] / $chunkSize);

        // Create new session
        $session = $this->sessionService->createSession([
            'original_filename' => $data['filename'],
            'filename' => $data['filename'],
            'total_size' => $data['total_size'],
            'total_chunks' => $totalChunks,
            'chunk_size' => $chunkSize,
            'mime_type' => $data['mime_type'] ?? null,
            'extension' => $extension,
            'hash' => $data['hash'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'session_id' => $session->session_id,
            'resumed' => false,
            'total_chunks' => $session->total_chunks,
            'chunk_size' => $session->chunk_size,
            'uploaded_chunks' => 0,
            'missing_chunks' => range(0, $totalChunks - 1),
            'progress' => 0,
        ]);
    }

    /**
     * Format bytes to human readable format
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

