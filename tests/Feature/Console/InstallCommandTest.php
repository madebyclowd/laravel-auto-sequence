<?php

namespace MadeByClowd\AutoSequence\Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use MadeByClowd\AutoSequence\Console\InstallCommand;

class InstallCommandTest extends ConsoleTestCase
{
    /**
     * Registers a version of InstallCommand whose inner `$this->call(...)`
     * fails (returns FAILURE) for whichever sub-command the given matcher
     * selects, so the command's own error-handling branches can be exercised
     * without needing a real failure from vendor:publish/migrate.
     *
     * A plain container bind() is not enough here: ConsoleTestCase::setUp()
     * already boots the console kernel (via the earlier `migrate` call),
     * which eagerly resolves and caches the real InstallCommand instance.
     * Re-registering the command overwrites that cached instance instead.
     */
    private function registerFailingInstallCommand(\Closure $shouldFail): void
    {
        Artisan::registerCommand(new class($shouldFail) extends InstallCommand
        {
            public function __construct(private \Closure $shouldFail)
            {
                parent::__construct();
            }

            public function call($command, array $arguments = [])
            {
                if (($this->shouldFail)($command, $arguments)) {
                    return self::FAILURE;
                }

                return parent::call($command, $arguments);
            }
        });
    }

    /** @test */
    public function test_it_skips_config_publish_if_it_already_exists()
    {
        file_put_contents(config_path('auto-sequence.php'), '<?php return [];');

        $this->artisan('sequence:install', ['--publish-config' => true])
            ->expectsOutputToContain('already exists, skipping')
            ->assertExitCode(0);
    }

    /** @test */
    public function test_it_runs_full_flow_with_explicit_options()
    {
        @unlink(config_path('auto-sequence.php'));

        $this->artisan('sequence:install', [
            '--publish-config' => true,
            '--publish-migrations' => true,
            '--migrate' => true,
        ])
            ->assertExitCode(0);

        $this->assertFileExists(config_path('auto-sequence.php'));
    }

    /** @test */
    public function test_it_runs_interactive_flow_with_defaults()
    {
        @unlink(config_path('auto-sequence.php'));

        $this->artisan('sequence:install')
            ->expectsConfirmation(
                '1. Publish the config file? (lets you tweak locking, caching, and audit columns later)',
                'yes'
            )
            ->expectsConfirmation(
                '2. Publish the migration files? (only needed if you want to edit the sequence tables yourself — otherwise the package loads them automatically)',
                'no'
            )
            ->expectsConfirmation(
                '3. Run the database migrations now? (creates the "sequences" and "sequence_recycled" tables)',
                'yes'
            )
            ->assertExitCode(0);

        $this->assertFileExists(config_path('auto-sequence.php'));
    }

    /** @test */
    public function test_opting_out_of_config_publish_skips_it_without_prompting()
    {
        @unlink(config_path('auto-sequence.php'));

        // Passing --migrate alone marks options as "explicit", so publish-config
        // (left at its false default) is skipped silently instead of prompted.
        $this->artisan('sequence:install', ['--migrate' => true])
            ->assertExitCode(0);

        $this->assertFileDoesNotExist(config_path('auto-sequence.php'));
        $this->assertTrue(Schema::hasTable('sequences'));
    }

    /** @test */
    public function test_it_fails_when_config_publish_fails()
    {
        @unlink(config_path('auto-sequence.php'));

        $this->registerFailingInstallCommand(
            fn ($command, $arguments) => $command === 'vendor:publish' && ($arguments['--tag'] ?? null) === 'auto-sequence-config'
        );

        $this->artisan('sequence:install', ['--publish-config' => true])
            ->expectsOutputToContain('Failed to publish configuration file.')
            ->assertExitCode(1);
    }

    /** @test */
    public function test_it_fails_when_migrations_publish_fails()
    {
        $this->registerFailingInstallCommand(
            fn ($command, $arguments) => $command === 'vendor:publish' && ($arguments['--tag'] ?? null) === 'auto-sequence-migrations'
        );

        $this->artisan('sequence:install', ['--publish-migrations' => true])
            ->expectsOutputToContain('Failed to publish migrations.')
            ->assertExitCode(1);
    }

    /** @test */
    public function test_it_fails_when_running_migrations_fails()
    {
        $this->registerFailingInstallCommand(
            fn ($command, $arguments) => $command === 'migrate'
        );

        $this->artisan('sequence:install', ['--migrate' => true])
            ->expectsOutputToContain('Database migrations failed.')
            ->assertExitCode(1);
    }
}
