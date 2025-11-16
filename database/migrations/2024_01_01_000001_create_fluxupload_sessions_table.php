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
        Schema::create('fluxupload_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 64)->unique();
            $table->string('filename');
            $table->string('original_filename');
            $table->bigInteger('total_size');
            $table->integer('total_chunks');
            $table->integer('chunk_size');
            $table->string('mime_type')->nullable();
            $table->string('extension')->nullable();
            $table->string('hash')->nullable();
            $table->string('hash_algorithm')->nullable();
            $table->string('storage_disk')->default('local');
            $table->string('storage_path')->nullable();
            $table->enum('status', ['pending', 'uploading', 'assembling', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('session_id');
            $table->index('status');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fluxupload_sessions');
    }
};

