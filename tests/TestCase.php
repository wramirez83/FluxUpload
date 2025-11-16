<?php

namespace Medusa\FluxUpload\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Medusa\FluxUpload\FluxUploadServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function getPackageProviders($app)
    {
        return [
            FluxUploadServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('fluxupload.chunk_size', 5242880);
        $app['config']->set('fluxupload.max_file_size', 5368709120);
        $app['config']->set('fluxupload.session_expiration_hours', 24);
        $app['config']->set('fluxupload.storage_disk', 'local');
        $app['config']->set('fluxupload.chunks_path', storage_path('app/fluxupload/chunks'));
    }
}

