<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use function Laravel\Prompts\progress;

use ErrorException;
use Illuminate\Cache\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Validation\ValidationException;
use Laravel\Prompts\Progress;
use Modules\Core\Overrides\Seeder;
use Modules\Core\Search\Traits\Searchable;
use RuntimeException;
use Throwable;

abstract class BatchSeeder extends Seeder
{
    private const MAX_RETRIES = 3;

    private const BATCHSIZE = 100;

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
        } catch (Throwable $throwable) {
            $this->command->error('Error during seeding: ' . $throwable->getMessage());
            Log::error('Seeding error: ' . $throwable->getMessage(), [
                'exception' => $throwable,
                'trace' => $throwable->getTraceAsString(),
            ]);
        }
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

        $progress = progress('Creating ' . $entity_name, $count_to_create);
        $progress->start();

        $created = 0;
        $batch = 0;
        $effective_batch_size = $batchSize ?? self::BATCHSIZE;

        while ($created < $count_to_create) {
            $remaining = $count_to_create - $created;
            $current_batch_size = min($effective_batch_size, $remaining);

            $this->executeBatch(
                $modelClass,
                $entity_name,
                $batch,
                $current_batch_size,
                $current_count,
                $count_to_create,
                $created,
                $remaining,
                $progress,
            );
            $batch++;
        }

        $progress->finish();

        return $created;
    }

    /**
     * Create the data in parallel batches using fork processes with file communication.
     *
     * @param  class-string<Model>  $modelClass  The model class to create the data for
     * @param  int  $totalCount  The total number of data to create
     * @param  int|null  $batchSize  The size of the batch to create
     * @param  int  $maxParallelCount  The maximum number of parallel processes
     */
    final protected function createInParallelBatches(string $modelClass, int $totalCount, ?int $batchSize = null, int $maxParallelCount = 10): int
    {
        $current_count = $this->countCurrentRecords($modelClass);
        $count_to_create = $this->countToCreate($totalCount, $current_count);
        $entity_name = new $modelClass()->getTable();

        if ($count_to_create <= 0) {
            $this->command->info($entity_name . ' already at target count.');

            return 0;
        }

        $effective_batch_size = $batchSize ?? self::BATCHSIZE;
        $total_batches = (int) ceil($count_to_create / $effective_batch_size);

        $progress = progress('Creating ' . $entity_name . ' (parallel)', $count_to_create);
        $progress->start();

        // Directory temporanea per comunicazione tra processi
        $temp_dir = sys_get_temp_dir() . '/batch_seeder_' . uniqid();
        mkdir($temp_dir, 0755, true);

        try {
            $created = $this->executeParallelBatches(
                $modelClass,
                $count_to_create,
                $effective_batch_size,
                $total_batches,
                $maxParallelCount,
                $temp_dir,
                $progress,
            );

            $progress->finish();

            return $created;
        } finally {
            // Cleanup
            $this->cleanupTempDirectory($temp_dir);
        }
    }

    /**
     * Execute parallel batches using fork processes with file communication.
     */
    private function executeParallelBatches(
        string $modelClass,
        int $count_to_create,
        int $effective_batch_size,
        int $total_batches,
        int $maxParallelCount,
        string $temp_dir,
        Progress $progress,
    ): int {
        $driver = Concurrency::driver('fork');
        $created = 0;
        $batch = 0;
        $active_processes = [];

        while ($created < $count_to_create) {
            // Avvia nuovi processi fino al limite
            while ($maxParallelCount > count($active_processes) && $batch < $total_batches) {
                $remaining = $count_to_create - $created;
                $current_batch_size = min($effective_batch_size, $remaining);

                if ($current_batch_size <= 0) {
                    break;
                }

                $batch_file = $temp_dir . "/batch_{$batch}.json";
                $error_file = $temp_dir . "/error_{$batch}.txt";

                $process_id = $this->startBatchProcess(
                    $modelClass,
                    $current_batch_size,
                    $batch,
                    $batch_file,
                    $error_file,
                    $driver,
                );

                $active_processes[$process_id] = [
                    'batch' => $batch,
                    'batch_file' => $batch_file,
                    'error_file' => $error_file,
                    'batch_size' => $current_batch_size,
                ];

                $batch++;
            }

            // Controlla processi completati
            $this->checkCompletedProcesses($active_processes, $progress, $created);

            // Se non ci sono processi attivi e abbiamo ancora lavoro da fare, c'è un errore
            throw_if($active_processes === [] && $created < $count_to_create, RuntimeException::class, "No active processes but still have work to do. Check error files in {$temp_dir}");

            // Piccola pausa per evitare busy waiting
            Sleep::usleep(100000); // 100ms
        }

        return $created;
    }

    /**
     * Start a batch process using fork driver.
     */
    private function startBatchProcess(
        string $modelClass,
        int $batchSize,
        int $batchNumber,
        string $batchFile,
        string $errorFile,
        $driver,
    ): string {
        $process_id = uniqid("batch_{$batchNumber}_");

        $driver->run([
            function () use ($modelClass, $batchSize, $batchNumber, $batchFile, $errorFile): void {
                try {
                    $result = $this->executeSingleBatch($modelClass, $batchSize);

                    // Scrivi risultato su file
                    file_put_contents($batchFile, json_encode([
                        'batch' => $batchNumber,
                        'created' => $result,
                        'success' => true,
                        'timestamp' => microtime(true),
                    ]));
                } catch (Throwable $e) {
                    // Scrivi errore su file
                    file_put_contents($errorFile, json_encode([
                        'batch' => $batchNumber,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'timestamp' => microtime(true),
                    ]));

                    throw $e;
                }
            },
        ]);

        return $process_id;
    }

    /**
     * Execute a single batch in an isolated process.
     */
    private function executeSingleBatch(string $modelClass, int $batchSize): int
    {
        // Setup connessioni isolate per questo processo
        $this->setupIsolatedConnections($modelClass);

        $model_instance = new $modelClass();

        // Usa connessione isolata
        $model_instance->setConnection($this->getIsolatedConnectionName());

        if ($this->isSearchable($modelClass)) {
            $model_instance->setCacheConnection($this->getIsolatedCacheConnectionName());
        }

        /** @phpstan-ignore staticMethod.notFound */
        $factory = $model_instance->factory();

        /** @var \Illuminate\Database\Eloquent\Factories\Factory<Model> $factory */
        $new_models = $factory->count($batchSize)->create();

        if ($new_models->isNotEmpty() && method_exists($factory, 'createDynamicContentRelations')) {
            $factory->createDynamicContentRelations($new_models);
        }

        return $batchSize;
    }

    /**
     * Check for completed processes and update progress.
     */
    private function checkCompletedProcesses(array &$active_processes, Progress $progress, int &$created): void
    {
        $completed_processes = [];

        foreach ($active_processes as $process_id => $process_info) {
            $batch_file = $process_info['batch_file'];
            $error_file = $process_info['error_file'];
            $batch_number = $process_info['batch'];

            // Controlla se il processo è completato
            if (file_exists($batch_file)) {
                $result = json_decode(file_get_contents($batch_file), true);

                if ($result && $result['success']) {
                    $created += $result['created'];
                    $progress->advance($result['created']);
                    $completed_processes[] = $process_id;

                    // Cleanup file
                    unlink($batch_file);
                }
            } elseif (file_exists($error_file)) {
                $error = json_decode(file_get_contents($error_file), true);

                if ($error) {
                    $progress->hint(sprintf(
                        '<fg=red>Batch %d failed: %s</>',
                        $batch_number + 1,
                        $error['error'],
                    ));
                    $progress->render();

                    throw new RuntimeException("Batch {$batch_number} failed: " . $error['error']);
                }
            }
        }

        // Rimuovi processi completati
        foreach ($completed_processes as $process_id) {
            unset($active_processes[$process_id]);
        }
    }

    /**
     * Setup isolated connections for this process.
     */
    private function setupIsolatedConnections(string $modelClass): void
    {
        $this->setupIsolatedDatabaseConnection();

        if ($this->isSearchable($modelClass)) {
            $this->setupIsolatedCacheConnection();
        }
    }

    /**
     * Setup isolated database connection.
     */
    private function setupIsolatedDatabaseConnection(): void
    {
        $connection_name = $this->getIsolatedConnectionName();
        // Crea connessione isolata
        config([
            'database.connections.' . $connection_name => array_merge(
                config('database.connections.' . config('database.default')),
                ['name' => $connection_name],
            ),
        ]);
        DB::purge();
        DB::setDefaultConnection($connection_name);
        DB::reconnect();
    }

    /**
     * Setup isolated cache connection.
     */
    private function setupIsolatedCacheConnection(): void
    {
        $cache_connection_name = $this->getIsolatedCacheConnectionName();
        // Crea connessione cache isolata
        config([
            'cache.stores.' . $cache_connection_name => array_merge(
                config('cache.stores.' . config('cache.default')),
                ['name' => $cache_connection_name],
            ),
        ]);
        Cache::purge();
    }

    /**
     * Get isolated database connection name.
     */
    private function getIsolatedConnectionName(): string
    {
        return 'isolated_db_' . uniqid() . '_' . getmypid();
    }

    /**
     * Get isolated cache connection name.
     */
    private function getIsolatedCacheConnectionName(): string
    {
        return 'isolated_cache_' . uniqid() . '_' . getmypid();
    }

    /**
     * Cleanup temporary directory.
     */
    private function cleanupTempDirectory(string $temp_dir): void
    {
        if (is_dir($temp_dir)) {
            $files = glob($temp_dir . '/*');

            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($temp_dir);
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

    private function isSearchable(string $modelClass): bool
    {
        return class_uses_trait($modelClass, Searchable::class);
    }

    private function getCacheConnectionName(string $modelClass): string
    {
        if (! $this->isSearchable($modelClass)) {
            return config('cache.default');
        }

        return new $modelClass()->searchableConnection() ?? config('cache.default');
    }

    /**
     * Execute a batch of data creation.
     *
     * @param  class-string<Model>  $modelClass  The model class to create the data for
     * @param  string  $entityName  The name of the entity to create the data for
     * @param  int  $batch  The batch number
     * @param  int  $batchSize  The size of the batch to create
     * @param  int  $currentCount  The current number of records in the database
     * @param  int  $countToCreate  The total number of records to create
     * @param  int  $created  The number of records created so far
     * @param  int  $remaining  The number of records remaining to create
     * @param  Progress  $progress  The progress bar
     * @param  bool  $asyncMode  Whether to run the batch asynchronously
     * @param  callable|null  $force_kill_batches  The function to call if the batch fails
     */
    private function executeBatch(string &$modelClass, string &$entityName, int &$batch, int &$batchSize, int &$currentCount, int &$countToCreate, int &$created, int &$remaining, Progress $progress, bool $asyncMode = false, ?callable $force_kill_batches = null): void
    {
        $retry_count = 0;
        $success = false;

        if ($remaining <= 0) {
            return;
        }

        $connections = [
            'database' => new $modelClass()->getConnectionName() ?? config('database.default'),
            'cache' => $this->getCacheConnectionName($modelClass),
        ];

        while (! $success && $retry_count < self::MAX_RETRIES) {
            try {
                if ($asyncMode) {
                    // Setup fresh connections for async operations
                    $connections = $this->setupAsyncConnections($connections['database'], $connections['cache']);
                }

                $model_instance = new $modelClass();
                $model_instance->setConnection($connections['database']);

                if ($this->isSearchable($modelClass)) {
                    $model_instance->setCacheConnection($connections['cache']);
                }
                $factory = $model_instance->factory();

                /** @var \Illuminate\Database\Eloquent\Factories\Factory<Model> $factory */
                $new_models = $factory->count($batchSize)->create();

                if ($new_models->isNotEmpty() && method_exists($factory, 'createDynamicContentRelations')) {
                    $factory->createDynamicContentRelations($new_models);
                }

                $success = true;
                $created += $batchSize;

                $progress->hint('');
                $progress->advance($batchSize);
            } catch (QueryException|ErrorException|ValidationException $e) {
                if ($force_kill_batches !== null) {
                    $force_kill_batches($e, $batch);

                    return;
                }

                $message = sprintf('Failed to create %s batch {', $entityName) . ($batch + 1) . '}: ' . $e->getMessage();
                Log::error($message);
                $progress->hint("<fg=red>{$message}</>");
                $progress->render();

                throw $e;
            } catch (Throwable $e) {
                $retry_count++;

                if ($retry_count >= self::MAX_RETRIES) {
                    $this->command->error($e->getTraceAsString());

                    if ($force_kill_batches !== null) {
                        $force_kill_batches($e, $batch);

                        return;
                    }

                    $message = sprintf('Failed to create %s batch {', $entityName) . ($batch + 1) . '}: ' . $e->getMessage();
                    Log::error($message);
                    $progress->hint("<fg=red>{$message}</>");
                    $progress->render();

                    throw $e;
                }

                if ($asyncMode) {
                    // Reset connections on retry
                    $this->resetAsyncConnections($connections['database'], $connections['cache']);
                }

                $created_before = $created;
                $created = $this->countCurrentRecords($modelClass) - $currentCount;
                $progress->advance($created - $created_before);
                $message = sprintf('Retry %d for %s batch {', $retry_count, $entityName) . ($batch + 1) . '}: ' . $e->getMessage();
                Log::warning($message);
                $progress->hint("<fg=yellow>{$message}</>");
                $progress->render();

                $remaining = $countToCreate - $created;
                $batchSize = min(self::BATCHSIZE, $remaining);

                Sleep::sleep(self::RETRY_DELAY);
            } finally {
                gc_collect_cycles();
            }
        }
    }

    /**
     * Setup fresh connections for async operations.
     *
     * @return list{database:string,cache:string}
     */
    private function setupAsyncConnections(string $db_connection_name, string $cache_connection_name): array
    {
        return [
            'database' => $this->setupAsyncDatabaseConnection($db_connection_name),
            'cache' => $this->setupAsyncCacheConnection($cache_connection_name),
        ];
    }

    /**
     * Setup a fresh database connection for async operations.
     */
    private function setupAsyncDatabaseConnection(string $connection_name): string
    {
        // Disable prepared statements for better async performance
        config([sprintf('database.connections.%s.prepared_statements', $connection_name) => false]);

        // Create a new connection with unique name
        $tmp_connection_name = 'async_db_' . uniqid();
        config([
            'database.connections.' . $tmp_connection_name => config('database.connections.' . $connection_name),
        ]);

        // Set the new connection as default
        DB::purge();
        DB::setDefaultConnection($tmp_connection_name);
        DB::reconnect();

        return $tmp_connection_name;
    }

    /**
     * Setup a fresh Redis connection for async operations.
     */
    private function setupAsyncCacheConnection(string $cache_connection_name): string
    {
        // Create a unique Redis connection for this process
        $tmp_cache_connection_name = 'async_cache_' . uniqid();
        $cache_config = config('cache.stores.' . $cache_connection_name);

        // Copy the default Redis configuration
        config([
            'cache.stores.' . $tmp_cache_connection_name => $cache_config,
        ]);

        // Clear and reconnect Redis
        Cache::purge();

        return $tmp_cache_connection_name;
    }

    /**
     * Reset all connections for async operations.
     */
    private function resetAsyncConnections(string $db_connection_name, string $cache_connection_name): void
    {
        // Reset database connection
        $this->resetAsyncDatabaseConnection($db_connection_name);

        // Reset Redis connection
        $this->resetAsyncCacheConnection($cache_connection_name);
    }

    /**
     * Reset the database connection for async operations.
     */
    private function resetAsyncDatabaseConnection(string $connection_name): void
    {
        // Close current connection
        $current_connection = DB::connection($connection_name);
        $current_connection->disconnect();

        // Reconnect with fresh connection
        DB::reconnect($connection_name);
    }

    /**
     * Reset the Redis connection for async operations.
     */
    private function resetAsyncCacheConnection(string $connection_name): void
    {
        // Close current cache connection
        /** @var Repository $store */
        $store = Cache::store($connection_name);
        $cache_connection = $store->getConnection();
        $cache_connection->disconnect();

        // Reconnect cache
        $cache_connection->connect();
    }
}
