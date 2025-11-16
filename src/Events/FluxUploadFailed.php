<?php

namespace Medusa\FluxUpload\Events;

use Medusa\FluxUpload\Models\UploadSession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FluxUploadFailed
{
    use Dispatchable, SerializesModels;

    public UploadSession $session;
    public string $errorMessage;

    /**
     * Create a new event instance.
     */
    public function __construct(UploadSession $session, string $errorMessage)
    {
        $this->session = $session;
        $this->errorMessage = $errorMessage;
    }
}

