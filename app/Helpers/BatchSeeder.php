<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use function Laravel\Prompts\progress;

use ErrorException;
use Illuminate\Cache\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Validation\ValidationException;
use Laravel\Prompts\Progress;
use Modules\Core\Concurrency\BatchTask;
use Modules\Core\Concurrency\ErrorPolicy;
use Modules\Core\Concurrency\Exceptions\BatchExecutionFailedException;
use Modules\Core\Concurrency\ParallelTaskRunner;
use Modules\Core\Concurrency\Reporters\ProgressBarReporter;
use Modules\Core\Overrides\Seeder;
use Modules\Core\Search\Traits\Searchable;
use ReflectionClass;
use Throwable;

abstract class BatchSeeder extends Seeder
{
    private const int MAX_RETRIES = 3;

    private const int BATCHSIZE = 100;

    private const int RETRY_DELAY = 1; // seconds

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
     * Hook executed once inside each forked child process before the batch task runs.
     *
     * The default implementation only reconnects to the database (PDO links
     * inherited from the parent are not safe to reuse after a fork). Override
     * in your seeder if you need additional resets, e.g. cache, queue, or
     * search engine clients. Avoid destructive operations like Cache::flush()
     * which would truncate shared stores (Redis FLUSHDB).
     */
    protected function bootstrapChildProcess(): void
    {
        DB::reconnect();
    }

    /**
     * Create the data in batches sequentially.
     *
     * @param  class-string<Model>  $modelClass  The model class to create the data for
     * @param  int  $totalCount  The total number of data to create
     * @param  int|null  $batchSize  The size of the batch to create
     */
    final protected function createInBatches(
        string $modelClass,
        int $totalCount,
        ?int $batchSize = null,
    ): int {
        $current_count = $this->countCurrentRecords($modelClass);
        $count_to_create = $this->countToCreate($totalCount, $current_count);
        $entity_name = new ReflectionClass($modelClass)->newInstanceWithoutConstructor()->getTable();

        if ($count_to_create <= 0) {
            $this->command->info($entity_name . ' already at target count.');

            return 0;
        }

        $start_time = microtime(true);

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

        if ($created > 0) {
            $progress->label("Successfully created {$created} {$entity_name} records in " . microtime(true) - $start_time . ' seconds');
            $progress->render();
            Sleep::usleep(250_000);
        }

        $progress->finish();

        return $created;
    }

    /**
     * Create the data in parallel batches using the ParallelTaskRunner worker pool.
     *
     * @param  class-string<Model>  $modelClass  The model class to create the data for
     * @param  int  $totalCount  The total number of data to create
     * @param  int|null  $batchSize  The size of the batch to create
     * @param  int  $maxParallelCount  The maximum number of concurrent worker processes
     */
    final protected function createInParallelBatches(
        string $modelClass,
        int $totalCount,
        ?int $batchSize = null,
        int $maxParallelCount = 10,
    ): int {
        $current_count = $this->countCurrentRecords($modelClass);
        $count_to_create = $this->countToCreate($totalCount, $current_count);
        $entity_name = new ReflectionClass($modelClass)->newInstanceWithoutConstructor()->getTable();
        $db_connection_name = new $modelClass()->getConnectionName() ?? config('database.default');

        if ($count_to_create <= 0) {
            $this->command->info($entity_name . ' already at target count.');

            return 0;
        }

        $effective_batch_size = $batchSize ?? self::BATCHSIZE;
        $total_batches = (int) ceil($count_to_create / $effective_batch_size);

        $tasks = $this->buildSeederTasks($modelClass, $count_to_create, $effective_batch_size, $total_batches);

        if ($tasks === []) {
            return 0;
        }

        $reporter = new ProgressBarReporter(
            label: 'Creating ' . $entity_name . ' (parallel)',
            extraHint: "Up to {$maxParallelCount} workers, {$effective_batch_size} records/batch, {$total_batches} batches",
        );

        try {
            $summary = ParallelTaskRunner::make()
                ->concurrent($maxParallelCount)
                ->withResourceSizing($db_connection_name, $effective_batch_size)
                ->errorPolicy(ErrorPolicy::FailFast)
                ->reportTo($reporter)
                ->beforeChild(fn () => $this->bootstrapChildProcess())
                ->run($tasks);
        } catch (BatchExecutionFailedException $e) {
            $error = $e->outcome->error;
            $message = $error['message'] ?? 'unknown error';
            $file = $error['file'] ?? '?';
            $line = (int) ($error['line'] ?? 0);

            $this->command->error("Task {$e->outcome->taskId} failed: {$message} in {$file} on line {$line}");

            if (($trace = $e->trace()) !== '') {
                $this->command->error($trace);
            }

            Log::error('BatchSeeder parallel run failed', [
                'task_id' => $e->outcome->taskId,
                'error' => $error,
            ]);

            exit(1);
        }

        return $summary->totalUnitsProcessed;
    }

