<?php

namespace Wramirez83\FluxUpload\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Wramirez83\FluxUpload\Services\SessionService;
use Wramirez83\FluxUpload\Services\ChunkService;
use Wramirez83\FluxUpload\Services\UploadService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

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
        // Get session_id and chunk_index first
        $sessionId = $request->input('session_id');
        $chunkIndex = $request->input('chunk_index');
        
        // Try to get the file using multiple methods
        $chunkFile = null;
        
        // Method 1: Laravel's file() method
        if ($request->hasFile('chunk')) {
            $chunkFile = $request->file('chunk');
        }
        
        // Method 2: Direct $_FILES access
        if (!$chunkFile && isset($_FILES['chunk'])) {
            $fileInfo = $_FILES['chunk'];
            
            // If PHP rejected the upload due to size limits, we need to parse manually
            if ($fileInfo['error'] === UPLOAD_ERR_INI_SIZE || $fileInfo['error'] === UPLOAD_ERR_FORM_SIZE) {
                \Log::warning('FluxUpload: PHP rejected upload due to size limits, attempting manual parse', [
                    'error_code' => $fileInfo['error'],
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'post_max_size' => ini_get('post_max_size'),
                    'chunk_size' => config('fluxupload.chunk_size'),
                ]);
                // Will try Method 3 (manual parsing) below
            } elseif ($fileInfo['error'] === UPLOAD_ERR_OK && file_exists($fileInfo['tmp_name'])) {
                try {
                    $chunkFile = new \Illuminate\Http\UploadedFile(
                        $fileInfo['tmp_name'],
                        $fileInfo['name'] ?? 'chunk.bin',
                        $fileInfo['type'] ?? 'application/octet-stream',
                        $fileInfo['error'],
                        true
                    );
                } catch (\Exception $e) {
                    \Log::warning('FluxUpload: Failed to create UploadedFile from $_FILES', [
                        'error' => $e->getMessage(),
                        'file_info' => $fileInfo,
                    ]);
                }
            }
        }
        
        // Method 3: Parse multipart/form-data manually
        // This is especially important when PHP rejects the upload due to size limits
        // The raw content is still available in php://input even if PHP rejected it
        if (!$chunkFile) {
            $chunkFile = $this->getChunkFileFromRequest($request);
        }
        
        // Validate session_id and chunk_index
        $validator = Validator::make([
            'session_id' => $sessionId,
            'chunk_index' => $chunkIndex,
        ], [
            'session_id' => 'required|string|max:64',
            'chunk_index' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid session_id or chunk_index',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Validate chunk file
        if (!$chunkFile) {
            // Enhanced debug information
            $phpInput = file_get_contents('php://input');
            $phpInputSize = strlen($phpInput);
            
            $debug = [
                'has_file_method' => $request->hasFile('chunk'),
                'has_files_superglobal' => isset($_FILES['chunk']),
                'content_type' => $request->header('Content-Type'),
                'request_method' => $request->method(),
                'content_length' => $request->header('Content-Length'),
                'all_input_keys' => array_keys($request->all()),
                'php_input_size' => $phpInputSize,
                'php_upload_max_filesize' => ini_get('upload_max_filesize'),
                'php_post_max_size' => ini_get('post_max_size'),
                'configured_chunk_size' => config('fluxupload.chunk_size'),
            ];
            
            $errorMessage = 'No file was received or file could not be processed.';
            $suggestion = null;
            
            if (isset($_FILES['chunk'])) {
                $uploadError = $_FILES['chunk']['error'];
                $errorMessage = $this->getUploadErrorMessage($uploadError);
                
                $debug['files_chunk'] = [
                    'error' => $uploadError,
                    'error_message' => $errorMessage,
                    'size' => $_FILES['chunk']['size'] ?? null,
                    'name' => $_FILES['chunk']['name'] ?? null,
                    'type' => $_FILES['chunk']['type'] ?? null,
                    'tmp_name' => $_FILES['chunk']['tmp_name'] ?? null,
                    'tmp_name_exists' => isset($_FILES['chunk']['tmp_name']) && file_exists($_FILES['chunk']['tmp_name']),
                ];
                
                // Provide specific suggestions based on error
                if ($uploadError === UPLOAD_ERR_INI_SIZE) {
                    $suggestion = 'PHP upload_max_filesize (' . ini_get('upload_max_filesize') . ') is too small for chunk size (' . 
                                  $this->formatBytes(config('fluxupload.chunk_size')) . '). ' .
                                  'Please increase upload_max_filesize in php.ini or reduce chunk_size in config/fluxupload.php';
                } elseif ($uploadError === UPLOAD_ERR_FORM_SIZE) {
                    $suggestion = 'The chunk exceeds MAX_FILE_SIZE. Please check form configuration.';
                } elseif ($uploadError === UPLOAD_ERR_PARTIAL) {
                    $suggestion = 'The chunk was only partially uploaded. Please check network connection and try again.';
                }
            }
            
            $contentType = $request->header('Content-Type', '');
            if (strpos($contentType, 'multipart/form-data') !== false) {
                $debug['multipart_detected'] = true;
                if (preg_match('/boundary=([^\s;]+)/i', $contentType, $matches)) {
                    $debug['boundary_found'] = true;
                    $debug['boundary'] = $matches[1];
                } else {
                    $debug['boundary_found'] = false;
                }
            } else {
                $debug['multipart_detected'] = false;
            }
            
            // If we have content in php://input but no file, it might be a size limit issue
            if ($phpInputSize > 0 && !isset($_FILES['chunk']['tmp_name'])) {
                $debug['note'] = 'Content is available in php://input but PHP rejected the upload. This usually indicates upload_max_filesize is too small.';
            }
            
            \Log::error('FluxUpload: Chunk file not received', [
                'session_id' => $sessionId,
                'chunk_index' => $chunkIndex,
                'debug' => $debug,
                'suggestion' => $suggestion,
            ]);
            
            $response = [
                'success' => false,
                'error' => 'The chunk failed to upload.',
                'errors' => [
                    'chunk' => [$errorMessage],
                ],
                'debug' => $debug,
            ];
            
            if ($suggestion) {
                $response['suggestion'] = $suggestion;
            }
            
            return response()->json($response, 422);
        }
        
        // Validate chunk file is valid
        if (!$chunkFile->isValid()) {
            return response()->json([
                'success' => false,
                'error' => 'The chunk failed to upload.',
                'errors' => [
                    'chunk' => ['Uploaded file is not valid: ' . $chunkFile->getErrorMessage()],
                ],
            ], 422);
        }

        $chunkIndex = (int) $chunkIndex;

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
        $chunkSize = $chunkFile->getSize();
        if (!$this->chunkService->validateChunk($session, $chunkIndex, $chunkSize)) {
            $expectedSize = $this->chunkService->getExpectedChunkSize($session, $chunkIndex);
            return response()->json([
                'success' => false,
                'error' => 'Invalid chunk size or index',
                'errors' => [
                    'chunk' => [
                        'The chunk failed to upload.',
                        "Expected size: {$expectedSize}, Got: {$chunkSize}",
                        "Chunk index: {$chunkIndex}, Total chunks: {$session->total_chunks}",
                    ],
                ],
            ], 422);
        }

        try {
            // Store chunk
            $chunk = $this->chunkService->storeChunk($session, $chunkIndex, $chunkFile);

            // Refresh session to get latest state
            $session->refresh();

            // Check if all chunks are uploaded
            if ($session->isComplete()) {
                // Assemble file in background to avoid timeout for large files
                // Use queue or async if available, otherwise run synchronously
                try {
                    $this->uploadService->assembleFile($session);
                    $session->refresh();
                } catch (\Exception $e) {
                    \Log::error('FluxUpload: Failed to assemble file', [
                        'session_id' => $session->session_id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'error' => 'Failed to assemble file: ' . $e->getMessage(),
                        'session_id' => $session->session_id,
                    ], 500);
                }
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
                'is_complete' => $session->isComplete(),
            ]);
        } catch (\Exception $e) {
            \Log::error('FluxUpload: Chunk upload failed', [
                'session_id' => $sessionId,
                'chunk_index' => $chunkIndex,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to upload chunk: ' . $e->getMessage(),
                'session_id' => $sessionId,
                'chunk_index' => $chunkIndex,
            ], 500);
        }
    }

    /**
     * Get chunk file from request using alternative methods
     */
    protected function getChunkFileFromRequest(Request $request): ?\Illuminate\Http\UploadedFile
    {
        // If Laravel doesn't recognize it as a file, try to get it from $_FILES directly
        if (isset($_FILES['chunk']) && $_FILES['chunk']['error'] === UPLOAD_ERR_OK) {
            $fileInfo = $_FILES['chunk'];
            if (file_exists($fileInfo['tmp_name'])) {
                return new \Illuminate\Http\UploadedFile(
                    $fileInfo['tmp_name'],
                    $fileInfo['name'],
                    $fileInfo['type'] ?? 'application/octet-stream',
                    $fileInfo['error'],
                    true
                );
            }
        }
        
        // If still no file, parse multipart/form-data manually
        return $this->parseMultipartChunk($request);
    }

    /**
     * Parse multipart/form-data to extract chunk file
     */
    protected function parseMultipartChunk(Request $request): ?\Illuminate\Http\UploadedFile
    {
        $contentType = $request->header('Content-Type', '');
        
        // Check if it's multipart/form-data
        if (strpos($contentType, 'multipart/form-data') === false) {
            \Log::debug('FluxUpload: Not multipart/form-data', ['content_type' => $contentType]);
            return null;
        }

        // Extract boundary
        if (!preg_match('/boundary=([^\s;]+)/i', $contentType, $matches)) {
            \Log::debug('FluxUpload: Boundary not found in Content-Type', ['content_type' => $contentType]);
            return null;
        }

        $boundary = '--' . trim($matches[1]);
        $rawContent = file_get_contents('php://input');
        
        if (empty($rawContent)) {
            \Log::debug('FluxUpload: Empty raw content');
            return null;
        }
        
        \Log::debug('FluxUpload: Parsing multipart', [
            'boundary' => $boundary,
            'raw_content_size' => strlen($rawContent),
        ]);

        // Split by boundary - need to handle boundary with and without leading dashes
        $boundaryWithDashes = $boundary;
        $boundaryWithoutDashes = ltrim($boundary, '-');
        
        // Try splitting with both formats
        $parts = [];
        if (strpos($rawContent, $boundaryWithDashes) !== false) {
            $parts = explode($boundaryWithDashes, $rawContent);
        } elseif (strpos($rawContent, $boundaryWithoutDashes) !== false) {
            $parts = explode($boundaryWithoutDashes, $rawContent);
        } else {
            // Try with just the boundary value
            $boundaryValue = trim($matches[1]);
            if (strpos($rawContent, $boundaryValue) !== false) {
                $parts = explode('--' . $boundaryValue, $rawContent);
            }
        }
        
        if (empty($parts)) {
            return null;
        }
        
        foreach ($parts as $partIndex => $part) {
            // Skip empty parts and boundary markers
            $part = trim($part);
            if (empty($part) || $part === '--' || $part === '') {
                continue;
            }

            // Look for chunk field - be more flexible with the regex
            $chunkPatterns = [
                '/Content-Disposition:\s*form-data;\s*name\s*=\s*"chunk"(?:;\s*filename\s*=\s*"([^"]+)")?/i',
                '/Content-Disposition:\s*form-data;\s*name\s*=\s*[\'"]chunk[\'"]/i',
                '/name\s*=\s*"chunk"/i',
            ];
            
            $dispositionMatch = null;
            foreach ($chunkPatterns as $pattern) {
                if (preg_match($pattern, $part, $matches)) {
                    $dispositionMatch = $matches;
                    break;
                }
            }
            
            if (!$dispositionMatch) {
                continue;
            }

            // Find where headers end and content begins
            // Headers are separated from content by \r\n\r\n or \n\n
            $headerEnd = false;
            $headerLength = 0;
            
            $patterns = [
                "\r\n\r\n" => 4,
                "\n\n" => 2,
                "\r\n\n" => 3,
                "\n\r\n" => 3,
            ];
            
            foreach ($patterns as $pattern => $length) {
                $pos = strpos($part, $pattern);
                if ($pos !== false) {
                    $headerEnd = $pos;
                    $headerLength = $length;
                    break;
                }
            }
            
            if ($headerEnd === false) {
                // Try to find content after the last header line
                $lines = explode("\n", $part);
                $headerEnd = 0;
                $headerLength = 0;
                foreach ($lines as $lineIndex => $line) {
                    if (trim($line) === '' && $lineIndex > 0) {
                        // Found empty line, this is where content starts
                        $headerEnd = strlen(implode("\n", array_slice($lines, 0, $lineIndex + 1)));
                        $headerLength = 1; // The \n itself
                        break;
                    }
                }
            }
            
            if ($headerEnd === false) {
                continue;
            }

            // Extract chunk content
            $chunkContent = substr($part, $headerEnd + $headerLength);
            
            // Clean up: remove leading/trailing whitespace and boundary markers
            $chunkContent = ltrim($chunkContent, "\r\n");
            $chunkContent = rtrim($chunkContent, "\r\n");
            // Remove trailing boundary markers (--boundary or boundary--)
            $chunkContent = preg_replace('/--+$/', '', $chunkContent);
            $chunkContent = rtrim($chunkContent, "\r\n \t-");
            
            // Validate we have content
            if (empty($chunkContent) || strlen($chunkContent) === 0) {
                continue;
            }

            // Create temporary file
            $tempPath = sys_get_temp_dir() . '/fluxupload_chunk_' . uniqid() . '_' . time() . '.bin';
            $bytesWritten = @file_put_contents($tempPath, $chunkContent);
            
            if ($bytesWritten === false || $bytesWritten === 0) {
                @unlink($tempPath);
                continue;
            }

            // Verify file was created and has content
            if (!file_exists($tempPath) || filesize($tempPath) === 0) {
                @unlink($tempPath);
                continue;
            }

            // Create UploadedFile instance
            try {
                $uploadedFile = new \Illuminate\Http\UploadedFile(
                    $tempPath,
                    isset($dispositionMatch[1]) && !empty($dispositionMatch[1]) ? $dispositionMatch[1] : 'chunk.bin',
                    'application/octet-stream',
                    null,
                    true // test mode
                );
                
                \Log::debug('FluxUpload: Successfully parsed chunk from multipart', [
                    'temp_path' => $tempPath,
                    'size' => filesize($tempPath),
                ]);
                
                return $uploadedFile;
            } catch (\Exception $e) {
                \Log::warning('FluxUpload: Failed to create UploadedFile from parsed chunk', [
                    'error' => $e->getMessage(),
                    'temp_path' => $tempPath,
                ]);
                @unlink($tempPath);
                continue;
            }
        }
        
        \Log::debug('FluxUpload: No chunk found in multipart data');
        return null;
    }
    
    /**
     * Get upload error message
     */
    protected function getUploadErrorMessage(int $errorCode): string
    {
        $messages = [
            UPLOAD_ERR_OK => 'There is no error, the file uploaded with success.',
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
        ];
        
        return $messages[$errorCode] ?? 'Unknown upload error: ' . $errorCode;
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
