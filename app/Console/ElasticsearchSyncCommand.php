<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Laravel\Prompts\Progress;
use Illuminate\Support\Carbon;
use Modules\Core\Overrides\Command;
use Modules\Core\Cache\HasCache;
use Modules\Core\Cache\Searchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\SoftDeletes;

class ElasticsearchSyncCommand extends Command
{
    protected $signature = 'model:sync {model : The model to sync} {--id= : The ID of the document to sync} {--from= : The date to sync from}';

    protected $description = 'Sync documents modified after the last indexing in Elasticsearch <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

    public function handle(): int
    {
        try {
            $model_class = $this->argument('model');
            $id = $this->option('id');
            $from = $this->option('from');

            if ($from && $id) {
                $this->error('You must specify either --id or --from option, but not both.');
                return 1;
            }

            if (!class_exists($model_class)) {
                $this->error("Model class {$model_class} does not exist.");
                return 1;
            }

            if (!in_array(Searchable::class, class_uses_recursive($model_class))) {
                $this->error("Model class {$model_class} does not use the Searchable trait.");
                return 1;
            }

            $model = $model_class::query()->withTrashed();

            if ($id) {
                $model->where('id', $id);
            } elseif ($from) {
                $model->where('updated_at', '>', Carbon::parse($from));
            } else {
                $last_indexed = $model->getLastIndexedTimestamp();
                $model->where('updated_at', '>', $last_indexed);
            }

            $total = $model->count();
            if ($total === 0) {
                $this->info('No documents need to be synced.');
                return 0;
            }

            $this->info("Found {$total} documents to sync.");
            $total_chunks = ceil($total / 100);
            $chunk = 0;
            $use_soft_deletes = in_array(SoftDeletes::class, class_uses_recursive($model_class));
            $model->chunk(100, function ($records) use ($total_chunks, &$chunk, $use_soft_deletes) {
                $chunk++;
                $progress = new Progress(
                    'Syncing documents chunk ' . $chunk . ' of ' . $total_chunks,
                    $records->map(fn($record) => 'Record ' . $record->id)->toArray(),
                );
                foreach ($records as $record) {
                    $record->dispatchSearchableJobs($use_soft_deletes && $record->trashed());
                    $progress->advance();
                }
                $progress->finish();
                $this->newLine();
            });
            $this->info('Documents have been queued for indexing.');

            // Se il modello usa il trait HasCache, invalida la cache
            if (in_array(HasCache::class, class_uses_recursive($model_class))) {
                (new $model_class)->invalidateCache();
                $this->info('Cache has been invalidated for model ' . $model_class);
            }

            return 0;
        } catch (\Exception $e) {
            Log::error('Error in elasticsearch:sync command', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error('An error occurred: ' . $e->getMessage());
            return 1;
        }
    }
}
