<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use function Laravel\Prompts\confirm;
use function class_uses_trait;
use function is_laraplate_owned_module;
use function models;
use function module_path;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes as EloquentSoftDeletes;
use Illuminate\Support\Str;
use Modules\Core\Overrides\Command;
use Modules\Core\SoftDeletes\SoftDeletes as CoreSoftDeletes;
use Nwidart\Modules\Facades\Module;
use Override;
use Throwable;

final class MakeFilamentResourcesCommand extends Command
{
    #[Override]
    protected $signature = 'filament:make-resources {module? : Target module name, or App (default)}';

    #[Override]
    protected $description = 'Scaffold Filament resources for Eloquent models in App or a custom module <fg=green>(⚡ Modules\\Core)</fg=green>';

    public function handle(): int
    {
        $module = $this->normalizeModule((string) ($this->argument('module') ?: 'App'));

        if ($module !== 'App' && is_laraplate_owned_module($module)) {
            $this->components->error("Module [{$module}] is Laraplate-owned; filament:make-resources will not generate into official modules.");

            return self::FAILURE;
        }

        if ($module !== 'App' && Module::find($module) === null) {
            $this->components->error("Module [{$module}] was not found.");

            return self::FAILURE;
        }

        $model_classes = $this->collectModels($module);

        if ($model_classes === []) {
            $this->components->warn("No eligible models found for [{$module}].");

            return self::SUCCESS;
        }

        $created = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($model_classes as $model_class) {
            $result = $this->makeResourceForModel($module, $model_class);

            match ($result) {
                'created' => $created++,
                'skipped' => $skipped++,
                default => $failed++,
            };
        }

        $this->newLine();
        $this->components->info("Done for [{$module}]: {$created} created, {$skipped} skipped, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<class-string<Model>>
     */
    protected function collectModels(string $module): array
    {
        /** @var list<class-string<Model>> $classes */
        $classes = models(
            onlyModule: $module,
            filter: static fn (string $class): bool => ! is_a($class, Pivot::class, true)
                && ! is_a($class, MorphPivot::class, true),
        );

        return $classes;
    }

    /**
     * @param  class-string<Model>  $model_class
     * @return 'created'|'skipped'|'failed'
     */
    private function makeResourceForModel(string $module, string $model_class): string
    {
        $resource_path = $this->resourceClassPath($module, $model_class);
        $force = false;

        if (is_file($resource_path)) {
            if (! $this->input->isInteractive()) {
                $this->components->warn("{$model_class}: skipped (exists)");

                return 'skipped';
            }

            if (! confirm(
                label: class_basename($model_class).'Resource already exists. Overwrite?',
                default: false,
            )) {
                $this->components->warn("{$model_class}: skipped");

                return 'skipped';
            }

            $force = true;
        }

        $basename = class_basename($model_class);
        $model_namespace = (string) Str::of($model_class)->beforeLast('\\'.$basename);

        $parameters = [
            'model' => $basename,
            '--model-namespace' => $model_namespace,
            '--panel' => 'admin',
            '--record-title-attribute' => $this->resolveRecordTitleAttribute($model_class),
            '--resource-namespace' => $this->resourceNamespace($module),
        ];

        if ($this->modelUsesSoftDeletes($model_class)) {
            $parameters['--soft-deletes'] = true;
        }

        if ($force) {
            $parameters['--force'] = true;
        }

        if (! $this->input->isInteractive()) {
            $parameters['--no-interaction'] = true;
        }

        $command = $module === 'App'
            ? 'make:filament-resource'
            : 'module:make:filament-resource';

        if ($module !== 'App') {
            $parameters['module'] = $module;
        }

        try {
            $exit = $this->call($command, $parameters);
        } catch (Throwable $exception) {
            $this->components->error("{$model_class}: failed — {$exception->getMessage()}");

            return 'failed';
        }

        if ($exit !== self::SUCCESS) {
            $this->components->error("{$model_class}: failed");

            return 'failed';
        }

        $this->components->info("{$model_class}: created");

        return 'created';
    }

    private function normalizeModule(string $module): string
    {
        $trimmed = trim($module);

        if ($trimmed === '' || strcasecmp($trimmed, 'App') === 0) {
            return 'App';
        }

        return Str::studly($trimmed);
    }

    /**
     * @param  class-string<Model>  $model_class
     */
    private function resourceClassPath(string $module, string $model_class): string
    {
        $basename = class_basename($model_class);
        $folder = Str::pluralStudly($basename);
        $relative = "Filament/Resources/{$folder}/{$basename}Resource.php";

        if ($module === 'App') {
            return app_path($relative);
        }

        return module_path($module, 'app/'.$relative);
    }

    private function resourceNamespace(string $module): string
    {
        if ($module === 'App') {
            return 'App\\Filament\\Resources';
        }

        return 'Modules\\'.$module.'\\Filament\\Resources';
    }

    /**
     * @param  class-string<Model>  $model_class
     */
    private function resolveRecordTitleAttribute(string $model_class): string
    {
        try {
            $model = new $model_class();

            return $model->getKeyName() ?: 'id';
        } catch (Throwable) {
            return 'id';
        }
    }

    /**
     * @param  class-string<Model>  $model_class
     */
    private function modelUsesSoftDeletes(string $model_class): bool
    {
        return class_uses_trait($model_class, EloquentSoftDeletes::class)
            || class_uses_trait($model_class, CoreSoftDeletes::class);
    }
}
