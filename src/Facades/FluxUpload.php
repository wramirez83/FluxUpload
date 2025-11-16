<?php

namespace Wramirez83\FluxUpload\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Wramirez83\FluxUpload\Services\UploadService getUploadService()
 * @method static \Wramirez83\FluxUpload\Services\SessionService getSessionService()
 * @method static \Wramirez83\FluxUpload\Services\ChunkService getChunkService()
 */
class FluxUpload extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'fluxupload';
    }
}

