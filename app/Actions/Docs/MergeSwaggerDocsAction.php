<?php

declare(strict_types=1);

namespace Modules\Core\Actions\Docs;

use Closure;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final readonly class MergeSwaggerDocsAction
{
    public function __construct(
        private Filesystem $filesystem,
        private ?Closure $modulesProvider = null,
        private ?Closure $pathResolver = null,
    ) {}

    public function __invoke(string $version): array
    {
        $modules = $this->modulesProvider instanceof Closure ? ($this->modulesProvider)() : modules(true, false, true);
        $resolve_path = $this->pathResolver ?? static fn (string $module): string => swagger_doc_path($module);
        $additional_paths = [];
        $main_json = [];

        $app_file = $resolve_path('App');

        if ($this->filesystem->exists($app_file)) {
            $main_json = $this->loadFilteredDocument($app_file, $version);
        }

        foreach ($modules as $module_name) {
            if ($module_name === 'App') {
                continue;
            }

            $module_file = $resolve_path($module_name);

            if (! $this->filesystem->exists($module_file)) {
                continue;
            }

            $module_json = $this->loadFilteredDocument($module_file, $version);
            $additional_paths = array_merge(
                $additional_paths,
                $this->filterPaths($module_json['paths'] ?? [], $version),
            );
        }

        if ($additional_paths !== []) {
            $main_json['paths'] = array_merge($main_json['paths'] ?? [], $additional_paths);
        }

        return $main_json;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFilteredDocument(string $file, string $version): array
    {
        /** @var array<string, mixed> $json */
        $json = json_decode($this->filesystem->get($file), true);
        $json['paths'] = $this->filterPaths($json['paths'] ?? [], $version);

        return $json;
    }

    /**
     * @param  array<string, mixed>  $paths
     * @return array<string, mixed>
     */
    private function filterPaths(array $paths, string $version): array
    {
        return array_filter(
            $paths,
            static fn (string $path): bool => Str::contains($path, $version) || ! Str::contains($path, '/api/'),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
