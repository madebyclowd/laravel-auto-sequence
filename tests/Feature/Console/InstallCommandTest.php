<?php

namespace MadeByClowd\AutoSequence\Tests\Feature\Console;

use Illuminate\Support\Facades\Schema;

class InstallCommandTest extends ConsoleTestCase
{
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
}
