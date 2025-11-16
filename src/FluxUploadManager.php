<?php

namespace Wramirez83\FluxUpload;

use Wramirez83\FluxUpload\Services\UploadService;
use Wramirez83\FluxUpload\Services\SessionService;
use Wramirez83\FluxUpload\Services\ChunkService;

class FluxUploadManager
{
    protected UploadService $uploadService;
    protected SessionService $sessionService;
    protected ChunkService $chunkService;

    public function __construct($app)
    {
        $this->uploadService = new UploadService();
        $this->sessionService = new SessionService();
        $this->chunkService = new ChunkService();
    }

    public function getUploadService(): UploadService
    {
        return $this->uploadService;
    }

    public function getSessionService(): SessionService
    {
        return $this->sessionService;
    }

    public function getChunkService(): ChunkService
    {
        return $this->chunkService;
    }
}

