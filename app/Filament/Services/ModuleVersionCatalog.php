<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Services;

use Illuminate\Support\Str;
use Modules\Core\Filament\Data\ModuleVersionEntry;
use Nwidart\Modules\Facades\Module;

final readonly class ModuleVersionCatalog
{
    /**
     * @param  callable(string): bool  $is_module_enabled
     */
    public function __construct(
        private string $app_composer_path,
        private string $modules_path,
        private mixed $is_module_enabled,
    ) {}

    public static function make(): self
    {
        return new self(
            base_path('composer.json'),
            (string) config('modules.paths.modules', base_path('Modules')),
            static fn (string $name): bool => Module::isEnabled($name),
        );
    }

    /**
     * @return list<ModuleVersionEntry>
     */
    public function entries(): array
    {
        $entries = [
            new ModuleVersionEntry(
                name: 'App',
                version: $this->readComposerVersion($this->app_composer_path),
                enabled: true,
                isApp: true,
            ),
        ];

        foreach ($this->installedModuleNames() as $name) {
            $composer_path = $this->modules_path.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.'composer.json';
            $enabled = (bool) ($this->is_module_enabled)($name);

            $entries[] = new ModuleVersionEntry(
                name: $name,
                version: $this->readComposerVersion($composer_path),
                enabled: $enabled,
            );
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    private function installedModuleNames(): array
    {
        if (! is_dir($this->modules_path)) {
            return [];
        }

        $names = [];

        foreach (glob($this->modules_path.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [] as $path) {
            $names[] = Str::afterLast($path, DIRECTORY_SEPARATOR);
        }

        sort($names, SORT_STRING);

        return $names;
    }

    private function readComposerVersion(string $composer_json_path): string
    {
        if (! is_file($composer_json_path)) {
            return 'unknown';
        }

        $raw = file_get_contents($composer_json_path);

        if ($raw === false) {
            return 'unknown';
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return 'unknown';
        }

        $version = $decoded['version'] ?? null;

        return is_string($version) && $version !== '' ? $version : 'unknown';
    }
}
