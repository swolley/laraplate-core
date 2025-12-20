<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\Core\Helpers\HasBenchmark;
use Modules\Core\Overrides\ModuleDocGenerator;
use Mtrajano\LaravelSwagger\FormatterManager;
use Mtrajano\LaravelSwagger\GenerateSwaggerDoc as BaseGenerateSwaggerDoc;
use Mtrajano\LaravelSwagger\LaravelSwaggerException;
use Nwidart\Modules\Facades\Module;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;

final class SwaggerGenerateCommand extends BaseGenerateSwaggerDoc
{
    use HasBenchmark;

    public function __construct()
    {
        $this->signature .= '
                {--m|module= : Filter to a specific Module}
        ';
        $this->description .= ' <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    #[Override]
    public function handle(): int
    {
        $module_filter = $this->option('module');

        foreach (modules(true, false, false) as $module_name) {
            if ($module_name !== 'App' && ! class_exists(Module::class)) {
                continue;
            }

            if ($module_filter && $module_name !== $module_filter) {
                continue;
            }

            $this->moduleHandle($module_name);
        }

        Cache::tags(Cache::getCacheTags('docs'))->flush();

        return Command::SUCCESS;
    }

    /**
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     * @throws LaravelSwaggerException
     */
    public function moduleHandle(string $moduleName): void
    {
        $filter = $this->option('filter') ?: null;

        /** @var string|null $file */
        $file = $this->option('output') ?: resource_path('swagger') . DIRECTORY_SEPARATOR . $moduleName . '-swagger.json';
        $config = config('laravel-swagger');

        if ($moduleName !== 'App') {
            $module_path = Module::getModulePath($moduleName);
            $module_json = json_decode(file_get_contents($module_path . DIRECTORY_SEPARATOR . 'module.json'), true);
            $config['title'] .= ' ' . $module_json['name'] . ' module';
            $config['description'] = $module_json['description'] . ($module_json['keywords'] === [] ? '' : ' (' . implode(', ', $module_json['keywords']) . ')');
            $composer_json = json_decode(file_get_contents($module_path . 'composer.json'));

            if (isset($composer_json->version)) {
                $config['version'] = $composer_json->version;
            }
        }

        $doc = new ModuleDocGenerator($config, $moduleName !== 'App' ? config('modules.namespace') . '\\' . $moduleName : $moduleName, $filter)->generate();
        $doc['tags'] = [$moduleName];

        if (array_filter($doc['paths'], static fn (string $k): bool => Str::contains($k, '/api/'), ARRAY_FILTER_USE_KEY) !== []) {
            $doc['tags'][] = 'Api';
        }

        $formattedDoc = new FormatterManager($doc)
            ->setFormat($this->option('format'))
            ->format();

        if ($file) {
            $folder = Str::beforeLast($file, DIRECTORY_SEPARATOR);

            if (! file_exists($folder)) {
                mkdir($folder, recursive: true);
            }

            if (file_exists($file)) {
                $old_doc = file_get_contents($file);

                if ($this->option('format') === 'json') {
                    $old_doc = json_decode($old_doc, true);
                } elseif ($this->option('format') === 'yaml') {
                    $old_doc = Yaml::parse($old_doc, Yaml::PARSE_OBJECT_FOR_MAP);
                } else {
                    $old_doc = null;
                }
            } else {
                $old_doc = null;
            }

            file_put_contents($file, $formattedDoc);

            $this->verboseGeneration($doc, $old_doc);
        } else {
            $this->line($formattedDoc);
        }
    }

    #[Override]
    protected function getOptions(): array
    {
        return [
            ['module', null, InputOption::VALUE_OPTIONAL, 'Filter to a specific Module', null],
        ];
    }

    private function verboseGeneration(array $doc, ?array $old_doc): void
    {
        $this->info($doc['info']['title']);

        foreach ($doc['paths'] as $path => $methods) {
            $keys = array_keys($methods);
            $imploded_methods = implode('|', array_map(strtoupper(...), $keys));
            $post_methods_padding = 40 - mb_strlen($imploded_methods);
            $post_route_padding = 60 - mb_strlen((string) $path);

            if (isset($old_doc['paths'][$path]) && $old_doc['paths'][$path] === $doc['paths'][$path]) {
                $color = 'gray';
                $message = 'unchanged';
            } elseif (isset($old_doc['paths'][$path])) {
                $color = 'yellow';
                $message = 'updated';
            } else {
                $color = 'green';
                $message = 'new';
            }

            $this->line($imploded_methods . str_repeat(' ', $post_methods_padding) . $path . str_repeat(' ', $post_route_padding) . sprintf('<fg=%s>%s</fg=%s>', $color, $message, $color));
        }

        $this->line('');
    }
}
