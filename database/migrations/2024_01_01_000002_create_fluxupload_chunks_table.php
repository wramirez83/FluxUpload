<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fluxupload_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('fluxupload_sessions')->onDelete('cascade');
            $table->integer('chunk_index');
            $table->bigInteger('chunk_size');
            $table->string('chunk_path');
            $table->string('hash')->nullable();
            $table->timestamp('uploaded_at');
            $table->timestamps();

            $table->unique(['session_id', 'chunk_index']);
            $table->index('session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fluxupload_chunks');
    }
};

