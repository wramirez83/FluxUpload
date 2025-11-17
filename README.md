# FluxUpload v3.0.0 - Laravel Package for Large File Uploads

FluxUpload es un paquete Laravel que permite la subida de archivos grandes (superiores a 1GB) mediante chunking (división en partes) con capacidad de reanudación automática.

## 📋 Características

- ✅ Subida de archivos grandes (hasta 25GB)
- ✅ Chunking automático (división en partes)
- ✅ Reanudación automática de cargas interrumpidas
- ✅ Validación de integridad mediante hash (opcional)
- ✅ Soporte para múltiples discos de almacenamiento (Local, S3, MinIO, Azure Blob)
- ✅ API REST completa
- ✅ Cliente JavaScript incluido
- ✅ Eventos para integración
- ✅ Limpieza automática de sesiones expiradas
- ✅ Compatible con Laravel 12

## 📦 Instalación

### Requisitos

- PHP 8.1 o superior
- Laravel 12
- Extensiones PHP: `fileinfo`, `mbstring`, `openssl`, `json`

### Instalación vía Composer

```bash
composer require wramirez83/fluxupload
```

**Versión actual**: 3.0.0

### Publicar configuración y migraciones

```bash
php artisan vendor:publish --tag=fluxupload-config
php artisan vendor:publish --tag=fluxupload-migrations
```

### Ejecutar migraciones

```bash
php artisan migrate
```

### Publicar assets JavaScript (opcional)

```bash
php artisan vendor:publish --tag=fluxupload-assets
```

## ⚙️ Configuración

El archivo de configuración se encuentra en `config/fluxupload.php`. Puedes configurar:

```php
// Tamaño de chunk por defecto (2MB en v3.0.0)
'chunk_size' => env('FLUXUPLOAD_CHUNK_SIZE', 2097152),

// Tamaño máximo de archivo (25GB en v3.0.0)
'max_file_size' => env('FLUXUPLOAD_MAX_FILE_SIZE', 26843545600),

// Expiración de sesiones (24 horas)
'session_expiration_hours' => env('FLUXUPLOAD_SESSION_EXPIRATION', 24),

// Disco de almacenamiento
'storage_disk' => env('FLUXUPLOAD_STORAGE_DISK', 'local'),

// Ruta de almacenamiento
'storage_path' => env('FLUXUPLOAD_STORAGE_PATH', 'fluxupload'),

// Extensiones permitidas (vacío = todas)
'allowed_extensions' => env('FLUXUPLOAD_ALLOWED_EXTENSIONS') 
    ? explode(',', env('FLUXUPLOAD_ALLOWED_EXTENSIONS'))
    : [],

// Validar hash
'validate_hash' => env('FLUXUPLOAD_VALIDATE_HASH', false),
'hash_algorithm' => env('FLUXUPLOAD_HASH_ALGORITHM', 'sha256'),
```

### Variables de entorno

Puedes configurar el paquete mediante variables de entorno en tu archivo `.env`:

```env
FLUXUPLOAD_CHUNK_SIZE=2097152
FLUXUPLOAD_MAX_FILE_SIZE=26843545600
FLUXUPLOAD_SESSION_EXPIRATION=24
FLUXUPLOAD_STORAGE_DISK=local
FLUXUPLOAD_STORAGE_PATH=fluxupload
FLUXUPLOAD_ALLOWED_EXTENSIONS=pdf,doc,docx,zip
FLUXUPLOAD_VALIDATE_HASH=true
FLUXUPLOAD_HASH_ALGORITHM=sha256
FLUXUPLOAD_ROUTE_PREFIX=fluxupload
FLUXUPLOAD_MIDDLEWARE=api
```

## 🚀 Uso Básico

### API REST

#### 1. Inicializar sesión de subida

```http
POST /fluxupload/init
Content-Type: application/json

{
    "filename": "documento.pdf",
    "total_size": 104857600,
    "chunk_size": 2097152,
    "mime_type": "application/pdf",
    "hash": "sha256_hash_here" // opcional
}
```

**Respuesta:**

```json
{
    "success": true,
    "session_id": "abc123...",
    "total_chunks": 50,
    "chunk_size": 2097152,
    "uploaded_chunks": 0,
    "missing_chunks": [0, 1, 2, ..., 19],
    "progress": 0
}
```

#### 2. Subir chunk

```http
POST /fluxupload/chunk
Content-Type: multipart/form-data

session_id: abc123...
chunk_index: 0
chunk: [archivo binario]
```

