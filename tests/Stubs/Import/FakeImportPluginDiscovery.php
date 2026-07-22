<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Import;

use Modules\Core\Import\Contracts\ImportPluginDiscoveryInterface;

final readonly class FakeImportPluginDiscovery implements ImportPluginDiscoveryInterface
{
    /**
     * @param  list<class-string>  $importers
     */
    public function __construct(
        private ?string $rootPath = null,
        private ?string $autoload = null,
        private array $importers = [],
    ) {}

    public function label(): string
    {
        return 'test-importers';
    }

    public function root(): ?string
    {
        return $this->rootPath;
    }

    public function autoloadPath(?string $root = null): ?string
    {
        return $this->autoload;
    }

    public function discoverImplementations(?string $root = null): array
    {
        return $this->importers;
    }
}
