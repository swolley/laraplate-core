<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use const SIGINT;
use const SIGTERM;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

use Illuminate\Console\Command;
use Modules\Core\Import\Contracts\BulkImporterResolverInterface;
use Modules\Core\Import\Contracts\ConnectionAwareBulkImporterInterface;
use Modules\Core\Import\Contracts\ImportPluginDiscoveryInterface;
use Modules\Core\Import\Support\BulkImportRunner;
use Modules\Core\Import\Support\ImportInterruptHandler;
use Modules\Core\Search\DeferredSearchIndexing;
use Modules\Core\Search\Exceptions\DeferredRunInterruptedException;
use Override;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

abstract class AbstractImportCommand extends Command
{
    private const string SKIP_IMPORTER = '(skip)';

    public function __construct(
        private readonly BulkImportRunner $runner,
        private readonly BulkImporterResolverInterface $resolver,
        private readonly ImportPluginDiscoveryInterface $discovery,
    ) {
        parent::__construct();
    }

    final public function handle(DeferredSearchIndexing $deferredIndexing): int
    {
        $this->maybePromptForImporter();

        $importer_class = mb_trim((string) $this->option('importer'));

        if ($importer_class === '') {
            $this->error('The --importer option is required (importer FQCN).');

            return self::FAILURE;
        }

        if (! $this->loadBootstrap()) {
            return self::FAILURE;
        }

        if (! class_exists($importer_class)) {
            $this->error("Importer class not found: {$importer_class}. Did you pass the correct --bootstrap autoloader?");

            return self::FAILURE;
        }

        $dry_run = (bool) $this->option('dry-run');
        $parameters = $this->parseArguments();
        $parameters['dryRun'] = $dry_run;
        $parameters['limit'] = $this->resolveLimit();

        try {
            $importer = $this->resolver->resolve($importer_class, $parameters);
        } catch (Throwable $exception) {
            $this->error("Unable to resolve importer [{$importer_class}]: {$exception->getMessage()}");

            return self::FAILURE;
        }

        if ($dry_run) {
            $this->warn('Dry-run enabled: the selected database transaction will be rolled back.');
        }

        $skip_search = (bool) $this->option('no-search') || $dry_run;

        if ($skip_search) {
            config(['scout.driver' => 'null']);
            $this->warn('Search indexing disabled for this import.');
        }

        $connection = $importer instanceof ConnectionAwareBulkImporterInterface
            ? $importer->importConnection()
            : null;
        $interrupt_handler = new ImportInterruptHandler(
            $deferredIndexing,
            $this->output,
            $this->input->isInteractive() ? $this->askOnInterrupt(...) : null,
            static function (int $code): never {
                exit($code);
            },
        );
        $this->trap(static fn (): array => [SIGINT, SIGTERM], $interrupt_handler);

        try {
            $imported = $deferredIndexing->run(
                fn (): int => $this->runner->run(
                    $dry_run,
                    fn (): int => $importer->import($this->output),
                    $connection,
                ),
                $this->resolveIndexBatch(),
                discard: $skip_search,
                onFlush: $this->reportSearchFlush(...),
            );
        } catch (DeferredRunInterruptedException) {
            $this->warn('Import interrupted: the records imported so far are indexed.');

            return $interrupt_handler->exitCode();
        }

        $this->info("Imported {$imported} record(s)" . ($dry_run ? ' (dry-run, rolled back).' : '.'));

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{0: string, 1: string|null, 2: int, 3: string, 4?: mixed}>
     */
    #[Override]
    protected function getOptions(): array
    {
        return [
            ['importer', null, InputOption::VALUE_OPTIONAL, 'Fully-qualified importer class name'],
            ['bootstrap', null, InputOption::VALUE_OPTIONAL, 'Path to an external Composer autoloader'],
            ['arg', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Importer argument as key=value', []],
            ['dry-run', null, InputOption::VALUE_NONE, 'Roll back writes on the importer-selected or default database connection'],
            ['limit', null, InputOption::VALUE_OPTIONAL, 'Maximum number of records to import'],
            ['no-search', null, InputOption::VALUE_NONE, 'Disable search indexing for the duration of the import'],
            ['index-batch', null, InputOption::VALUE_OPTIONAL, 'Records indexed per bulk search flush (default: scout.chunk.searchable)'],
        ];
    }

    private function maybePromptForImporter(): void
    {
        if ($this->optionValueIsPresent('importer') || $this->optionValueIsPresent('bootstrap')) {
            return;
        }

        $root = $this->discovery->root();

        if ($root === null) {
            return;
        }

        if (! confirm("Found {$this->discovery->label()} at {$root}. Load its Composer autoloader?", false)) {
            return;
        }

        $autoload = $this->discovery->autoloadPath($root);

        if ($autoload === null) {
            $this->error("{$this->discovery->label()} vendor/autoload.php not found. Run composer install in that project first.");

            return;
        }

        require_once $autoload;

        $importers = $this->discovery->discoverImplementations($root);

        if ($importers === []) {
            $this->warn("No {$this->resolver->contract()} implementations found under {$this->discovery->label()}/src.");

            return;
        }

        $selected = select('Select an importer (optional)', [self::SKIP_IMPORTER, ...$importers], 0);

        if ($selected === self::SKIP_IMPORTER) {
            return;
        }

        $this->input->setOption('bootstrap', $autoload);
        $this->input->setOption('importer', $selected);
    }

    private function optionValueIsPresent(string $name): bool
    {
        $value = $this->option($name);

        return is_string($value) && mb_trim($value) !== '';
    }

    private function loadBootstrap(): bool
    {
        $bootstrap = $this->option('bootstrap');

        if (! is_string($bootstrap) || $bootstrap === '') {
            return true;
        }

        if (! is_file($bootstrap)) {
            $this->error("Bootstrap autoloader not found: {$bootstrap}");

            return false;
        }

        require_once $bootstrap;

        return true;
    }

    private function resolveLimit(): ?int
    {
        $limit = $this->option('limit');

        return $limit === null || $limit === '' ? null : max(0, (int) $limit);
    }

    private function askOnInterrupt(bool $flushing, int $pending): string
    {
        return (string) select(
            label: 'Import interrupted. What now?',
            options: [
                ImportInterruptHandler::FINISH => $flushing
                    ? 'Finish the bulk indexing in progress, then quit'
                    : "Index the {$pending} record(s) imported since the last flush, then quit",
                ImportInterruptHandler::QUIT => 'Quit now and leave them unindexed (scout:import indexes them later)',
                ImportInterruptHandler::RESUME => 'Resume the import',
            ],
            default: ImportInterruptHandler::FINISH,
        );
    }

    /**
     * One line per flushed chunk, so the operator sees when the bulk indexing
     * runs and whether it was queued or done in place.
     */
    private function reportSearchFlush(string $class, int $count, bool $queued, float $milliseconds): void
    {
        $model = class_basename($class);

        $this->line($queued
            ? "<fg=gray>Search: queued {$count} {$model} record(s) for bulk indexing</>"
            : sprintf('<fg=gray>Search: indexed %d %s record(s) in %.0fms</>', $count, $model, $milliseconds));
    }

    private function resolveIndexBatch(): int
    {
        $batch = $this->option('index-batch');

        return $batch === null || $batch === ''
            ? max(1, config()->integer('scout.chunk.searchable', 500))
            : max(1, (int) $batch);
    }

    /**
     * @return array<string, string>
     */
    private function parseArguments(): array
    {
        $parsed = [];

        foreach ((array) $this->option('arg') as $pair) {
            if (! is_string($pair) || ! str_contains($pair, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $pair, 2);
            $key = mb_trim($key);

            if ($key !== '') {
                $parsed[$key] = $value;
            }
        }

        return $parsed;
    }
}
