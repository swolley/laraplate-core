<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Modules\Core\Import\Contracts\BulkImporterResolverInterface;
use Modules\Core\Import\Contracts\ConnectionAwareBulkImporterInterface;
use Modules\Core\Import\Contracts\ImportPluginDiscoveryInterface;
use Modules\Core\Import\Support\BulkImportRunner;
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

    final public function handle(): int
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

        if ((bool) $this->option('no-search') || $dry_run) {
            config(['scout.driver' => 'null']);
            $this->warn('Search indexing disabled for this import.');
        }

        $connection = $importer instanceof ConnectionAwareBulkImporterInterface
            ? $importer->importConnection()
            : null;
        $imported = $this->runner->run(
            $dry_run,
            static fn (): int => $importer->import(),
            $connection,
        );

        $this->info("Imported {$imported} record(s)".($dry_run ? ' (dry-run, rolled back).' : '.'));

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
            ['dry-run', null, InputOption::VALUE_NONE, 'Roll back default-connection database writes'],
            ['limit', null, InputOption::VALUE_OPTIONAL, 'Maximum number of records to import'],
            ['no-search', null, InputOption::VALUE_NONE, 'Disable search indexing for the duration of the import'],
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

        if (! $this->confirm("Found {$this->discovery->label()} at {$root}. Load its Composer autoloader?", false)) {
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

        $selected = $this->choice('Select an importer (optional)', [self::SKIP_IMPORTER, ...$importers], 0);

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
