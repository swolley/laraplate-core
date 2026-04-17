<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use function class_exists;
use function class_uses_recursive;
use function in_array;
use function is_subclass_of;

use Generator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Modules\Core\Helpers\HasVersions;
use Modules\Core\Overrides\Command;
use Override;
use ReflectionClass;
use Symfony\Component\Console\Command\Command as CommandExit;

final class CompactVersions extends Command
{
    #[Override]
    protected $signature = 'versions:compact {modelClass? : Fully-qualified model class (must use HasVersions)} {id? : Primary key of a single record}';

    #[Override]
    protected $description = 'Create a snapshot version per record and purge older version rows. Optionally restrict by model class and/or id. <fg=yellow>(⚡ Modules\\Core)</fg=yellow>';

    public function handle(): int
    {
        $modelClass = $this->argument('modelClass');
        $id = $this->argument('id');

        if ($id !== null && $modelClass === null) {
            $this->error('The model class argument is required when an id is given.');

            return CommandExit::FAILURE;
        }

        if ($modelClass !== null && (! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class))) {
            $this->error('The model class must exist and extend Illuminate\\Database\\Eloquent\\Model.');

            return CommandExit::FAILURE;
        }

        if ($modelClass !== null && ! in_array(HasVersions::class, class_uses_recursive($modelClass), true)) {
            $this->error('The model must use the HasVersions trait.');

            return CommandExit::FAILURE;
        }

        $processed = 0;
        $skipped = 0;

        if ($modelClass !== null && $id !== null) {
            $this->compactOneModelAndId($modelClass, $id, $processed, $skipped);
        } else {
            foreach ($this->iterateDistinctVersionTargets($modelClass) as $tuple) {
                $resolvedClass = Relation::getMorphedModel($tuple->versionable_type) ?? (class_exists((string) $tuple->versionable_type) ? (string) $tuple->versionable_type : null);

                if ($resolvedClass === null || ! is_subclass_of($resolvedClass, Model::class)) {
                    $this->warn("Skipping unknown morph type: {$tuple->versionable_type}");
                    $skipped++;

                    continue;
                }

                if (! in_array(HasVersions::class, class_uses_recursive($resolvedClass), true)) {
                    $skipped++;

                    continue;
                }

                if ($this->compactRecord($resolvedClass, $tuple->versionable_id, $tuple->connection_ref, $tuple->table_ref)) {
                    $processed++;

                    continue;
                }

                $skipped++;
            }
        }

        $this->info("Done. Compacted: {$processed}, skipped: {$skipped}.");

        return CommandExit::SUCCESS;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function compactOneModelAndId(string $modelClass, string $id, int &$processed, int &$skipped): void
    {
        /** @var Model $prototype */
        $prototype = new $modelClass();
        $morph = $prototype->getMorphClass();

        $refs = DB::table('versions')
            ->whereNull('deleted_at')
            ->where('versionable_type', $morph)
            ->where('versionable_id', $id)
            ->select(['connection_ref', 'table_ref'])
            ->distinct()
            ->get();

        if ($refs->isEmpty()) {
            if ($this->compactRecord($modelClass, $id, null, null)) {
                $processed++;
            } else {
                $skipped++;
            }

            return;
        }

        foreach ($refs as $refRow) {
            if ($this->compactRecord($modelClass, $id, $refRow->connection_ref, $refRow->table_ref)) {
                $processed++;
            } else {
                $skipped++;
            }
        }
    }

    /**
     * @return Generator<int, object{versionable_type: string, versionable_id: mixed, connection_ref: ?string, table_ref: ?string}>
     */
    private function iterateDistinctVersionTargets(?string $modelClass): Generator
    {
        $query = DB::table('versions')
            ->select(['versionable_type', 'versionable_id', 'connection_ref', 'table_ref'])
            ->whereNull('deleted_at')
            ->distinct()
            ->orderBy('versionable_type')
            ->orderBy('versionable_id');

        if ($modelClass !== null) {
            $query->where('versionable_type', (new $modelClass())->getMorphClass());
        }

        foreach ($query->cursor() as $row) {
            yield $row;
        }
    }

    /**
     * @param  class-string<Model>  $className
     */
    private function compactRecord(string $className, mixed $versionableId, ?string $connectionRef, ?string $tableRef): bool
    {
        $model = $this->resolveTargetModel($className, $versionableId, $connectionRef, $tableRef);

        if ($model === null) {
            $this->warn("Could not resolve model {$className}#{$versionableId}.");

            return false;
        }

        if ($model->getVersionStrategy() === false) {
            $this->warn("Versioning is disabled for {$className}#{$versionableId}; skipped.");

            return false;
        }

        $this->disableAsyncVersioning($model);
        $model->createSnapshotVersion([], null, true);

        return true;
    }

    /**
     * @param  class-string<Model>  $className
     */
    private function resolveTargetModel(string $className, mixed $versionableId, ?string $connectionRef, ?string $tableRef): ?Model
    {
        /** @var Model $model */
        $model = new $className();

        if ($connectionRef !== null) {
            $model->setConnection($connectionRef);
        }

        if ($tableRef !== null) {
            $model->setTable($tableRef);
        }

        return $model->newQueryWithoutScopes()->find($versionableId);
    }

    private function disableAsyncVersioning(Model $model): void
    {
        $reflection = new ReflectionClass($model);

        if (! $reflection->hasProperty('asyncVersioning')) {
            return;
        }

        $property = $reflection->getProperty('asyncVersioning');
        $property->setAccessible(true);
        $property->setValue($model, false);
    }
}
