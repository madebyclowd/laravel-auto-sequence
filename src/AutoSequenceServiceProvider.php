<?php

namespace MadeByClowd\AutoSequence;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Event;
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

            // Allow publishing of Laravel Boost skills
            $this->publishes([
                __DIR__.'/../resources/boost/skills' => base_path('.github/skills'),
            ], 'auto-sequence-boost-skills');

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
        $source = __DIR__.'/../resources/boost/skills/laravel-auto-sequence/SKILL.md';
        if (! file_exists($source)) {
            return;
        }

        $targets = [];
        if (is_dir(base_path('.github/skills'))) {
            $targets[] = base_path('.github/skills/laravel-auto-sequence/SKILL.md');
        }
        if (is_dir(base_path('.ai/skills'))) {
            $targets[] = base_path('.ai/skills/laravel-auto-sequence/SKILL.md');
        }

        if (empty($targets)) {
            $targets[] = base_path('.github/skills/laravel-auto-sequence/SKILL.md');
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
                if (! in_array('laravel-auto-sequence', $boostJson['skills'])) {
                    $boostJson['skills'][] = 'laravel-auto-sequence';
                    file_put_contents(
                        $boostJsonPath,
                        json_encode($boostJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                    );
                }
            }
        }
    }
}
