# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2025-11-16

### Changed
- **BREAKING**: Removed support for Laravel 10 and 11. Now requires Laravel 12 or higher.
- Updated `illuminate/support` requirement from `^10.0|^11.0|^12.0` to `^12.0`

## [1.0.0] - 2024-01-01

### Added
- Initial release of FluxUpload package
- Support for large file uploads (up to 5GB+)
- Chunking system for file division
- Automatic resumption of interrupted uploads
- File integrity validation via hash (optional)
- Support for multiple storage disks (Local, S3, MinIO, Azure Blob)
- Complete REST API
- JavaScript client library
- Event system (FluxUploadCompleted, FluxUploadFailed, FluxUploadChunkReceived)
- Artisan command for cleaning expired sessions
- Comprehensive unit and feature tests
- Full documentation in README

### Features
- Session management with expiration
- Chunk validation and duplicate handling
- Progress tracking
- Missing chunk detection
- Stream-based file assembly to reduce RAM usage
- Configurable chunk size and file size limits
- Extension validation
- Middleware support for authentication

