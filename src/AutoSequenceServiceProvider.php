<?php

namespace MadeByClowd\AutoSequence;

use Illuminate\Support\ServiceProvider;
use MadeByClowd\AutoSequence\Console\InstallCommand;
use MadeByClowd\AutoSequence\Console\ListCommand;
use MadeByClowd\AutoSequence\Console\ResetCommand;
use MadeByClowd\AutoSequence\Console\VerifyCommand;

class AutoSequenceServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/auto-sequence.php', 'auto-sequence');

        $this->app->singleton('auto-sequence', function ($app) {
            return new SequenceManager;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Load migrations automatically if configured or if in testing
        if (config('auto-sequence.load_migrations', true) || $this->app->runningUnitTests()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        if ($this->app->runningInConsole()) {
            // Allow publishing of the config file
            $this->publishes([
                __DIR__.'/../config/auto-sequence.php' => config_path('auto-sequence.php'),
            ], 'auto-sequence-config');

            // Allow publishing of database migrations
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'auto-sequence-migrations');

            // Register Artisan commands
            $this->commands([
                InstallCommand::class,
                ListCommand::class,
                ResetCommand::class,
                VerifyCommand::class,
            ]);
        }
    }
}
