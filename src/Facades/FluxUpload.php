<?php

namespace Medusa\FluxUpload\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Medusa\FluxUpload\Services\UploadService getUploadService()
 * @method static \Medusa\FluxUpload\Services\SessionService getSessionService()
 * @method static \Medusa\FluxUpload\Services\ChunkService getChunkService()
 */
class FluxUpload extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'fluxupload';
    }
}

