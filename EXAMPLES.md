# FluxUpload - Ejemplos de Uso

## Ejemplo 1: Subida básica con JavaScript

```html
<!DOCTYPE html>
<html>
<head>
    <title>FluxUpload - Ejemplo Básico</title>
    <style>
        .progress-bar {
            width: 100%;
            height: 30px;
            background-color: #f0f0f0;
            border-radius: 5px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background-color: #4CAF50;
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
    </style>
</head>
<body>
    <h1>Subir Archivo Grande</h1>
    
    <input type="file" id="fileInput" />
    <button id="uploadBtn">Subir</button>
    <button id="pauseBtn">Pausar</button>
    <button id="resumeBtn">Reanudar</button>
    
    <div class="progress-bar" style="margin-top: 20px;">
        <div class="progress-fill" id="progressFill" style="width: 0%">
            <span id="progressText">0%</span>
        </div>
    </div>
    
    <div id="status" style="margin-top: 10px;"></div>

    <script src="/vendor/fluxupload/fluxupload.js"></script>
    <script>
        const uploader = new FluxUpload({
            baseUrl: '/fluxupload',
            chunkSize: 5 * 1024 * 1024, // 5MB
            parallelUploads: 3,
            maxRetries: 3,
            onProgress: (progress) => {
                const fill = document.getElementById('progressFill');
                const text = document.getElementById('progressText');
                fill.style.width = progress.progress + '%';
                text.textContent = progress.progress.toFixed(2) + '%';
                
                document.getElementById('status').textContent = 
                    `Subidos: ${progress.uploaded}/${progress.total} chunks`;
            },
            onComplete: (status) => {
                document.getElementById('status').textContent = 
                    '¡Subida completada! Archivo guardado en: ' + status.storage_path;
                alert('Archivo subido exitosamente');
            },
            onError: (error) => {
                document.getElementById('status').textContent = 
                    'Error: ' + error.message;
                console.error('Error de subida:', error);
            }
        });

        document.getElementById('uploadBtn').addEventListener('click', async () => {
            const fileInput = document.getElementById('fileInput');
            if (fileInput.files.length > 0) {
                try {
                    await uploader.upload(fileInput.files[0]);
                } catch (error) {
                    console.error('Error:', error);
                }
            } else {
                alert('Por favor selecciona un archivo');
            }
        });

        document.getElementById('pauseBtn').addEventListener('click', () => {
            uploader.pause();
            document.getElementById('status').textContent = 'Subida pausada';
        });

        document.getElementById('resumeBtn').addEventListener('click', async () => {
            try {
                await uploader.resume();
                document.getElementById('status').textContent = 'Reanudando subida...';
            } catch (error) {
                console.error('Error al reanudar:', error);
            }
        });
    </script>
</body>
</html>
```

## Ejemplo 2: Uso con React

```jsx
import React, { useState, useRef } from 'react';
import FluxUpload from './fluxupload';

function FileUploader() {
    const [progress, setProgress] = useState(0);
    const [status, setStatus] = useState('');
    const [isUploading, setIsUploading] = useState(false);
    const uploaderRef = useRef(null);

    const initializeUploader = () => {
        if (!uploaderRef.current) {
            uploaderRef.current = new FluxUpload({
                baseUrl: '/fluxupload',
                chunkSize: 5 * 1024 * 1024,
                parallelUploads: 3,
                onProgress: (p) => {
                    setProgress(p.progress);
                    setStatus(`Subidos: ${p.uploaded}/${p.total} chunks`);
                },
                onComplete: (result) => {
                    setProgress(100);
                    setStatus('¡Subida completada!');
                    setIsUploading(false);
                    console.log('Archivo guardado en:', result.storage_path);
                },
                onError: (error) => {
                    setStatus('Error: ' + error.message);
                    setIsUploading(false);
                }
            });
        }
        return uploaderRef.current;
    };

    const handleFileChange = async (e) => {
        const file = e.target.files[0];
        if (!file) return;

        const uploader = initializeUploader();
        setIsUploading(true);
        setProgress(0);
        setStatus('Iniciando subida...');

        try {
            await uploader.upload(file);
        } catch (error) {
            console.error('Error:', error);
            setIsUploading(false);
        }
    };

    const handlePause = () => {
        if (uploaderRef.current) {
            uploaderRef.current.pause();
            setStatus('Subida pausada');
        }
    };

    const handleResume = async () => {
        if (uploaderRef.current) {
            try {
                await uploaderRef.current.resume();
                setStatus('Reanudando...');
            } catch (error) {
                console.error('Error al reanudar:', error);
            }
        }
    };

    return (
        <div>
            <input 
                type="file" 
                onChange={handleFileChange}
                disabled={isUploading}
            />
            <button onClick={handlePause} disabled={!isUploading}>
                Pausar
            </button>
            <button onClick={handleResume} disabled={!isUploading}>
                Reanudar
            </button>
            
            <div style={{ marginTop: '20px' }}>
                <div style={{ 
                    width: '100%', 
                    height: '30px', 
                    backgroundColor: '#f0f0f0',
                    borderRadius: '5px',
                    overflow: 'hidden'
                }}>
                    <div style={{
                        width: `${progress}%`,
                        height: '100%',
                        backgroundColor: '#4CAF50',
                        transition: 'width 0.3s',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        color: 'white'
                    }}>
                        {progress.toFixed(2)}%
                    </div>
                </div>
            </div>
            
            <div style={{ marginTop: '10px' }}>{status}</div>
        </div>
    );
}

export default FileUploader;
```

