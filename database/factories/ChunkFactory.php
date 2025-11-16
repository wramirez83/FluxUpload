<?php

namespace Medusa\FluxUpload\Database\Factories;

use Medusa\FluxUpload\Models\Chunk;
use Medusa\FluxUpload\Models\UploadSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class ChunkFactory extends Factory
{
    protected $model = Chunk::class;

    public function definition(): array
    {
        return [
            'session_id' => UploadSession::factory(),
            'chunk_index' => $this->faker->numberBetween(0, 9),
            'chunk_size' => $this->faker->numberBetween(1024, 5242880),
            'chunk_path' => storage_path('app/fluxupload/chunks/test/' . $this->faker->numberBetween(0, 9)),
            'hash' => null,
            'uploaded_at' => Carbon::now(),
        ];
    }
}

