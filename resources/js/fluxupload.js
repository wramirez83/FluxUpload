/**
 * FluxUpload - JavaScript Client Library
 * 
 * A resumable file upload library for Laravel FluxUpload package
 */
class FluxUpload {
    constructor(options = {}) {
        this.baseUrl = options.baseUrl || '/fluxupload';
        this.chunkSize = options.chunkSize || 5 * 1024 * 1024; // 5MB default
        this.maxRetries = options.maxRetries || 3;
        this.retryDelay = options.retryDelay || 1000;
        this.parallelUploads = options.parallelUploads || 3;
        this.onProgress = options.onProgress || null;
        this.onComplete = options.onComplete || null;
        this.onError = options.onError || null;
        this.onChunkComplete = options.onChunkComplete || null;
        
        this.sessionId = null;
        this.file = null;
        this.totalChunks = 0;
        this.uploadedChunks = new Set();
        this.failedChunks = new Map();
        this.isUploading = false;
        this.isPaused = false;
    }

    /**
     * Initialize upload session
     */
    async init(file, options = {}) {
        this.file = file;
        this.chunkSize = options.chunkSize || this.chunkSize;
        this.totalChunks = Math.ceil(file.size / this.chunkSize);

        const initData = {
            filename: file.name,
            total_size: file.size,
            chunk_size: this.chunkSize,
            mime_type: file.type,
            ...(options.hash && { hash: options.hash }),
            ...(this.sessionId && { session_id: this.sessionId }),
        };

        try {
            const response = await fetch(`${this.baseUrl}/init`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    ...(options.headers || {}),
                },
                body: JSON.stringify(initData),
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Failed to initialize upload');
            }

            this.sessionId = data.session_id;

            // If resuming, mark uploaded chunks
            if (data.resumed && data.uploaded_chunks) {
                for (let i = 0; i < data.uploaded_chunks; i++) {
                    this.uploadedChunks.add(i);
                }
            }

            return data;
        } catch (error) {
            this.handleError(error);
            throw error;
        }
    }

    /**
     * Upload file
     */
    async upload(file, options = {}) {
        if (this.isUploading) {
            throw new Error('Upload already in progress');
        }

        this.isUploading = true;
        this.isPaused = false;

        try {
            // Initialize session
            await this.init(file, options);

            // Upload chunks
            if (this.parallelUploads > 1) {
                await this.uploadParallel();
            } else {
                await this.uploadSequential();
            }

            // Wait for all chunks to complete
            while (this.uploadedChunks.size < this.totalChunks && !this.isPaused) {
                await this.sleep(100);
            }

            if (this.uploadedChunks.size === this.totalChunks) {
                // Check final status
                const status = await this.getStatus();
                
                if (status.status === 'completed') {
                    this.handleComplete(status);
                } else if (status.status === 'failed') {
                    throw new Error(status.error_message || 'Upload failed');
                }
            }
        } catch (error) {
            this.handleError(error);
            throw error;
        } finally {
            this.isUploading = false;
        }
    }

    /**
     * Upload chunks sequentially
     */
    async uploadSequential() {
        for (let i = 0; i < this.totalChunks; i++) {
            if (this.isPaused) break;
            if (this.uploadedChunks.has(i)) continue;

            await this.uploadChunk(i);
        }
    }

    /**
     * Upload chunks in parallel
     */
    async uploadParallel() {
        const uploadPromises = [];
        let currentIndex = 0;

        while (currentIndex < this.totalChunks || uploadPromises.length > 0) {
            // Start new uploads up to parallel limit
            while (uploadPromises.length < this.parallelUploads && currentIndex < this.totalChunks) {
                if (this.uploadedChunks.has(currentIndex)) {
                    currentIndex++;
                    continue;
                }

                const promise = this.uploadChunk(currentIndex).finally(() => {
                    const index = uploadPromises.indexOf(promise);
                    if (index > -1) {
                        uploadPromises.splice(index, 1);
                    }
                });

                uploadPromises.push(promise);
                currentIndex++;
            }

            // Wait a bit before checking again
            await this.sleep(50);
        }
    }

    /**
     * Upload a single chunk
     */
    async uploadChunk(chunkIndex, retryCount = 0) {
        if (this.uploadedChunks.has(chunkIndex)) {
            return;
        }

        const start = chunkIndex * this.chunkSize;
        const end = Math.min(start + this.chunkSize, this.file.size);
        const chunk = this.file.slice(start, end);

        const formData = new FormData();
        formData.append('session_id', this.sessionId);
        formData.append('chunk_index', chunkIndex);
        formData.append('chunk', chunk, `chunk_${chunkIndex}`);

        try {
            const response = await fetch(`${this.baseUrl}/chunk`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                },
                body: formData,
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Chunk upload failed');
            }

            this.uploadedChunks.add(chunkIndex);
            this.failedChunks.delete(chunkIndex);

            if (this.onChunkComplete) {
                this.onChunkComplete(chunkIndex, data);
            }

            this.updateProgress();

            return data;
        } catch (error) {
            if (retryCount < this.maxRetries) {
                await this.sleep(this.retryDelay * (retryCount + 1));
                return this.uploadChunk(chunkIndex, retryCount + 1);
            } else {
                this.failedChunks.set(chunkIndex, error);
                throw error;
            }
        }
    }

    /**
     * Get upload status
     */
    async getStatus() {
        if (!this.sessionId) {
            throw new Error('No active session');
        }

        const response = await fetch(`${this.baseUrl}/status/${this.sessionId}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
            },
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Failed to get status');
        }

        return data;
    }

    /**
     * Resume upload
     */
    async resume() {
        if (!this.sessionId) {
            throw new Error('No session to resume');
        }

        const status = await this.getStatus();

        if (status.status === 'completed') {
            this.handleComplete(status);
            return;
        }

        if (status.status === 'failed') {
            throw new Error(status.error_message || 'Upload failed');
        }

        // Update uploaded chunks
        this.uploadedChunks.clear();
        for (let i = 0; i < status.uploaded_chunks; i++) {
            this.uploadedChunks.add(i);
        }

        // Continue uploading
        this.isPaused = false;
        await this.uploadParallel();
    }

    /**
     * Pause upload
     */
    pause() {
        this.isPaused = true;
    }

    /**
     * Cancel upload
     */
    cancel() {
        this.isPaused = true;
        this.uploadedChunks.clear();
        this.failedChunks.clear();
    }

    /**
     * Update progress
     */
    updateProgress() {
        if (this.onProgress) {
            const progress = (this.uploadedChunks.size / this.totalChunks) * 100;
            this.onProgress({
                progress: Math.round(progress * 100) / 100,
                uploaded: this.uploadedChunks.size,
                total: this.totalChunks,
                failed: this.failedChunks.size,
            });
        }
    }

    /**
     * Handle upload complete
     */
    handleComplete(status) {
        if (this.onComplete) {
            this.onComplete(status);
        }
    }

    /**
     * Handle error
     */
    handleError(error) {
        if (this.onError) {
            this.onError(error);
        }
    }

    /**
     * Sleep utility
     */
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

// Export for different module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = FluxUpload;
}
if (typeof window !== 'undefined') {
    window.FluxUpload = FluxUpload;
}

