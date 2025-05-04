<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use function Laravel\Prompts\progress;

use Exception;
use Illuminate\Support\Facades\DB;
use Modules\Core\Overrides\Seeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

abstract class BatchSeeder extends Seeder
{
    private const BATCH_SIZE = 20;

    private const MAX_RETRIES = 3;

    private const RETRY_DELAY = 2; // seconds

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

    /**
     * Create the data in batches.
     *
     * @param  class-string<Model>  $model_class  The model class to create the data for
     * @param  int  $total_count  The total number of data to create
     * @param  bool  $force_approve  Whether to force approval of the created data
     */
    protected function createInBatches(string $model_class, int $total_count, bool $force_approve = true): void
    {
        $current_count = $this->command->option('force') ? 0 : $model_class::withoutGlobalScopes()->count();
        $count_to_create = $total_count - $current_count;
        $entity_name = (new $model_class)->getTable();

        if ($count_to_create <= 0) {
            $this->command->info($entity_name . ' already at target count.');

            return;
        }

        $batches = ceil($count_to_create / self::BATCH_SIZE);
        $created = 0;

        $progress = progress("Creating {$entity_name}", $count_to_create);
        $progress->start();

        for ($batch = 0; $batch < $batches; $batch++) {
            $remaining = $count_to_create - $created;

            /** @var int $batch_size */
            $batch_size = min(self::BATCH_SIZE, $remaining);

            $retry_count = 0;
            $success = false;

            while (! $success && $retry_count < self::MAX_RETRIES) {
                try {
                    DB::beginTransaction();

                    /** @var \Illuminate\Database\Eloquent\Factories\Factory<Model> $factory */
                    /** @phpstan-ignore staticMethod.notFound */
                    $factory = $model_class::factory();
                    $created_models = $factory->count($batch_size)->create();

                    if ($force_approve && class_uses_trait($model_class, HasApprovals::class)) {
                        /** @var Model $model */
                        foreach ($created_models as $model) {
                            $model->approve();
                        }
                    }

                    DB::commit();
                    $success = true;

                    $created += $batch_size;
                    $progress->hint('');
                    $progress->advance($batch_size);
                } catch (QueryException $e) {
                    $progress->hint("<fg=red>Failed to create {$entity_name} batch {" . ($batch + 1) . '}: ' . $e->getMessage() . '</>');
                    $progress->render();

                    throw $e;
                } catch (Exception $e) {
                    DB::rollBack();
                    $retry_count++;

                    if ($retry_count >= self::MAX_RETRIES) {
                        throw $e;
                    }

                    $progress->hint("<fg=yellow>Retry {$retry_count} for {$entity_name} batch {" . ($batch + 1) . '}</>');
                    $progress->render();
                    sleep(self::RETRY_DELAY);
                }
            }
        }

        $progress->finish();
    }
}
