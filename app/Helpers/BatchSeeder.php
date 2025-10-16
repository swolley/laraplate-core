<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use ErrorException;

use function Laravel\Prompts\progress;
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
use Modules\Cms\Database\Factories\DynamicContentFactory;
use Modules\Core\Overrides\Seeder;
use Modules\Core\Search\Traits\Searchable;
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
        } catch (Throwable $e) {
            $this->command->error('Error during seeding: ' . $e->getMessage());
            Log::error('Seeding error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
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

        $driver = Concurrency::driver('fork');

        /** @var Throwable|null $force_kill_all_batches */
        $force_kill_all_batches = null;

        $propagate_exception = function (Throwable $e) use (&$force_kill_all_batches, &$progress, &$entity_name): void {
            $force_kill_all_batches = $e;
            $progress->hint("<fg=red>Failed to create {$entity_name} batch {" . (1) . '}: ' . $e->getMessage() . '</>');
            $progress->render();

            exit(1);
        };

        while (! $force_kill_all_batches instanceof Throwable && $created < $count_to_create) {
            $remaining = max(0, $count_to_create - count($concurrencies) * $batchSize);
            $batchSize = min(self::BATCHSIZE, $remaining);

            $concurrencies[] = fn () => $this->executeBatch($modelClass, $entity_name, $batch, $batchSize, $current_count, $count_to_create, $created, $remaining, $progress, true, $propagate_exception);
            $batch++;

            if ($maxParallelCount <= count($concurrencies)) {
                $driver->run($concurrencies);
                $concurrencies = [];
            }
        }

        throw_if($force_kill_all_batches instanceof Throwable, $force_kill_all_batches);

        if ($concurrencies !== []) {
            $driver->run($concurrencies);
        }

        $progress->finish();

        return $created;
    }

    private function countCurrentRecords(string $modelClass): int
    {
        return $modelClass::count();
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
     * @param  string  $entity_name  The name of the entity to create the data for
     * @param  int  $batch  The batch number
     * @param  int  $batchSize  The size of the batch to create
     * @param  int  $currentCount  The current number of records in the database
     * @param  int  $countToCreate  The total number of records to create
     * @param  int  $created  The number of records created so far
     * @param  int  $remaining  The number of records remaining to create
     * @param  Progress  $progress  The progress bar
     * @param  bool  $asyncMode  Whether to run the batch asynchronously
     */
    private function executeBatch(string &$modelClass, string &$entity_name, int &$batch, int &$batchSize, int &$currentCount, int &$countToCreate, int &$created, int &$remaining, Progress $progress, bool $asyncMode = false, ?callable $force_kill_batches = null): void
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

                {
                    $model_instance = new $modelClass();
                    $model_instance->setConnection($connections['database']);
    
                    if ($this->isSearchable($modelClass)) {
                        $model_instance->setCacheConnection($connections['cache']);
                    }
    
                    /** @phpstan-ignore staticMethod.notFound */
                    $factory = $model_instance->factory();
    
                    /** @var \Illuminate\Database\Eloquent\Factories\Factory<Model> $factory */
                    $new_models = $factory->count($batchSize)->create();
    
                    if ($new_models->isNotEmpty() && $factory instanceof DynamicContentFactory) {
                        $factory->createRelations($new_models);
                    }

                }

                $success = true;
                $created += $batchSize;

                $progress->hint('');
                $progress->advance($batchSize);
            } catch (QueryException|ErrorException|ValidationException $e) {
                $progress->hint("<fg=red>Failed to create {$entity_name} batch {" . ($batch + 1) . '}: ' . $e->getMessage() . '</>');
                $progress->render();

                if ($force_kill_batches) {
                    $force_kill_batches($e);

                    return;
                }

                throw $e;
            } catch (Throwable $e) {
                $retry_count++;

                if ($retry_count >= self::MAX_RETRIES) {
                    $progress->hint("<fg=red>Failed to create {$entity_name} batch {" . ($batch + 1) . '}: ' . $e->getMessage() . '</>');
                    $progress->render();

                    $this->command->error($e->getTraceAsString());

                    if ($force_kill_batches) {
                        $force_kill_batches($e);

                        return;
                    }

                    throw $e;
                }

                if ($asyncMode) {
                    // Reset connections on retry
                    $this->resetAsyncConnections($connections['database'], $connections['cache']);
                }

                $created_before = $created;
                $created = $this->countCurrentRecords($modelClass) - $currentCount;
                $progress->advance($created - $created_before);
                $progress->hint("<fg=yellow>Retry {$retry_count} for {$entity_name} batch {" . ($batch + 1) . '}: ' . $e->getMessage() . '</>');
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
        config(["database.connections.{$connection_name}.prepared_statements" => false]);

        // Create a new connection with unique name
        $tmp_connection_name = 'async_db_' . uniqid();
        config([
            "database.connections.{$tmp_connection_name}" => config("database.connections.{$connection_name}"),
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
            "cache.stores.{$tmp_cache_connection_name}" => $cache_config,
        ]);

        // Clear and reconnect Redis
        Cache::purge();
        // /** @var Repository $store */
        // $store = Cache::store($tmp_cache_connection_name);
        // $connection = $store->getStore()->getConnection();
        // $connection->disconnect();
        // $connection->connect();

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