**Respuesta:**

```json
{
    "success": true,
    "message": "Chunk uploaded successfully",
    "session_id": "abc123...",
    "chunk_index": 0,
    "uploaded_chunks": 1,
    "total_chunks": 50,
    "progress": 5.0,
    "status": "uploading"
}
```

#### 3. Consultar estado

```http
GET /fluxupload/status/{session_id}
```

**Respuesta:**

```json
{
    "success": true,
    "session_id": "abc123...",
    "filename": "documento.pdf",
    "status": "uploading",
    "uploaded_chunks": 25,
    "total_chunks": 50,
    "total_size": 104857600,
    "progress": 50.0,
    "missing_chunks": [25, 26, 27, ..., 49],
    "storage_path": null,
    "error_message": null,
    "expires_at": "2024-01-01T12:00:00Z"
}
```

### Cliente JavaScript

#### Uso básico

```html
<script src="/vendor/fluxupload/fluxupload.js"></script>
<script>
const uploader = new FluxUpload({
    baseUrl: '/fluxupload',
    chunkSize: 2 * 1024 * 1024, // 2MB (v3.0.0)
    parallelUploads: 3,
    onProgress: (progress) => {
        console.log(`Progress: ${progress.progress}%`);
    },
    onComplete: (status) => {
        console.log('Upload completed!', status);
    },
    onError: (error) => {
        console.error('Upload error:', error);
    }
});

// Subir archivo
const fileInput = document.getElementById('fileInput');
fileInput.addEventListener('change', async (e) => {
    const file = e.target.files[0];
    try {
        await uploader.upload(file);
    } catch (error) {
        console.error('Upload failed:', error);
    }
});
</script>
```

#### Reanudar subida

```javascript
// Si tienes un session_id previo
uploader.sessionId = 'previous-session-id';
await uploader.resume();
```

#### Pausar/Cancelar

```javascript
// Pausar
uploader.pause();

// Cancelar
uploader.cancel();
```

### Uso en PHP (Backend)

```php
use Wramirez83\FluxUpload\Facades\FluxUpload;

// Obtener servicios
$uploadService = FluxUpload::getUploadService();
$sessionService = FluxUpload::getSessionService();
$chunkService = FluxUpload::getChunkService();

// Crear sesión manualmente
$session = $sessionService->createSession([
    'original_filename' => 'archivo.pdf',
    'total_size' => 104857600,
    'total_chunks' => 50,
    'chunk_size' => 2097152,
]);

// Obtener chunks faltantes
$missingChunks = $sessionService->getMissingChunks($session->session_id);

// Ensamblar archivo manualmente
$uploadService->assembleFile($session);
```

## 📡 Eventos

FluxUpload emite eventos que puedes escuchar:

### FluxUploadCompleted

Se emite cuando una subida se completa exitosamente.

```php
use Wramirez83\FluxUpload\Events\FluxUploadCompleted;
use Illuminate\Support\Facades\Event;

Event::listen(FluxUploadCompleted::class, function (FluxUploadCompleted $event) {
    $session = $event->session;
    
    // Procesar archivo completado
    // $session->storage_path contiene la ruta del archivo final
    // $session->storage_disk contiene el disco donde se guardó
});
```

### FluxUploadFailed

Se emite cuando una subida falla.

```php
use Wramirez83\FluxUpload\Events\FluxUploadFailed;

Event::listen(FluxUploadFailed::class, function (FluxUploadFailed $event) {
    $session = $event->session;
    $error = $event->errorMessage;
    
    // Manejar error
    logger()->error("Upload failed: {$error}", [
        'session_id' => $session->session_id,
    ]);
});
```

### FluxUploadChunkReceived

Se emite cada vez que se recibe un chunk (opcional).

```php
use Wramirez83\FluxUpload\Events\FluxUploadChunkReceived;

Event::listen(FluxUploadChunkReceived::class, function (FluxUploadChunkReceived $event) {
    $session = $event->session;
    $chunk = $event->chunk;
    
    // Procesar chunk recibido
});
```

## 🧹 Limpieza de Sesiones

### Comando Artisan

Para limpiar sesiones expiradas manualmente:

```bash
php artisan fluxupload:clean
```

### Modo dry-run

Para ver qué se eliminaría sin eliminar realmente:

```bash
php artisan fluxupload:clean --dry-run
```

### Programar limpieza automática

Agrega al `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('fluxupload:clean')
        ->daily()
        ->at('02:00');
}
```

