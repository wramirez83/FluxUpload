<?php

namespace Wramirez83\FluxUpload;

use Illuminate\Support\ServiceProvider;
use Wramirez83\FluxUpload\Commands\CleanExpiredSessionsCommand;

class FluxUploadServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/fluxupload.php',
            'fluxupload'
        );

        $this->app->singleton('fluxupload', function ($app) {
            return new FluxUploadManager($app);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/fluxupload.php' => config_path('fluxupload.php'),
        ], 'fluxupload-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'fluxupload-migrations');

        $this->publishes([
            __DIR__ . '/../resources/js' => public_path('vendor/fluxupload'),
        ], 'fluxupload-assets');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../routes/fluxupload.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                CleanExpiredSessionsCommand::class,
            ]);
        }
    }
}

