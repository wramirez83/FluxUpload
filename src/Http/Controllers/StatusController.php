<?php

namespace Wramirez83\FluxUpload\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Wramirez83\FluxUpload\Services\SessionService;

class StatusController extends Controller
{
    protected SessionService $sessionService;

    public function __construct(SessionService $sessionService)
    {
        $this->sessionService = $sessionService;
    }

    /**
     * Get upload status
     */
    public function status(Request $request, string $sessionId): JsonResponse
    {
        $session = $this->sessionService->findSession($sessionId);

        if (!$session) {
            return response()->json([
                'success' => false,
                'error' => 'Session not found',
            ], 404);
        }

        $missingChunks = $session->getMissingChunkIndices();

        return response()->json([
            'success' => true,
            'session_id' => $session->session_id,
            'filename' => $session->original_filename,
            'status' => $session->status,
            'uploaded_chunks' => $session->getUploadedChunksCount(),
            'total_chunks' => $session->total_chunks,
            'total_size' => $session->total_size,
            'progress' => round($session->getProgressPercentage(), 2),
            'missing_chunks' => $missingChunks,
            'storage_path' => $session->status === 'completed' ? $session->storage_path : null,
            'error_message' => $session->error_message,
            'expires_at' => $session->expires_at->toIso8601String(),
        ]);
    }
}

