<?php

namespace Medusa\FluxUpload\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Medusa\FluxUpload\Database\Factories\UploadSessionFactory;
use Carbon\Carbon;

class UploadSession extends Model
{
    use HasFactory;

    protected $table = 'fluxupload_sessions';

    protected $fillable = [
        'session_id',
        'filename',
        'original_filename',
        'total_size',
        'total_chunks',
        'chunk_size',
        'mime_type',
        'extension',
        'hash',
        'hash_algorithm',
        'storage_disk',
        'storage_path',
        'status',
        'error_message',
        'expires_at',
    ];

    protected $casts = [
        'total_size' => 'integer',
        'total_chunks' => 'integer',
        'chunk_size' => 'integer',
        'expires_at' => 'datetime',
    ];

    /**
     * Get all chunks for this session
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(Chunk::class, 'session_id');
    }

    /**
     * Check if session is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Get uploaded chunks count
     */
    public function getUploadedChunksCount(): int
    {
        return $this->chunks()->count();
    }

    /**
     * Get missing chunk indices
     */
    public function getMissingChunkIndices(): array
    {
        $uploadedIndices = $this->chunks()->pluck('chunk_index')->toArray();
        $allIndices = range(0, $this->total_chunks - 1);
        
        return array_values(array_diff($allIndices, $uploadedIndices));
    }

    /**
     * Check if all chunks are uploaded
     */
    public function isComplete(): bool
    {
        return $this->getUploadedChunksCount() === $this->total_chunks;
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentage(): float
    {
        if ($this->total_chunks === 0) {
            return 0;
        }

        return ($this->getUploadedChunksCount() / $this->total_chunks) * 100;
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return UploadSessionFactory::new();
    }
}

