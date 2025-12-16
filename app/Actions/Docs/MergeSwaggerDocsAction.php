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
        private ?string $basePath = null,
    ) {}

    public function __invoke(string $version): array
    {
        $assetsPath = $this->basePath ?? resource_path('swagger');
        $files = glob($assetsPath . DIRECTORY_SEPARATOR . '*-swagger.json');
        $modules = $this->modulesProvider instanceof Closure ? ($this->modulesProvider)() : modules(true, false, true);

        $additionalPaths = [];
        $mainJson = [];

        foreach ($files as $file) {
            $shortName = str_replace($assetsPath . DIRECTORY_SEPARATOR, '', $file);
            $json = json_decode($this->filesystem->get($file), true);
            $json['paths'] = array_filter($json['paths'], function ($path) use ($version): bool {
                if (Str::contains($path, $version)) {
                    return true;
                }

                return ! Str::contains($path, '/api/');
            }, ARRAY_FILTER_USE_KEY);

            if (Str::startsWith($shortName, 'App')) {
                $mainJson = $json;

                continue;
            }

            $moduleName = str_replace(['-swagger.json'], '', $shortName);

            if (in_array($moduleName, $modules, true)) {
                $additionalPaths = array_merge(
                    $additionalPaths,
                    array_filter(
                        $json['paths'],
                        fn (string $path): bool => Str::contains($path, $version) || ! Str::contains($path, '/api/'),
                        ARRAY_FILTER_USE_KEY,
                    ),
                );
            }
        }

        if ($additionalPaths !== []) {
            $mainJson['paths'] = array_merge($mainJson['paths'] ?? [], $additionalPaths);
        }

        return $mainJson;
    }
}
