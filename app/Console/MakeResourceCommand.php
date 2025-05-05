<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use function Laravel\Prompts\select;

use Override;
use Filament\Panel;
use ReflectionClass;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Filament\Clusters\Cluster;
use Filament\Facades\Filament;
use Modules\Core\Helpers\HasBenchmark;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Console\Command\Command;
use Modules\Core\Helpers\HasCommandModelResolution;
use Filament\Commands\MakeResourceCommand as FilamentMakeResourceCommand;

final class MakeResourceCommand extends FilamentMakeResourceCommand
{
    use HasBenchmark, HasCommandModelResolution;

    protected $description = 'Create a new Filament resource class and default page classes <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

    public function handle(): int
    {
        $model = $this->getModelClass('name', $this->option('model-namespace'), ! $this->option('model'));

        $match = Str::match('/(?:App|Modules)\\\\(?:\\w+\\\\)*Models/', $model ?: '');

        if ($match) {
            $modelNamespace = $match;
            unset($match);
        } else {
            $modelNamespace = 'App\\Models';
        }
        $this->input->setOption('model-namespace', $modelNamespace);

        $is_module = Str::contains($model ?: '', 'Modules\\');
        $module_name = $is_module ? Str::after(Str::before($model ?: '', '\\Models\\'), 'Modules\\') : null;
        $class_name = Str::after($model ?: '', $modelNamespace . '\\');

        if ($this->option('model')) {
            $class_name ??= $this->argument('name');

            if ($is_module && $module_name && class_exists('Nwidart\Modules\Facades\Module')) {
                $this->callSilently('make:model', ['name' => "{$class_name}", 'module' => $module_name]);
            } else {
                $this->callSilently('make:model', ['name' => "{$class_name}"]);
            }

            if (! $model) {
                $this->input->setArgument('name', $modelNamespace . '\\' . $class_name);
                $this->input->setOption('model', false);

                return $this->handle();
            }
        }

        if (! $model) {
            $this->error('Model not found');

            return self::FAILURE;
        }

        if ($this->option('migration')) {
            $table = (string) str($model)
                ->classBasename()
                ->pluralStudly()
                ->snake();

            if ($is_module && $module_name && class_exists('Nwidart\Modules\Facades\Module')) {
                $this->call('module:make-migration', ['name' => "create_{$table}_table", 'module' => $module_name, '--create' => $table]);
            } else {
                $this->call('make:migration', ['name' => "create_{$table}_table", '--create' => $table]);
            }
        }

        if ($this->option('factory')) {
            if ($is_module && $module_name && class_exists('Nwidart\Modules\Facades\Module')) {
                $this->call('module:make-factory', ['name' => $model, 'module' => $module_name]);
            } else {
                $this->call('make:factory', ['name' => $model]);
            }
        }

        $modelClass = (string) Str::afterLast($model, '\\');
        $modelSubNamespace = Str::contains($model, '\\') ? (string) Str::before(Str::after($model, 'Models\\'), $class_name) : '';
        $pluralModelClass = (string) str($class_name)->pluralStudly();
        $needsAlias = $class_name === 'Record';

        $panel = $this->option('panel');

        if ($panel) {
            $panel = Filament::getPanel($panel, isStrict: false);
        }

        if (! $panel) {
            $panels = Filament::getPanels();

            /** @var Panel $panel */
            $panel = count($panels) > 1 ? $panels[select(
                label: 'Which panel would you like to create this in?',
                options: array_map(
                    fn (Panel $panel): string => $panel->getId(),
                    $panels,
                ),
                default: Filament::getDefaultPanel()->getId(),
            )] : Arr::first($panels);
        }

        $resourceDirectories = $panel->getResourceDirectories();
        $resourceNamespaces = $panel->getResourceNamespaces();

        foreach ($resourceDirectories as $resourceIndex => $resourceDirectory) {
            if (str($resourceDirectory)->startsWith(base_path('vendor'))) {
                unset($resourceDirectories[$resourceIndex]);
                unset($resourceNamespaces[$resourceIndex]);
            }
        }

        $namespace = count($resourceNamespaces) > 1
            ? select(
                label: 'Which namespace would you like to create this in?',
                options: $resourceNamespaces,
            ) : (Arr::first($resourceNamespaces) ?? 'App\\Filament\\Resources');
        $path = count($resourceDirectories) > 1
            ? $resourceDirectories[array_search($namespace, $resourceNamespaces, true)] : (Arr::first($resourceDirectories) ?? app_path('Filament/Resources/'));

        $resourceClass = "{$modelClass}Resource";
        $resourceNamespace = mb_trim($module_name ? $module_name . '\\' . $modelSubNamespace : $modelSubNamespace, '\\');

        $namespace .= $resourceNamespace !== '' ? "\\{$resourceNamespace}" : '';
        $listResourcePageClass = "List{$pluralModelClass}";
        $manageResourcePageClass = "Manage{$pluralModelClass}";
        $createResourcePageClass = "Create{$modelClass}";
        $editResourcePageClass = "Edit{$modelClass}";
        $viewResourcePageClass = "View{$modelClass}";

        $baseResourcePath
            = (string) str($resourceClass)
                ->prepend('/' . $resourceNamespace . '/')
                ->prepend($path)
                ->replace('\\', '/')
                ->replace('//', '/');

        $resourcePath = "{$baseResourcePath}.php";
        $resourcePagesDirectory = "{$baseResourcePath}/Pages";
        $listResourcePagePath = "{$resourcePagesDirectory}/{$listResourcePageClass}.php";
        $manageResourcePagePath = "{$resourcePagesDirectory}/{$manageResourcePageClass}.php";
        $createResourcePagePath = "{$resourcePagesDirectory}/{$createResourcePageClass}.php";
        $editResourcePagePath = "{$resourcePagesDirectory}/{$editResourcePageClass}.php";
        $viewResourcePagePath = "{$resourcePagesDirectory}/{$viewResourcePageClass}.php";

        if (! $this->option('force') && $this->checkForCollision([
            $resourcePath,
            $listResourcePagePath,
            $manageResourcePagePath,
            $createResourcePagePath,
            $editResourcePagePath,
            $viewResourcePagePath,
        ])) {
            return Command::INVALID;
        }

        $pages = '';
        $pages .= '\'index\' => Pages\\' . ($this->option('simple') ? $manageResourcePageClass : $listResourcePageClass) . '::route(\'/\'),';

        if (! $this->option('simple')) {
            $pages .= PHP_EOL . "'create' => Pages\\{$createResourcePageClass}::route('/create'),";

            if ($this->option('view')) {
                $pages .= PHP_EOL . "'view' => Pages\\{$viewResourcePageClass}::route('/{record}'),";
            }

            $pages .= PHP_EOL . "'edit' => Pages\\{$editResourcePageClass}::route('/{record}/edit'),";
        }

        $tableActions = [];

        if ($this->option('view')) {
            $tableActions[] = 'Tables\Actions\ViewAction::make(),';
        }

        $tableActions[] = 'Tables\Actions\EditAction::make(),';

        $relations = '';

        $needs_soft_deletes = class_uses_trait($model, SoftDeletes::class) && ($panel->getId() === 'admin' || $this->option('soft-deletes'));

        if ($this->option('simple')) {
            $tableActions[] = 'Tables\Actions\DeleteAction::make(),';

            if ($needs_soft_deletes) {
                $tableActions[] = 'Tables\Actions\ForceDeleteAction::make(),';
                $tableActions[] = 'Tables\Actions\RestoreAction::make(),';
            }
        } else {
            $relations .= PHP_EOL . 'public static function getRelations(): array';
            $relations .= PHP_EOL . '{';
            $relations .= PHP_EOL . '    return [';
            $relations .= PHP_EOL . '        //';
            $relations .= PHP_EOL . '    ];';
            $relations .= PHP_EOL . '}' . PHP_EOL;
        }

        $tableActions = implode(PHP_EOL, $tableActions);

        $tableBulkActions = [];

        $tableBulkActions[] = 'Tables\Actions\DeleteBulkAction::make(),';

        $eloquentQuery = '';

        if ($needs_soft_deletes) {
            $tableBulkActions[] = 'Tables\Actions\ForceDeleteBulkAction::make(),';
            $tableBulkActions[] = 'Tables\Actions\RestoreBulkAction::make(),';

            $eloquentQuery .= PHP_EOL . PHP_EOL . 'public static function getEloquentQuery(): Builder';
            $eloquentQuery .= PHP_EOL . '{';
            $eloquentQuery .= PHP_EOL . '    return parent::getEloquentQuery()';

            if ($panel->getId() === 'admin') {
                $eloquentQuery .= PHP_EOL . '        ->withoutGlobalScopes();';
            } else {
                $eloquentQuery .= PHP_EOL . '        ->withoutGlobalScopes([';
                $eloquentQuery .= PHP_EOL . '            SoftDeletingScope::class,';
                $eloquentQuery .= PHP_EOL . '        ]);';
            }
            $eloquentQuery .= PHP_EOL . '}';
        }

        $tableBulkActions = implode(PHP_EOL, $tableBulkActions);

        $potentialCluster = (string) str($namespace)->beforeLast('\Resources');
        $clusterAssignment = null;
        $clusterImport = null;

        if (
            class_exists($potentialCluster)
            && is_subclass_of($potentialCluster, Cluster::class)
        ) {
            $clusterAssignment = $this->indentString(PHP_EOL . PHP_EOL . 'protected static ?string $cluster = ' . class_basename($potentialCluster) . '::class;');
            $clusterImport = "use {$potentialCluster};" . PHP_EOL;
        }

        $this->copyStubToApp('Resource', $resourcePath, [
            'clusterAssignment' => $clusterAssignment,
            'clusterImport' => $clusterImport,
            'eloquentQuery' => $this->indentString($eloquentQuery, 1),
            'formSchema' => $this->indentString($this->option('generate') ? $this->getResourceFormSchema(
                $modelNamespace . ($modelSubNamespace !== '' ? "\\{$modelSubNamespace}" : '') . '\\' . $modelClass,
            ) : '//', 4),
            ...$this->generateModel($class_name, $modelNamespace, $modelClass),
            'namespace' => $namespace,
            'pages' => $this->indentString($pages, 3),
            'relations' => $this->indentString($relations, 1),
            'resource' => "{$namespace}\\{$resourceClass}",
            'resourceClass' => $resourceClass,
            'tableActions' => $this->indentString($tableActions, 4),
            'tableBulkActions' => $this->indentString($tableBulkActions, 5),
            'tableColumns' => $this->indentString($this->option('generate') ? $this->getResourceTableColumns(
                $modelNamespace . ($modelSubNamespace !== '' ? "\\{$modelSubNamespace}" : '') . '\\' . $modelClass,
            ) : '//', 4),
            'tableFilters' => $this->indentString(
                $needs_soft_deletes ? 'Tables\Filters\TrashedFilter::make(),' : '//',
                4,
            ),
        ]);

        if ($this->option('simple')) {
            $this->copyStubToApp('ResourceManagePage', $manageResourcePagePath, [
                'baseResourcePage' => 'Filament\\Resources\\Pages\\ManageRecords' . ($needsAlias ? ' as BaseManageRecords' : ''),
                'baseResourcePageClass' => $needsAlias ? 'BaseManageRecords' : 'ManageRecords',
                'namespace' => "{$namespace}\\{$resourceClass}\\Pages",
                'resource' => "{$namespace}\\{$resourceClass}",
                'resourceClass' => $resourceClass,
                'resourcePageClass' => $manageResourcePageClass,
            ]);
        } else {
            $this->copyStubToApp('ResourceListPage', $listResourcePagePath, [
                'baseResourcePage' => 'Filament\\Resources\\Pages\\ListRecords' . ($needsAlias ? ' as BaseListRecords' : ''),
                'baseResourcePageClass' => $needsAlias ? 'BaseListRecords' : 'ListRecords',
                'namespace' => "{$namespace}\\{$resourceClass}\\Pages",
                'resource' => "{$namespace}\\{$resourceClass}",
                'resourceClass' => $resourceClass,
                'resourcePageClass' => $listResourcePageClass,
            ]);

            $this->copyStubToApp('ResourcePage', $createResourcePagePath, [
                'baseResourcePage' => 'Filament\\Resources\\Pages\\CreateRecord' . ($needsAlias ? ' as BaseCreateRecord' : ''),
                'baseResourcePageClass' => $needsAlias ? 'BaseCreateRecord' : 'CreateRecord',
                'namespace' => "{$namespace}\\{$resourceClass}\\Pages",
                'resource' => "{$namespace}\\{$resourceClass}",
                'resourceClass' => $resourceClass,
                'resourcePageClass' => $createResourcePageClass,
            ]);

            $editPageActions = [];

            if ($this->option('view')) {
                $this->copyStubToApp('ResourceViewPage', $viewResourcePagePath, [
                    'baseResourcePage' => 'Filament\\Resources\\Pages\\ViewRecord' . ($needsAlias ? ' as BaseViewRecord' : ''),
                    'baseResourcePageClass' => $needsAlias ? 'BaseViewRecord' : 'ViewRecord',
                    'namespace' => "{$namespace}\\{$resourceClass}\\Pages",
                    'resource' => "{$namespace}\\{$resourceClass}",
                    'resourceClass' => $resourceClass,
                    'resourcePageClass' => $viewResourcePageClass,
                ]);

                $editPageActions[] = 'Actions\ViewAction::make(),';
            }

            $editPageActions[] = 'Actions\DeleteAction::make(),';

            if ($needs_soft_deletes) {
                $editPageActions[] = 'Actions\ForceDeleteAction::make(),';
                $editPageActions[] = 'Actions\RestoreAction::make(),';
            }

            $editPageActions = implode(PHP_EOL, $editPageActions);

            $this->copyStubToApp('ResourceEditPage', $editResourcePagePath, [
                'baseResourcePage' => 'Filament\\Resources\\Pages\\EditRecord' . ($needsAlias ? ' as BaseEditRecord' : ''),
                'baseResourcePageClass' => $needsAlias ? 'BaseEditRecord' : 'EditRecord',
                'actions' => $this->indentString($editPageActions, 3),
                'namespace' => "{$namespace}\\{$resourceClass}\\Pages",
                'resource' => "{$namespace}\\{$resourceClass}",
                'resourceClass' => $resourceClass,
                'resourcePageClass' => $editResourcePageClass,
            ]);
        }

        $this->components->info("Filament resource [{$resourcePath}] created successfully.");

        return Command::SUCCESS;
    }

    #[Override]
    protected function getDefaultStubPath(): string
    {
        $stubPath = parent::getDefaultStubPath();

        if (! $this->fileExists($stubPath)) {
            $reflectionClass = new ReflectionClass(FilamentMakeResourceCommand::class);

            $stubPath = (string) str($reflectionClass->getFileName())
                ->beforeLast('Commands')
                ->append('../stubs');
        }

        return $stubPath;
    }
}
