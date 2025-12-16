<?php

declare(strict_types=1);

namespace Modules\Core\Services\Docs;

use Closure;
use Illuminate\Support\Str;
use Nwidart\Modules\Facades\Module;

final readonly class ModuleInfoService
{
    /**
     * @param  callable():array<int,string>  $modulesProvider
     * @param  callable(bool):array<int,string>  $modelsProvider
     * @param  callable(bool):array<int,string>  $controllersProvider
     * @param  callable(string):string|null  $composerReader
     * @param  callable(string):bool  $moduleEnabled
     */
    public function __construct(
        private ?Closure $modulesProvider = null,
        private ?Closure $modelsProvider = null,
        private ?Closure $controllersProvider = null,
        private ?Closure $composerReader = null,
        private ?Closure $moduleEnabled = null,
    ) {}

    /**
     * @return array<string,array<string,mixed>>
     */
    public function groupedModules(): array
    {
        $allModules = $this->modulesProvider instanceof Closure ? ($this->modulesProvider)() : modules(true, false, false);
        $allModels = $this->modelsProvider instanceof Closure ? ($this->modelsProvider)(false) : models(false);
        $allControllers = $this->controllersProvider instanceof Closure ? ($this->controllersProvider)(false) : controllers(false);

        $grouped = [];

        foreach ($allModules as $module) {
            if (! array_key_exists((string) $module, $grouped)) {
                $grouped[$module] = ['models' => [], 'controllers' => [], 'routes' => [], 'authors' => []];
            }

            foreach ($allModels as $index => $model) {
                if (Str::startsWith($model, $module) || Str::startsWith($model, 'Modules\\' . $module)) {
                    $grouped[$module]['models'][] = $model;
                    unset($allModels[$index]);
                }
            }

            foreach ($allControllers as $index => $controller) {
                if (Str::startsWith($controller, $module) || Str::startsWith($controller, 'Modules\\' . $module)) {
                    $grouped[$module]['controllers'][] = $controller;
                    unset($allControllers[$index]);
                }
            }

            $composerContent = $this->readComposerJson($module);

            foreach ($composerContent['authors'] ?? [] as $author) {
                $grouped[$module]['authors'][] = [
                    'name' => is_string($author) ? $author : $author['name'],
                    'email' => ! is_string($author) && isset($author['email']) ? $author['email'] : null,
                ];
            }

            $grouped[$module]['description'] = $composerContent['description'] ?? null;
            $grouped[$module]['version'] = $composerContent['version'] ?? null;
            sort($grouped[$module]['models']);
            sort($grouped[$module]['controllers']);
            $grouped[$module]['isEnabled'] = $module === 'App' ? true : $this->isModuleEnabled($module);

            if ($module === 'App') {
                $grouped[$module]['version'] = version();
            } else {
                $modulePath = Module::getModulePath($module);
                $moduleComposer = json_decode(file_get_contents($modulePath . 'composer.json'), true);
                $version = $moduleComposer['version'] ?? null;

                if ($version) {
                    $grouped[$module]['version'] = $version;
                }
            }
        }

        return $grouped;
    }

    /**
     * @return array<string,mixed>
     */
    private function readComposerJson(string $module): array
    {
        $path = $module === 'App'
            ? base_path('composer.json')
            : module_path($module, 'composer.json');

        $content = $this->composerReader instanceof Closure ? ($this->composerReader)($path) : file_get_contents($path);

        return json_decode((string) $content ?: '{}', true) ?? [];
    }

    private function isModuleEnabled(string $module): bool
    {
        if ($this->moduleEnabled instanceof Closure) {
            return ($this->moduleEnabled)($module);
        }

        return Module::isEnabled($module);
    }
}
