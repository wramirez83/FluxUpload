<?php

namespace Wramirez83\FluxUpload\Database\Factories;

use Wramirez83\FluxUpload\Models\UploadSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Carbon\Carbon;

class UploadSessionFactory extends Factory
{
    protected $model = UploadSession::class;

    public function definition(): array
    {
        return [
            'session_id' => Str::random(32),
            'filename' => $this->faker->uuid() . '.txt',
            'original_filename' => $this->faker->word() . '.txt',
            'total_size' => $this->faker->numberBetween(1024, 10485760),
            'total_chunks' => $this->faker->numberBetween(1, 10),
            'chunk_size' => 5242880,
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'hash' => null,
            'hash_algorithm' => 'sha256',
            'storage_disk' => 'local',
            'storage_path' => null,
            'status' => 'pending',
            'error_message' => null,
            'expires_at' => Carbon::now()->addHours(24),
        ];
    }
}

