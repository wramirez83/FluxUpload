<?php

namespace Medusa\FluxUpload\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Medusa\FluxUpload\Database\Factories\ChunkFactory;

class Chunk extends Model
{
    use HasFactory;

    protected $table = 'fluxupload_chunks';

    protected $fillable = [
        'session_id',
        'chunk_index',
        'chunk_size',
        'chunk_path',
        'hash',
        'uploaded_at',
    ];

    protected $casts = [
        'chunk_index' => 'integer',
        'chunk_size' => 'integer',
        'uploaded_at' => 'datetime',
    ];

    /**
     * Get the session this chunk belongs to
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(UploadSession::class, 'session_id');
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return ChunkFactory::new();
    }
}

