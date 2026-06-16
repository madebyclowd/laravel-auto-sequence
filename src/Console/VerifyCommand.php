<?php

namespace MadeByClowd\Sequenceable\Console;

use Illuminate\Console\Command;
use MadeByClowd\Sequenceable\Facades\Sequence;

class VerifyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sequence:verify
                            {model : The fully qualified Eloquent model class to verify (e.g. "App\Models\Invoice")}
                            {column : The column containing the sequence number (e.g. "number")}
                            {--module= : The module name (defaults to model table)}
                            {--type=GEN : The type code (defaults to GEN)}
                            {--period= : The period to check (defaults to current Ym)}
                            {--scope=default : The scope to check}
                            {--repair : Automatically update database counter to match highest model number}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify and repair database sequence counters against actual model records';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $modelClass = $this->argument('model');
        $column = $this->argument('column');
        $repair = $this->option('repair');

        if (! class_exists($modelClass)) {
            $this->components->error("Model class '{$modelClass}' does not exist.");
            return self::FAILURE;
        }

        $model = new $modelClass;
        $module = $this->option('module') ?: $model->getTable();
        $type = $this->option('type');
        $period = $this->option('period') ?: now()->format('Ym');
        $scope = $this->option('scope');

        $this->components->info("Scanning '{$modelClass}' records for column '{$column}'...");

        // Fetch all values of this column
        $values = $modelClass::query()
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->pluck($column);

        if ($values->isEmpty()) {
            $this->components->info("No records found with sequence values in '{$modelClass}'.");
            return self::SUCCESS;
        }

        // Extract numbers from the strings (looks for digits at the end of string or right before non-digits)
        $maxNumber = 0;
        foreach ($values as $val) {
            if (preg_match('/(\d+)(?:\D*)$/', $val, $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxNumber) {
                    $maxNumber = $num;
                }
            }
        }

        $currentDbNumber = Sequence::getCurrent($module, $type, $period, $scope);

        $this->info("Highest sequence number found in model records: {$maxNumber}");
        $this->info("Current sequence counter in database: {$currentDbNumber}");

        if ($maxNumber > $currentDbNumber) {
            $drift = $maxNumber - $currentDbNumber;
            $this->components->warn("Drift detected! Database counter is behind by {$drift} counts.");

            if ($repair) {
                Sequence::reset($module, $type, $period, $scope, $maxNumber);
                $this->components->info("Successfully repaired database sequence counter to {$maxNumber}.");
            } else {
                $this->components->warn("Run with '--repair' option to automatically align the database sequence counter.");
            }
        } else {
            $this->components->info("Sequence is verified and in sync!");
        }

        return self::SUCCESS;
    }
}
