<?php

declare(strict_types=1);

namespace Modules\Core\Import\Support;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Modules\Core\Import\Contracts\BulkImporterInterface;
use Modules\Core\Import\Contracts\BulkImporterResolverInterface;

final readonly class ContainerBulkImporterResolver implements BulkImporterResolverInterface
{
    /**
     * @param  class-string<BulkImporterInterface>  $contract
     */
    public function __construct(
        private Container $container,
        private string $contract = BulkImporterInterface::class,
    ) {
        if (! is_a($this->contract, BulkImporterInterface::class, true)) {
            throw new InvalidArgumentException(
                "Importer contract [{$this->contract}] must extend ".BulkImporterInterface::class.'.',
            );
        }
    }

    public function resolve(string $importerClass, array $parameters): BulkImporterInterface
    {
        $importer = $this->container->make($importerClass, $parameters);

        if (! $importer instanceof $this->contract) {
            throw new InvalidArgumentException(
                "Importer [{$importerClass}] must implement {$this->contract}.",
            );
        }

        return $importer;
    }

    public function contract(): string
    {
        return $this->contract;
    }
}
