<?php

namespace Medusa\FluxUpload\Events;

use Medusa\FluxUpload\Models\UploadSession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FluxUploadCompleted
{
    use Dispatchable, SerializesModels;

    public UploadSession $session;

    /**
     * Create a new event instance.
     */
    public function __construct(UploadSession $session)
    {
        $this->session = $session;
    }
}

