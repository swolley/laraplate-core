<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Exception;

use function Laravel\Prompts\progress;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Prompts\Progress;
use Modules\Core\Overrides\Seeder;

abstract class BatchSeeder extends Seeder
{
    private const BATCHSIZE = 10;

    private const MAX_RETRIES = 3;

    private const RETRY_DELAY = 1; // seconds

    /**
     * Put the logic of the seeder here.
     */
    abstract protected function execute(): void;

    /**
     * Run the seeder.
     */
    final public function run(): void
    {
        try {
            $this->execute();
            $this->command->newLine();
            $this->command->info('All data seeded successfully!');
        } catch (Exception $e) {
            $this->command->error('Error during seeding: ' . $e->getMessage());
            Log::error('Seeding error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function countCurrentRecords(string $modelClass): int
    {
        return $modelClass::query()->withoutGlobalScopes()->count();
    }

    private function countToCreate(int $totalCount, int $currentCount): int
    {
        $currentCount = $this->command->option('force') ? 0 : $currentCount;

        return $totalCount - $currentCount;
    }

    /**
     * Create the data in batches.
     *
     * @param  class-string<Model>  $modelClass  The model class to create the data for
     * @param  int  $totalCount  The total number of data to create
     * @param  int|null  $batchSize  The size of the batch to create
     */
    final protected function createInBatches(string $modelClass, int $totalCount, ?int $batchSize = null): int
    {
        $current_count = $this->countCurrentRecords($modelClass);
        $count_to_create = $this->countToCreate($totalCount, $current_count);
        $entity_name = new $modelClass()->getTable();

        if ($count_to_create <= 0) {
            $this->command->info($entity_name . ' already at target count.');

            return 0;
        }

        $progress = progress("Creating {$entity_name}", $count_to_create);
        $progress->start();

        $created = 0;
        $batch = 0;
        while ($created < $count_to_create) {
            $remaining = $count_to_create - $created;
            $batchSize = min(self::BATCHSIZE, $remaining);

            $this->executeBatch($modelClass, $entity_name, $batch, $batchSize, $current_count, $count_to_create, $created, $remaining, $progress);
            $batch++;
        }

        $progress->finish();

        return $created;
    }

    final protected function createInParallelBatches(string $modelClass, int $totalCount, ?int $batchSize = null, int $maxParallelCount = 10): int
    {
        $current_count = $this->countCurrentRecords($modelClass);
        $count_to_create = $this->countToCreate($totalCount, $current_count);
        $entity_name = new $modelClass()->getTable();

        if ($count_to_create <= 0) {
            $this->command->info($entity_name . ' already at target count.');

            return 0;
        }


        $progress = progress("Creating {$entity_name}", $count_to_create);
        $progress->start();

        $created = 0;
        $batch = 0;
        $concurrencies = [];
        while ($created < $count_to_create) {
            $remaining = max(0, $count_to_create - $created - count($concurrencies) * $batchSize);
            $batchSize = min(self::BATCHSIZE, $remaining);

            $concurrencies[] = fn() => $this->executeBatch($modelClass, $entity_name, $batch, $batchSize, $current_count, $count_to_create, $created, $remaining, $progress, true);
            $batch++;

            if (count($concurrencies) >= $maxParallelCount) {
                Concurrency::driver('fork')->run($concurrencies);
                $concurrencies = [];
            }
        }

        if ($concurrencies !== []) {
            Concurrency::run($concurrencies);
        }

        $progress->finish();

        return $created;
    }

    private function executeBatch(string &$modelClass, string &$entity_name, int &$batch, int &$batchSize, int &$currentCount, int &$countToCreate, int &$created, int &$remaining, Progress $progress, bool $asyncMode = false): void
    {
        $retry_count = 0;
        $success = false;

        if ($remaining <= 0) {
            return;
        }

        while (! $success && $retry_count < self::MAX_RETRIES) {
            try {
                if ($asyncMode) {
                    // Disable prepared statements for this connection
                    config(['database.connections.pgsql.prepared_statements' => false]);

                    // Purge and reconnect to apply the new configuration
                    DB::purge();
                    DB::reconnect();

                    // Force a new connection for this process
                    $connection = DB::connection();
                    $connection->disconnect();
                    $connection->reconnect();
                }

                /** @var \Illuminate\Database\Eloquent\Factories\Factory<Model> $factory */
                /** @phpstan-ignore staticMethod.notFound */
                $factory = $modelClass::factory();
                $factory->count($batchSize)->create();

                $success = true;
                $created += $batchSize;

                $progress->hint('');
                $progress->advance($batchSize);
            } catch (QueryException $e) {
                $progress->hint("<fg=red>Failed to create {$entity_name} batch {" . ($batch + 1) . '}: ' . $e->getMessage() . '</>');
                $progress->render();

                throw $e;
            } catch (ValidationException $e) {
                $progress->hint("<fg=red>Failed to create {$entity_name} batch {" . ($batch + 1) . '}: ' . $e->getMessage() . '</>');
                $progress->render();

                throw $e;
            } catch (Exception $e) {
                $retry_count++;

                if ($retry_count >= self::MAX_RETRIES) {
                    $progress->hint("<fg=red>Failed to create {$entity_name} batch {" . ($batch + 1) . '}: ' . $e->getMessage() . '</>');
                    $progress->render();

                    $this->command->error($e->getTraceAsString());

                    throw $e;
                }

                if ($asyncMode) {
                    // Force a new connection on retry
                    DB::purge();
                    DB::reconnect();
                    $connection = DB::connection();
                    $connection->disconnect();
                    $connection->reconnect();
                }

                $created_before = $created;
                $created = $this->countCurrentRecords($modelClass) - $currentCount;
                $progress->advance($created - $created_before);
                $progress->hint("<fg=yellow>Retry {$retry_count} for {$entity_name} batch {" . ($batch + 1) . '}: ' . $e->getMessage() . '</>');
                $progress->render();

                $remaining = $countToCreate - $created;
                /** @var int $batchSize */
                $batchSize = min(self::BATCHSIZE, $remaining);

                sleep(self::RETRY_DELAY);
            }
        }
    }
}