## 🔒 Seguridad

### Autenticación

Puedes proteger las rutas mediante middleware. En `config/fluxupload.php`:

```php
'middleware' => ['api', 'auth:sanctum'],
```

O usando middleware personalizado:

```php
'middleware' => ['api', 'throttle:60,1'],
```

### Validación de extensiones

```php
'allowed_extensions' => ['pdf', 'doc', 'docx', 'zip'],
```

### Validación de hash

Para validar la integridad del archivo:

```php
'validate_hash' => true,
'hash_algorithm' => 'sha256', // o 'md5'
```

## 🧪 Pruebas

Ejecutar todas las pruebas:

```bash
composer test
```

O con PHPUnit directamente:

```bash
./vendor/bin/phpunit
```

### Estructura de pruebas

- `tests/Unit/` - Pruebas unitarias de servicios y modelos
- `tests/Feature/` - Pruebas de integración de la API

## 📚 Ejemplos

### Ejemplo completo con JavaScript

```html
<!DOCTYPE html>
<html>
<head>
    <title>FluxUpload Example</title>
</head>
<body>
    <input type="file" id="fileInput">
    <button id="uploadBtn">Upload</button>
    <button id="pauseBtn">Pause</button>
    <button id="resumeBtn">Resume</button>
    <div id="progress"></div>

    <script src="/vendor/fluxupload/fluxupload.js"></script>
    <script>
        const uploader = new FluxUpload({
            baseUrl: '/fluxupload',
            chunkSize: 2 * 1024 * 1024, // 2MB (v3.0.0)
            parallelUploads: 3,
            onProgress: (progress) => {
                document.getElementById('progress').textContent = 
                    `Progress: ${progress.progress}% (${progress.uploaded}/${progress.total})`;
            },
            onComplete: (status) => {
                alert('Upload completed!');
                console.log('File saved at:', status.storage_path);
            },
            onError: (error) => {
                alert('Upload failed: ' + error.message);
            }
        });

        document.getElementById('uploadBtn').addEventListener('click', async () => {
            const fileInput = document.getElementById('fileInput');
            if (fileInput.files.length > 0) {
                try {
                    await uploader.upload(fileInput.files[0]);
                } catch (error) {
                    console.error(error);
                }
            }
        });

        document.getElementById('pauseBtn').addEventListener('click', () => {
            uploader.pause();
        });

        document.getElementById('resumeBtn').addEventListener('click', async () => {
            try {
                await uploader.resume();
            } catch (error) {
                console.error(error);
            }
        });
    </script>
</body>
</html>
```

### Ejemplo con React

```jsx
import { useState } from 'react';
import FluxUpload from './fluxupload';

function FileUploader() {
    const [progress, setProgress] = useState(0);
    const [uploader] = useState(() => new FluxUpload({
        baseUrl: '/fluxupload',
        onProgress: (p) => setProgress(p.progress),
        onComplete: (status) => {
            console.log('Completed:', status);
            setProgress(100);
        },
        onError: (error) => {
            console.error('Error:', error);
        }
    }));

    const handleFileChange = async (e) => {
        const file = e.target.files[0];
        if (file) {
            await uploader.upload(file);
        }
    };

    return (
        <div>
            <input type="file" onChange={handleFileChange} />
            <div>Progress: {progress}%</div>
        </div>
    );
}
```

## 🐛 Solución de Problemas

### Error: "Session not found"

- Verifica que el `session_id` sea correcto
- Verifica que la sesión no haya expirado
- Revisa los logs de Laravel

### Error: "Chunk size validation failed"

- Verifica que el tamaño del chunk coincida con el configurado
- El último chunk puede ser más pequeño que el tamaño configurado

### Archivo no se ensambla correctamente

- Verifica que todos los chunks se hayan subido
- Revisa los permisos de escritura en el directorio de chunks
- Verifica el espacio en disco disponible

## 📝 Changelog

Ver [CHANGELOG.md](CHANGELOG.md) para más detalles.

## 🤝 Contribuir

Las contribuciones son bienvenidas. Por favor:

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## 📄 Licencia

Este paquete es de código abierto bajo la [licencia MIT](LICENSE).

## 👥 Autores

- **Medusa** - *Desarrollo inicial*

## 🙏 Agradecimientos

- Laravel Framework
- Comunidad de desarrolladores PHP

---

**¿Necesitas ayuda?** Abre un issue en GitHub o contacta al equipo de desarrollo.

