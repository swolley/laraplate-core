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
use ReflectionClass;
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
            $this->command->error('Error during seeding: ' . $throwable->getMessage() . ' in ' . $throwable->getFile() . ' on line ' . $throwable->getLine() . PHP_EOL . $throwable->getTraceAsString());
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
        $entity_name = new ReflectionClass($modelClass)->newInstanceWithoutConstructor()->getTable();

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
        // Calcolare il numero ideale di processi paralleli ("maxParallelCount") dipende principalmente dal numero di CPU core disponibili,
        // ma vanno considerate anche la quantità di RAM e la natura del carico (CPU bound vs. IO bound).
        // Per la maggior parte degli scenari CPU bound (come la generazione intensiva di dati), conviene eseguire un processo parallelo per ogni core.
        // In PHP puoi rilevare il numero di core in modo portabile e adattare "maxParallelCount" automaticamente. Ad esempio:

        $safe_max_parallel_count = $this->getMaxParallelCount($maxParallelCount);

        if ($safe_max_parallel_count <= $maxParallelCount) {
            $this->command->newLine();
            $this->command->info('Safely reduced max parallel count to ' . $safe_max_parallel_count . ' because the number of CPU cores is less than expected.');
            $this->command->newLine();
        }

        $current_count = $this->countCurrentRecords($modelClass);
        $count_to_create = $this->countToCreate($totalCount, $current_count);
        $entity_name = new ReflectionClass($modelClass)->newInstanceWithoutConstructor()->getTable();

        if ($count_to_create <= 0) {
            $this->command->info($entity_name . ' already at target count.');

            return 0;
        }

        $effective_batch_size = $batchSize ?? self::BATCHSIZE;
        $total_batches = (int) ceil($count_to_create / $effective_batch_size);

        $progress = progress('Creating ' . $entity_name . ' (parallel)', $count_to_create);
        $progress->hint("Using {$safe_max_parallel_count} parallel processes, {$effective_batch_size} records per batch, {$total_batches} total batches");
        $progress->start();

        // Temporary directory for inter-process communication
        $temp_dir = sys_get_temp_dir() . '/batch_seeder_' . uniqid();
        mkdir($temp_dir, 0755, true);

        // Shared file for total progress
        $progress_file = $temp_dir . '/progress.json';
        $progress_lock_file = $temp_dir . '/progress.lock';

        // Initialize the progress file
        file_put_contents($progress_file, json_encode(['total_created' => 0]));

        try {
            $created = $this->executeParallelBatches(
                $modelClass,
                $count_to_create,
                $effective_batch_size,
                $total_batches,
                $safe_max_parallel_count,
                $progress,
                $progress_file,
                $progress_lock_file,
            );

            $progress->finish();

            return $created;
        } finally {
            // Cleanup
            $this->cleanupTempDirectory($temp_dir);
        }
    }

    private function getCpuCores(): int
    {
        if (function_exists('shell_exec') && str_contains(PHP_OS_FAMILY, 'Linux')) {
            return (int) shell_exec('nproc') ?: 1;
        }

        if (function_exists('shell_exec') && str_contains(PHP_OS_FAMILY, 'Darwin')) {
            return (int) shell_exec('sysctl -n hw.ncpu') ?: 1;
        }

        if (function_exists('shell_exec') && str_contains(PHP_OS_FAMILY, 'Windows')) {
            return (int) getenv('NUMBER_OF_PROCESSORS') ?: 1;
        }

        return 1;
    }

    private function getMaxParallelCount(int $maxParallelCount): int
    {
        $cpu_cores = $this->getCpuCores();

        // Heuristic: keep some margin for other system activities; don't exceed the CPU core count.
        return min($maxParallelCount, max(1, $cpu_cores - 1));
    }

    /**
     * Execute parallel batches using Concurrency facade with grouped batches.
     *
     * Note: Concurrency::run() is blocking and waits for all closures to complete.
     * We group batches into chunks of maxParallelCount, execute them in parallel,
     * wait for completion, then proceed to the next chunk. This approach balances
     * parallelism with memory management through garbage collection between chunks.
     */
    private function executeParallelBatches(
        string $modelClass,
        int $count_to_create,
        int $effective_batch_size,
        int $total_batches,
        int $maxParallelCount,
        Progress $progress,
        string $progress_file,
        string $progress_lock_file,
    ): int {
        $driver = Concurrency::driver('fork');
        $batch = 0;
        $last_progress = 0;
        $total_groups = (int) ceil($total_batches / $maxParallelCount);
        $current_group = 0;

        // Statistics for weighted average calculation
        $total_time_weighted = 0.0; // Sum of (time_per_batch * records_in_batch)
        $total_records_processed = 0; // Sum of all records processed so far

        while ($batch < $total_batches) {
            $current_group++;
            // $group_start_time = microtime(true);
            // Collect batches for parallel execution (grouped approach for memory efficiency)
            $batches_to_run = [];
            $batch_info = [];

            while ($maxParallelCount > count($batches_to_run) && $batch < $total_batches) {
                $remaining = $count_to_create - $last_progress;
                $current_batch_size = min($effective_batch_size, $remaining);

                if ($current_batch_size <= 0) {
                    break;
                }

                // Create closure for this batch with timing
                $batches_to_run["batch_{$batch}"] = function () use ($modelClass, $current_batch_size, $batch, $progress_file, $progress_lock_file): array {
                    $batch_start_time = microtime(true);

                    try {
                        $result = $this->executeSingleBatch($modelClass, $current_batch_size);

                        $db_complete_time = microtime(true);
                        $db_duration = $db_complete_time - $batch_start_time;

                        // Time the file locking operation
                        $file_lock_start = microtime(true);
                        $this->incrementProgressFile($progress_file, $progress_lock_file, $result);
                        $file_lock_time = microtime(true) - $file_lock_start;

                        $batch_end_time = microtime(true);
                        $batch_duration = $batch_end_time - $batch_start_time;

                        return [
                            'batch' => $batch,
                            'created' => $result,
                            'success' => true,
                            'duration' => $batch_duration,
                            'db_duration' => $db_duration,
                            'file_lock_duration' => $file_lock_time,
                            'timestamp' => microtime(true),
                        ];
                    } catch (Throwable $e) {
                        return [
                            'created' => $result ?? 0,
                            'duration' => $batch_duration ?? 0,
                            'db_duration' => $db_duration ?? 0,
                            'file_lock_duration' => $file_lock_time ?? 0,
                            'batch' => $batch,
                            'error' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'trace' => $e->getTraceAsString(),
                            'timestamp' => microtime(true),
                            'success' => false,
                        ];
                    }
                };

                $batch++;
            }

            if ($batches_to_run === []) {
                break;
            }

            // Execute all batches in parallel using Concurrency
            // try {
            $results = $driver->run($batches_to_run);

            // Update progressbar based on results and collect timing statistics
            foreach ($results as $result) {
                if (! $result['success']) {
                    $this->command->error("Batch {$result['batch']} failed: " . $result['error'] . ' in ' . $result['file'] . ' on line ' . $result['line']);
                    $this->command->error($result['trace']);

                    exit();
                }

                if (is_array($result) && isset($result['created']) && $result['created'] > 0) {
                    $records_created = $result['created'];
                    $batch_duration = $result['duration'] ?? 0;

                    // Update weighted average: sum(time * records) / sum(records)
                    $total_time_weighted += $batch_duration * $records_created;
                    $total_records_processed += $records_created;

                    $progress->advance($records_created);
                    $last_progress += $records_created;
                } elseif (is_int($result) && $result > 0) {
                    // Fallback for old format
                    $progress->advance($result);
                    $last_progress += $result;
                }
            }

            // Calculate weighted average time per record
            $weighted_avg_time_per_record = $total_records_processed > 0
                ? $total_time_weighted / $total_records_processed
                : 0;

            // Update progressbar hint with statistics
            $this->updateProgressHint(
                $progress,
                $current_group,
                $total_groups,
                $weighted_avg_time_per_record,
                $total_records_processed,
                $count_to_create,
            );
            // } catch (Throwable $e) {
            //     // Check for individual batch errors
            //     foreach ($batch_info as $batch_num => $info) {
            //         if (file_exists($info['error_file'])) {
            //             $error = json_decode(file_get_contents($info['error_file']), true);

            //             if ($error) {
            //                 throw new RuntimeException("Batch {$batch_num} failed: " . $error['error']);
            //             }
            //         }
            //     }

            //     throw $e;
            // }

            // Update progressbar from shared file (in case some updates were missed)
            $current_progress = $this->readProgressFile($progress_file, $progress_lock_file);

            if ($current_progress > $last_progress) {
                $increment = $current_progress - $last_progress;
                $progress->advance($increment);
                $last_progress = $current_progress;

                // Update hint with current progress (if we have statistics)
                if ($total_records_processed > 0 && $weighted_avg_time_per_record > 0) {
                    $this->updateProgressHint(
                        $progress,
                        $current_group,
                        $total_groups,
                        $weighted_avg_time_per_record,
                        $current_progress,
                        $count_to_create,
                    );
                }
            }

            // Clean up memory after each group of batches
            unset($batches_to_run, $batch_info, $results);
            gc_collect_cycles();
        }

        // Read the final progress
        $final_progress = $this->readProgressFile($progress_file, $progress_lock_file);

        if ($final_progress > $last_progress) {
            $progress->advance($final_progress - $last_progress);
        }

        return $final_progress;
    }

    /**
     * Update the progressbar hint with statistics.
     */
    private function updateProgressHint(
        Progress $progress,
        int $current_group,
        int $total_groups,
        float $weighted_avg_time_per_record,
        int $total_records_processed,
        int $count_to_create,
    ): void {
        $hint_parts = [];

        // Group information
        $hint_parts[] = sprintf('Group %d/%d', $current_group, $total_groups);

        // Weighted average time per record
        if ($weighted_avg_time_per_record > 0 && $total_records_processed > 0) {
            $time_per_record_ms = $weighted_avg_time_per_record * 1000;

            if ($time_per_record_ms < 1) {
                $hint_parts[] = sprintf('~%.2f μs/record', $time_per_record_ms * 1000);
            } elseif ($time_per_record_ms < 1000) {
                $hint_parts[] = sprintf('~%.2f ms/record', $time_per_record_ms);
            } else {
                $hint_parts[] = sprintf('~%.2f s/record', $time_per_record_ms / 1000);
            }
        }

        // Estimated time remaining (if we have enough data)
        if ($weighted_avg_time_per_record > 0 && $total_records_processed > 0 && $total_records_processed < $count_to_create) {
            $remaining_records = $count_to_create - $total_records_processed;
            $estimated_seconds = $weighted_avg_time_per_record * $remaining_records;

            if ($estimated_seconds < 60) {
                $hint_parts[] = sprintf('ETA: ~%.0f s', $estimated_seconds);
            } elseif ($estimated_seconds < 3600) {
                $hint_parts[] = sprintf('ETA: ~%.1f min', $estimated_seconds / 60);
            } else {
                $hint_parts[] = sprintf('ETA: ~%.1f h', $estimated_seconds / 3600);
            }
        }

        $progress->hint(implode(' | ', $hint_parts));
        $progress->render();
    }

    /**
     * Execute a single batch in an isolated process.
     * In a forked process, each process already has isolated memory,
     * so we can use the default connections without conflicts.
     */
    private function executeSingleBatch(string $modelClass, int $batchSize): int
    {
        // In a forked process, each process already has isolated memory
        // So we can use the default connections without issues
        // Just make sure the connections are fresh
        DB::reconnect();
        Cache::flush();

        $model_instance = new $modelClass();

        /** @phpstan-ignore staticMethod.notFound */
        $factory = $model_instance->factory();

        /** @var \Illuminate\Database\Eloquent\Factories\Factory<Model> $factory */
        $new_models = $factory->count($batchSize)->create();

        if ($new_models->isNotEmpty() && method_exists($factory, 'createDynamicContentRelations')) {
            $factory->createDynamicContentRelations($new_models);
        }

        // Clean up memory in child process
        unset($new_models, $factory, $model_instance);
        gc_collect_cycles();

        return $batchSize;
    }

    /**
     * Increment the shared progress file atomically.
     */
    private function incrementProgressFile(string $progressFile, string $lockFile, int $increment): void
    {
        $lock = fopen($lockFile, 'c+');

        throw_if($lock === false, RuntimeException::class, "Unable to create lock file: {$lockFile}");

        // Try to acquire exclusive lock (non-blocking)
        $attempts = 0;
        $max_attempts = 100; // 10 seconds max wait

        while (! flock($lock, LOCK_EX | LOCK_NB)) {
            $attempts++;

            if ($attempts >= $max_attempts) {
                fclose($lock);

                throw new RuntimeException("Unable to acquire lock on progress file after {$max_attempts} attempts");
            }

            Sleep::usleep(100000); // 100ms
        }

        try {
            // Read current progress
            $current_data = ['total_created' => 0];

            if (file_exists($progressFile)) {
                $content = file_get_contents($progressFile);

                if ($content !== false) {
                    $decoded = json_decode($content, true);

                    if (is_array($decoded)) {
                        $current_data = $decoded;
                    }
                }
            }

            // Increment progress
            $current_data['total_created'] += $increment;

            // Write back
            file_put_contents($progressFile, json_encode($current_data), LOCK_EX);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Read the current progress from the shared file.
     */
    private function readProgressFile(string $progressFile, string $lockFile): int
    {
        if (! file_exists($progressFile)) {
            return 0;
        }

        $lock = fopen($lockFile, 'c+');

        if ($lock === false) {
            // If we can't acquire the lock, read anyway (read-only)
            $content = file_get_contents($progressFile);

            if ($content === false) {
                return 0;
            }

            $decoded = json_decode($content, true);

            return is_array($decoded) && isset($decoded['total_created']) ? (int) $decoded['total_created'] : 0;
        }

        // Try to acquire shared lock (non-blocking)
        if (flock($lock, LOCK_SH | LOCK_NB)) {
            try {
                $content = file_get_contents($progressFile);

                if ($content === false) {
                    return 0;
                }

                $decoded = json_decode($content, true);

                return is_array($decoded) && isset($decoded['total_created']) ? (int) $decoded['total_created'] : 0;
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }

        fclose($lock);

        // Fallback: read without lock
        $content = file_get_contents($progressFile);

        if ($content === false) {
            return 0;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) && isset($decoded['total_created']) ? (int) $decoded['total_created'] : 0;
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
