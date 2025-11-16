<?php

namespace Wramirez83\FluxUpload\Events;

use Wramirez83\FluxUpload\Models\UploadSession;
use Wramirez83\FluxUpload\Models\Chunk;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FluxUploadChunkReceived
{
    use Dispatchable, SerializesModels;

    public UploadSession $session;
    public Chunk $chunk;

    /**
     * Create a new event instance.
     */
    public function __construct(UploadSession $session, Chunk $chunk)
    {
        $this->session = $session;
        $this->chunk = $chunk;
    }
}

