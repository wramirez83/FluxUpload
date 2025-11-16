<?php

namespace Medusa\FluxUpload\Events;

use Medusa\FluxUpload\Models\UploadSession;
use Medusa\FluxUpload\Models\Chunk;
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