    /**
     * Build the BatchTask list for parallel seeding.
     *
     * @param  class-string<Model>  $modelClass
     * @return list<BatchTask>
     */
    private function buildSeederTasks(
        string $modelClass,
        int $count_to_create,
        int $effective_batch_size,
        int $total_batches,
    ): array {
        $tasks = [];
        $remaining = $count_to_create;

        for ($i = 0; $i < $total_batches; $i++) {
            $size = min($effective_batch_size, $remaining);

            if ($size <= 0) {
                break;
            }

            $remaining -= $size;

            $tasks[] = new BatchTask(
                id: "batch_{$i}",
                units: $size,
                run: fn (): int => $this->executeSingleBatch($modelClass, $size),
            );
        }

        return $tasks;
    }

    /**
     * Execute a single batch in an isolated forked process.
     *
     * Connection bootstrap is centralised in bootstrapChildProcess(), invoked
     * once per fork by ParallelTaskRunner via beforeChild().
     */
    private function executeSingleBatch(string $modelClass, int $batchSize): int
    {
        $model_instance = new $modelClass();

        /** @phpstan-ignore staticMethod.notFound */
        $factory = $model_instance->factory();

        /** @var \Illuminate\Database\Eloquent\Factories\Factory<Model> $factory */
        $new_models = $factory->count($batchSize)->create();

        if ($new_models->isNotEmpty() && method_exists($factory, 'createDynamicContentRelations')) {
            $factory->createDynamicContentRelations($new_models);
        }

        unset($new_models, $factory, $model_instance);
        gc_collect_cycles();

        return $batchSize;
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
     * Execute a batch of data creation in the synchronous path.
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
     * @param  bool  $asyncMode  Whether to run the batch with isolated async connections
     * @param  callable|null  $force_kill_batches  Optional callback invoked on failure
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
        config([sprintf('database.connections.%s.prepared_statements', $connection_name) => false]);

        $tmp_connection_name = 'async_db_' . uniqid();
        config([
            'database.connections.' . $tmp_connection_name => config('database.connections.' . $connection_name),
        ]);

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
        $tmp_cache_connection_name = 'async_cache_' . uniqid();
        $cache_config = config('cache.stores.' . $cache_connection_name);

        config([
            'cache.stores.' . $tmp_cache_connection_name => $cache_config,
        ]);

        Cache::purge();

        return $tmp_cache_connection_name;
    }

    /**
     * Reset all connections for async operations.
     */
    private function resetAsyncConnections(string $db_connection_name, string $cache_connection_name): void
    {
        $this->resetAsyncDatabaseConnection($db_connection_name);
        $this->resetAsyncCacheConnection($cache_connection_name);
    }

    /**
     * Reset the database connection for async operations.
     */
    private function resetAsyncDatabaseConnection(string $connection_name): void
    {
        $current_connection = DB::connection($connection_name);
        $current_connection->disconnect();

        DB::reconnect($connection_name);
    }

    /**
     * Reset the Redis connection for async operations.
     */
    private function resetAsyncCacheConnection(string $connection_name): void
    {
        /** @var Repository $store */
        $store = Cache::store($connection_name);
        $cache_connection = $store->getConnection();
        $cache_connection->disconnect();

        $cache_connection->connect();
    }
}
