<?php

namespace MadeByClowd\Sequenceable;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use MadeByClowd\Sequenceable\Console\InstallCommand;
use MadeByClowd\Sequenceable\Console\ListCommand;
use MadeByClowd\Sequenceable\Console\ResetCommand;
use MadeByClowd\Sequenceable\Console\VerifyCommand;

class SequenceableServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sequenceable.php', 'sequenceable');

        $this->app->singleton('sequenceable', function ($app) {
            return new SequenceManager;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Load migrations automatically if configured or if in testing
        if (config('sequenceable.load_migrations', true) || $this->app->runningUnitTests()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        if ($this->app->runningInConsole()) {
            // Allow publishing of the config file
            $this->publishes([
                __DIR__.'/../config/sequenceable.php' => config_path('sequenceable.php'),
            ], 'sequenceable-config');

            // Allow publishing of database migrations
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'sequenceable-migrations');

            // Allow publishing of Laravel Boost skills
            $this->publishes([
                __DIR__.'/../resources/boost/skills' => base_path('.github/skills'),
            ], 'sequenceable-boost-skills');

            // Register Artisan commands
            $this->commands([
                InstallCommand::class,
                ListCommand::class,
                ResetCommand::class,
                VerifyCommand::class,
            ]);

            // Automatically push our AI agent skill on boost install/update
            Event::listen(
                CommandFinished::class,
                function (CommandFinished $event) {
                    if (in_array($event->command, ['boost:install', 'boost:update'])) {
                        $this->autoPublishBoostSkills();
                    }
                }
            );
        }
    }

    /**
     * Automatically copy Boost skill markdown to project repository.
     */
    protected function autoPublishBoostSkills(): void
    {
        $source = __DIR__.'/../resources/boost/skills/laravel-sequenceable/SKILL.md';
        if (! file_exists($source)) {
            return;
        }

        $targets = [];
        if (is_dir(base_path('.github/skills'))) {
            $targets[] = base_path('.github/skills/laravel-sequenceable/SKILL.md');
        }
        if (is_dir(base_path('.ai/skills'))) {
            $targets[] = base_path('.ai/skills/laravel-sequenceable/SKILL.md');
        }

        if (empty($targets)) {
            $targets[] = base_path('.github/skills/laravel-sequenceable/SKILL.md');
        }

        foreach ($targets as $destination) {
            if (! is_dir(dirname($destination))) {
                mkdir(dirname($destination), 0755, true);
            }
            copy($source, $destination);
        }

        // Auto-register in boost.json if it exists
        $boostJsonPath = base_path('boost.json');
        if (file_exists($boostJsonPath)) {
            $boostJson = json_decode(file_get_contents($boostJsonPath), true);
            if (is_array($boostJson) && isset($boostJson['skills'])) {
                if (! in_array('laravel-sequenceable', $boostJson['skills'])) {
                    $boostJson['skills'][] = 'laravel-sequenceable';
                    file_put_contents(
                        $boostJsonPath,
                        json_encode($boostJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                    );
                }
            }
        }
    }
}
