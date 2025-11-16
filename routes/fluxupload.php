<?php

use Illuminate\Support\Facades\Route;
use Medusa\FluxUpload\Http\Controllers\InitController;
use Medusa\FluxUpload\Http\Controllers\ChunkController;
use Medusa\FluxUpload\Http\Controllers\StatusController;

$prefix = config('fluxupload.route_prefix', 'fluxupload');
$middleware = config('fluxupload.middleware', ['api']);

Route::prefix($prefix)->middleware($middleware)->group(function () {
    // Initialize upload session
    Route::post('/init', [InitController::class, 'init'])->name('fluxupload.init');
    
    // Upload chunk
    Route::post('/chunk', [ChunkController::class, 'upload'])->name('fluxupload.chunk');
    
    // Get upload status
    Route::get('/status/{sessionId}', [StatusController::class, 'status'])->name('fluxupload.status');
});

