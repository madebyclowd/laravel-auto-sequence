<?php

namespace MadeByClowd\AutoSequence\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sequence:install
                            {--publish-config : Automatically publish configuration file}
                            {--publish-migrations : Automatically publish migrations files}
                            {--migrate : Automatically run database migrations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set up the Laravel Auto Sequence package (publish assets and migrate)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->components->info('Welcome! This wizard sets up Laravel Auto Sequence in 3 quick steps.');
        $this->line('    Just answer yes/no below — the defaults work for most apps.');
        $this->newLine();

        $hasExplicitOptions = $this->option('publish-config') ||
            $this->option('publish-migrations') ||
            $this->option('migrate');

        // 1. Publish Config
        $configPath = config_path('auto-sequence.php');
        if (file_exists($configPath)) {
            $this->components->twoColumnDetail('1. Config file', '<fg=yellow>already exists, skipping</>');
        } else {
            $publishConfig = $this->option('publish-config') || (! $hasExplicitOptions && $this->confirm(
                '1. Publish the config file? (lets you tweak locking, caching, and audit columns later)',
                true
            ));
            if ($publishConfig) {
                $exit = $this->call('vendor:publish', [
                    '--tag' => 'auto-sequence-config',
                ]);
                if ($exit !== self::SUCCESS) {
                    $this->components->error('Failed to publish configuration file.');

                    return self::FAILURE;
                }
                $this->components->info('Config file published to config/auto-sequence.php.');
            }
        }

        // 2. Publish Migrations
        $publishMigrations = $this->option('publish-migrations') || (! $hasExplicitOptions && $this->confirm(
            '2. Publish the migration files? (only needed if you want to edit the sequence tables yourself — otherwise the package loads them automatically)',
            false
        ));
        if ($publishMigrations) {
            $exit = $this->call('vendor:publish', [
                '--tag' => 'auto-sequence-migrations',
            ]);
            if ($exit !== self::SUCCESS) {
                $this->components->error('Failed to publish migrations.');

                return self::FAILURE;
            }
            $this->components->info('Migrations published to database/migrations.');
        }

        // 3. Run Migrations
        $runMigrations = $this->option('migrate') || (! $hasExplicitOptions && $this->confirm(
            '3. Run the database migrations now? (creates the "sequences" and "sequence_recycled" tables)',
            true
        ));
        if ($runMigrations) {
            $exit = $this->call('migrate');
            if ($exit !== self::SUCCESS) {
                $this->components->error('Database migrations failed.');

                return self::FAILURE;
            }
            $this->components->info('Database migrations completed.');
        }

        $this->newLine();
        $this->components->info('Setup finished! Next: add sequencing to a model.');
        $this->line('    1. Implement the <fg=cyan>AutoSequence</> contract and use the <fg=cyan>HasSequenceNumber</> trait.');
        $this->line('    2. Define <fg=cyan>getSequenceConfig()</> to say which column to fill and how to format it.');
        $this->newLine();
        $this->line('    <fg=gray>use MadeByClowd\AutoSequence\Contracts\AutoSequence;</>');
        $this->line('    <fg=gray>use MadeByClowd\AutoSequence\Traits\HasSequenceNumber;</>');
        $this->newLine();
        $this->line('    <fg=gray>class Invoice extends Model implements AutoSequence</>');
        $this->line('    <fg=gray>{</>');
        $this->line('    <fg=gray>    use HasSequenceNumber;</>');
        $this->newLine();
        $this->line('    <fg=gray>    public function getSequenceConfig(): array</>');
        $this->line('    <fg=gray>    {</>');
        $this->line('    <fg=gray>        return [</>');
        $this->line('    <fg=gray>            \'number\' => [</>');
        $this->line('    <fg=gray>                \'module\' => \'invoice\',</>');
        $this->line('    <fg=gray>                \'type_code\' => \'INV\',</>');
        $this->line('    <fg=gray>                \'format_template\' => \'{type_code}-{YYYY}-{seq:5}\', // INV-2026-00001</>');
        $this->line('    <fg=gray>            ],</>');
        $this->line('    <fg=gray>        ];</>');
        $this->line('    <fg=gray>    }</>');
        $this->line('    <fg=gray>}</>');
        $this->newLine();
        $this->line('    Full guide (all options + examples): https://github.com/madebyclowd/laravel-auto-sequence#readme');

        return self::SUCCESS;
    }
}
