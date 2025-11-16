<?php

namespace Wramirez83\FluxUpload\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Wramirez83\FluxUpload\Services\SessionService;
use Wramirez83\FluxUpload\Services\ChunkService;
use Wramirez83\FluxUpload\Services\UploadService;
use Illuminate\Support\Facades\Validator;

class ChunkController extends Controller
{
    protected SessionService $sessionService;
    protected ChunkService $chunkService;
    protected UploadService $uploadService;

    public function __construct(
        SessionService $sessionService,
        ChunkService $chunkService,
        UploadService $uploadService
    ) {
        $this->sessionService = $sessionService;
        $this->chunkService = $chunkService;
        $this->uploadService = $uploadService;
    }

    /**
     * Upload a chunk
     */
    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string|max:64',
            'chunk_index' => 'required|integer|min:0',
            'chunk' => 'required|file',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $sessionId = $request->input('session_id');
        $chunkIndex = (int) $request->input('chunk_index');
        $chunkFile = $request->file('chunk');

        // Find session
        $session = $this->sessionService->findSession($sessionId);
        
        if (!$session) {
            return response()->json([
                'success' => false,
                'error' => 'Session not found',
            ], 404);
        }

        if ($session->isExpired()) {
            return response()->json([
                'success' => false,
                'error' => 'Session expired',
            ], 410);
        }

        if ($session->status === 'completed') {
            return response()->json([
                'success' => true,
                'message' => 'Upload already completed',
                'session_id' => $session->session_id,
            ]);
        }

        // Validate chunk
        if (!$this->chunkService->validateChunk($session, $chunkIndex, $chunkFile->getSize())) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid chunk size or index',
            ], 422);
        }

        try {
            // Store chunk
            $chunk = $this->chunkService->storeChunk($session, $chunkIndex, $chunkFile);

            // Check if all chunks are uploaded
            if ($session->isComplete()) {
                // Assemble file
                $this->uploadService->assembleFile($session);
                $session->refresh();
            }

            return response()->json([
                'success' => true,
                'message' => 'Chunk uploaded successfully',
                'session_id' => $session->session_id,
                'chunk_index' => $chunkIndex,
                'uploaded_chunks' => $session->getUploadedChunksCount(),
                'total_chunks' => $session->total_chunks,
                'progress' => round($session->getProgressPercentage(), 2),
                'status' => $session->status,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to upload chunk: ' . $e->getMessage(),
            ], 500);
        }
    }
}

