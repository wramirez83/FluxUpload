<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Chunk Size
    |--------------------------------------------------------------------------
    |
    | Default chunk size in bytes. Recommended: 2MB (2097152 bytes) for v3.0.0
    |
    */
    'chunk_size' => env('FLUXUPLOAD_CHUNK_SIZE', 2097152),

    /*
    |--------------------------------------------------------------------------
    | Max File Size
    |--------------------------------------------------------------------------
    |
    | Maximum file size in bytes. Default: 25GB (26843545600 bytes) for v3.0.0
    |
    */
    'max_file_size' => env('FLUXUPLOAD_MAX_FILE_SIZE', 26843545600),

    /*
    |--------------------------------------------------------------------------
    | Session Expiration
    |--------------------------------------------------------------------------
    |
    | Session expiration time in hours. Sessions older than this will be
    | cleaned up automatically.
    |
    */
    'session_expiration_hours' => env('FLUXUPLOAD_SESSION_EXPIRATION', 24),

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | The disk where final files will be stored. Can be 'local', 's3', etc.
    |
    */
    'storage_disk' => env('FLUXUPLOAD_STORAGE_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Storage Path
    |--------------------------------------------------------------------------
    |
    | The path within the storage disk where files will be stored.
    |
    */
    'storage_path' => env('FLUXUPLOAD_STORAGE_PATH', 'fluxupload'),

    /*
    |--------------------------------------------------------------------------
    | Chunks Path
    |--------------------------------------------------------------------------
    |
    | The path where temporary chunks will be stored.
    |
    */
    'chunks_path' => storage_path('app/fluxupload/chunks'),

    /*
    |--------------------------------------------------------------------------
    | Allowed Extensions
    |--------------------------------------------------------------------------
    |
    | Array of allowed file extensions. Empty array means all extensions are allowed.
    |
    */
    'allowed_extensions' => env('FLUXUPLOAD_ALLOWED_EXTENSIONS') 
        ? explode(',', env('FLUXUPLOAD_ALLOWED_EXTENSIONS'))
        : [],

    /*
    |--------------------------------------------------------------------------
    | Validate Hash
    |--------------------------------------------------------------------------
    |
    | Whether to validate file integrity using hash (MD5 or SHA256).
    |
    */
    'validate_hash' => env('FLUXUPLOAD_VALIDATE_HASH', false),

    /*
    |--------------------------------------------------------------------------
    | Hash Algorithm
    |--------------------------------------------------------------------------
    |
    | Hash algorithm to use: 'md5' or 'sha256'
    |
    */
    'hash_algorithm' => env('FLUXUPLOAD_HASH_ALGORITHM', 'sha256'),

    /*
    |--------------------------------------------------------------------------
    | Route Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix for all FluxUpload routes.
    |
    */
    'route_prefix' => env('FLUXUPLOAD_ROUTE_PREFIX', 'fluxupload'),

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware to apply to FluxUpload routes. Can be used for authentication.
    |
    */
    'middleware' => env('FLUXUPLOAD_MIDDLEWARE') 
        ? explode(',', env('FLUXUPLOAD_MIDDLEWARE'))
        : ['api'],

    /*
    |--------------------------------------------------------------------------
    | Enable JavaScript Client
    |--------------------------------------------------------------------------
    |
    | Whether to serve the JavaScript client library.
    |
    */
    'enable_js_client' => env('FLUXUPLOAD_ENABLE_JS_CLIENT', true),
];