## Ejemplo 3: Uso en el Backend (Laravel)

### Escuchar eventos

```php
<?php

namespace App\Listeners;

use Wramirez83\FluxUpload\Events\FluxUploadCompleted;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessUploadedFile
{
    public function handle(FluxUploadCompleted $event)
    {
        $session = $event->session;
        
        // El archivo ya está guardado en $session->storage_path
        $filePath = $session->storage_path;
        $disk = $session->storage_disk;
        
        // Procesar el archivo
        Log::info("Archivo subido: {$filePath} en disco {$disk}");
        
        // Ejemplo: mover a otra ubicación
        // Storage::disk($disk)->move($filePath, 'processed/' . basename($filePath));
        
        // Ejemplo: enviar notificación
        // Notification::send($user, new FileUploadedNotification($session));
    }
}
```

Registrar el listener en `app/Providers/EventServiceProvider.php`:

```php
use Wramirez83\FluxUpload\Events\FluxUploadCompleted;
use App\Listeners\ProcessUploadedFile;

protected $listen = [
    FluxUploadCompleted::class => [
        ProcessUploadedFile::class,
    ],
];
```

### Uso directo de servicios

```php
<?php

namespace App\Http\Controllers;

use Wramirez83\FluxUpload\Facades\FluxUpload;
use Illuminate\Http\Request;

class CustomUploadController extends Controller
{
    public function customUpload(Request $request)
    {
        $sessionService = FluxUpload::getSessionService();
        $chunkService = FluxUpload::getChunkService();
        $uploadService = FluxUpload::getUploadService();
        
        // Crear sesión
        $session = $sessionService->createSession([
            'original_filename' => $request->input('filename'),
            'total_size' => $request->input('size'),
            'total_chunks' => $request->input('chunks'),
            'chunk_size' => $request->input('chunk_size'),
        ]);
        
        return response()->json([
            'session_id' => $session->session_id,
            'status' => 'created'
        ]);
    }
    
    public function checkStatus($sessionId)
    {
        $sessionService = FluxUpload::getSessionService();
        $session = $sessionService->findSession($sessionId);
        
        if (!$session) {
            return response()->json(['error' => 'Session not found'], 404);
        }
        
        return response()->json([
            'status' => $session->status,
            'progress' => $session->getProgressPercentage(),
            'missing_chunks' => $session->getMissingChunkIndices(),
        ]);
    }
}
```

## Ejemplo 4: Configuración con S3

En tu archivo `.env`:

```env
FLUXUPLOAD_STORAGE_DISK=s3
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket
```

En `config/filesystems.php`:

```php
's3' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
],
```

## Ejemplo 5: Validación de Hash

```javascript
const crypto = require('crypto');
const fs = require('fs');

// Calcular hash del archivo antes de subir
function calculateFileHash(filePath, algorithm = 'sha256') {
    return new Promise((resolve, reject) => {
        const hash = crypto.createHash(algorithm);
        const stream = fs.createReadStream(filePath);
        
        stream.on('data', (data) => hash.update(data));
        stream.on('end', () => resolve(hash.digest('hex')));
        stream.on('error', reject);
    });
}

// En el cliente JavaScript
async function uploadWithHash(file) {
    // Calcular hash (requiere biblioteca de hash en el navegador)
    const hash = await calculateHash(file); // Implementar según necesidad
    
    const uploader = new FluxUpload({
        baseUrl: '/fluxupload',
        onComplete: (status) => {
            console.log('Hash validado:', status.hash);
        }
    });
    
    await uploader.upload(file, { hash: hash });
}
```

## Ejemplo 6: Autenticación con Sanctum

En `config/fluxupload.php`:

```php
'middleware' => ['api', 'auth:sanctum'],
```

O crear middleware personalizado:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class FluxUploadAuth
{
    public function handle(Request $request, Closure $next)
    {
        // Verificar que el usuario tenga permisos
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        // Verificar límite de tamaño por usuario
        $user = auth()->user();
        $totalSize = $request->input('total_size', 0);
        
        if ($totalSize > $user->max_upload_size) {
            return response()->json(['error' => 'File too large'], 422);
        }
        
        return $next($request);
    }
}
```

Registrar en `app/Http/Kernel.php`:

```php
protected $middlewareGroups = [
    'api' => [
        // ...
        \App\Http\Middleware\FluxUploadAuth::class,
    ],
];
```

